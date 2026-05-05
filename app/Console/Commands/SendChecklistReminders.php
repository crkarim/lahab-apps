<?php

namespace App\Console\Commands;

use App\CentralLogics\Helpers;
use App\Model\Admin;
use App\Models\ChecklistRunItem;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pushes "5 minutes until <task>" reminders for scheduled checklist items.
 *
 * Run every minute. For each open run-item that:
 *   - has a scheduled_time
 *   - is not yet checked
 *   - hasn't already received a reminder today (reminder_sent_at NULL)
 *   - whose scheduled_time falls in [now+4min, now+6min] window
 *
 * we send an FCM push to every active staff who:
 *   - belongs to the run's branch
 *   - matches the assigned designation (or the item is unassigned)
 *   - has app_login_enabled + a registered fcm_token
 *
 * Then we stamp `reminder_sent_at` so the same item is never reminded
 * twice in the same day.
 */
class SendChecklistReminders extends Command
{
    protected $signature = 'checklist:send-reminders';
    protected $description = 'Fire 5-min-before reminder pushes for scheduled checklist items.';

    public function handle(): int
    {
        $now   = Carbon::now();
        $today = $now->toDateString();
        $sent  = 0;

        // Pass 1 — 5-min-before reminders (the "polite" first ping).
        $sent += $this->fireWindow(
            window: [
                $now->copy()->addMinutes(4)->format('H:i:s'),
                $now->copy()->addMinutes(6)->format('H:i:s'),
            ],
            now: $now,
            today: $today,
            kind: 'soon',
        );

        // Pass 2 — overdue follow-ups at +15 min and +60 min after
        // scheduled_time. We re-use `reminder_sent_at` as the last-ping
        // timestamp so we don't spam — only re-fire if the most recent
        // ping was ≥ 15 min ago.
        $sent += $this->fireOverdue($now, $today);

        if ($sent > 0) $this->info("Reminders fired: {$sent}");
        return self::SUCCESS;
    }

    /** Fire reminders for items whose scheduled_time matches a window. */
    private function fireWindow(array $window, Carbon $now, string $today, string $kind): int
    {
        $items = ChecklistRunItem::query()
            ->join('checklist_runs as r', 'r.id', '=', 'checklist_run_items.run_id')
            ->whereNull('checklist_run_items.checked_at')
            ->whereNull('checklist_run_items.reminder_sent_at')
            ->whereNotNull('checklist_run_items.scheduled_time')
            ->where('r.run_date', $today)
            ->whereBetween('checklist_run_items.scheduled_time', $window)
            ->select(
                'checklist_run_items.*',
                'r.branch_id as run_branch_id',
            )
            ->get();
        return $this->dispatch($items, $now, $kind);
    }

    /** Fire follow-ups for overdue items (≥ 15 min past, last ping ≥ 15 min ago). */
    private function fireOverdue(Carbon $now, string $today): int
    {
        $cutoffMinutes = 15;
        $items = ChecklistRunItem::query()
            ->join('checklist_runs as r', 'r.id', '=', 'checklist_run_items.run_id')
            ->whereNull('checklist_run_items.checked_at')
            ->whereNotNull('checklist_run_items.scheduled_time')
            ->where('r.run_date', $today)
            // scheduled_time is at least cutoffMinutes in the past
            ->where('checklist_run_items.scheduled_time', '<=', $now->copy()->subMinutes($cutoffMinutes)->format('H:i:s'))
            // last ping was either never, or was at least cutoffMinutes ago
            ->where(function ($q) use ($now, $cutoffMinutes) {
                $q->whereNull('checklist_run_items.reminder_sent_at')
                  ->orWhere('checklist_run_items.reminder_sent_at', '<=', $now->copy()->subMinutes($cutoffMinutes));
            })
            ->select(
                'checklist_run_items.*',
                'r.branch_id as run_branch_id',
            )
            ->get();
        return $this->dispatch($items, $now, 'overdue');
    }

    private function dispatch($items, Carbon $now, string $kind): int
    {
        if ($items->isEmpty()) return 0;

        $sent = 0;
        foreach ($items as $row) {
            $tokens = $this->tokensFor(
                $row->run_branch_id,
                $row->assigned_designation_id,
                $row->assigned_admin_id,
            );
            if (empty($tokens)) {
                ChecklistRunItem::where('id', $row->id)->update(['reminder_sent_at' => $now]);
                continue;
            }

            // Title is just the step name + a soft emoji cue. Office
            // staff asked us to drop the literal "Reminder:" / "Overdue:"
            // word — the urgency comes from the body's timing.
            $emoji = $kind === 'overdue' ? '⚠️' : '⏰';
            $title = "$emoji " . $row->label_snapshot;
            $body  = $row->scheduled_time
                ? ($kind === 'overdue'
                    ? __('Was due at') . ' ' . Carbon::parse($row->scheduled_time)->format('H:i')
                    : __('Due at') . ' ' . Carbon::parse($row->scheduled_time)->format('H:i'))
                : __('Due now');

            foreach ($tokens as $tok) {
                try {
                    // Deep-link payload: app opens to the specific checklist
                    // run when the notification is tapped.
                    Helpers::send_push_notif_to_device($tok, [
                        'title'       => $title,
                        'description' => $body,
                        'image'       => '',
                        'order_id'    => (string) $row->run_id,
                        'type'        => 'checklist_reminder',
                    ]);
                    $sent++;
                } catch (\Throwable $e) {
                    $this->warn("push failed for token {$tok}: {$e->getMessage()}");
                }
            }

            ChecklistRunItem::where('id', $row->id)->update(['reminder_sent_at' => $now]);
        }
        return $sent;
    }

    /**
     * FCM tokens for staff who are eligible to receive this reminder:
     *   - status=1, app_login_enabled=1, has fcm_token
     *   - if a specific person is assigned → only that person
     *   - else if a designation is assigned → branch + designation match
     *   - else → anyone in the branch
     */
    private function tokensFor(?int $branchId, ?int $designationId, ?int $adminId): array
    {
        $q = DB::table('admins')
            ->where('status', 1)
            ->where('app_login_enabled', 1)
            ->whereNotNull('fcm_token');

        if ($adminId) {
            // Specific person beats everything else, including branch.
            return $q->where('id', $adminId)->pluck('fcm_token')->all();
        }
        if ($branchId) {
            $q->where('branch_id', $branchId);
        }
        if ($designationId) {
            $q->where('designation_id', $designationId);
        }

        return $q->pluck('fcm_token')->all();
    }
}
