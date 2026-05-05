<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\ChecklistRun;
use App\Models\ChecklistRunItem;
use App\Models\ChecklistTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Staff API for the My Lahab open/close checklists.
 *
 * Endpoints:
 *   GET  /api/v1/staff/checklists/today
 *        — templates due today (active + branch-scoped) plus the
 *          in-progress run (if any) for each.
 *   POST /api/v1/staff/checklists/{template_id}/start
 *        — create a new run row for today (idempotent: returns the
 *          existing in-progress run if there is one).
 *   POST /api/v1/staff/checklists/runs/{run_id}/items/{item_id}/toggle
 *        — flip a check on/off; multipart for optional photo + note.
 *   POST /api/v1/staff/checklists/runs/{run_id}/complete
 *        — close out the run (rejects if any required item still
 *          unchecked).
 *   GET  /api/v1/staff/checklists/runs?date=
 *        — recent runs (default last 7 days) for audit.
 *
 * Branch scoping: every read is filtered to the staff's branch_id (or
 * global). Writes are branch-pinned to whatever the staff's branch is
 * when they start the run — runs are immutable after start so a staff
 * moving branch mid-shift doesn't orphan the data.
 */
class StaffChecklistController extends Controller
{
    public function today(): JsonResponse
    {
        $admin = auth('staff_api')->user();
        $today = now()->toDateString();

        $templates = ChecklistTemplate::query()
            ->active()
            ->forBranch($admin->branch_id)
            ->with(['items.assignedDesignation:id,name', 'items.assignedAdmin:id,f_name,l_name'])
            ->orderByRaw("FIELD(kind, 'open', 'daily', 'close', 'weekly')")
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Pull all of today's runs in one shot to avoid n+1.
        $runs = ChecklistRun::query()
            ->whereIn('template_id', $templates->pluck('id'))
            ->where('run_date', $today)
            ->where(function ($q) use ($admin) {
                if ($admin->branch_id) {
                    $q->where('branch_id', $admin->branch_id)->orWhereNull('branch_id');
                } else {
                    $q->whereNull('branch_id');
                }
            })
            ->with('items')
            ->get()
            ->keyBy('template_id');

        // Backfill any template items added AFTER the run started so the
        // staff sees newly-assigned tasks without the office having to
        // restart the run. Snapshot fields stay frozen for items that
        // already exist on the run.
        foreach ($runs as $tplId => $run) {
            $template = $templates->firstWhere('id', $tplId);
            if ($template) $this->syncRunItems($run, $template);
        }

        $payload = $templates->map(function (ChecklistTemplate $t) use ($runs) {
            $run = $runs[$t->id] ?? null;
            return [
                'id'        => $t->id,
                'name'      => $t->name,
                'kind'      => $t->kind,
                'item_count'=> $t->items->count(),
                'run'       => $run ? $this->runPayload($run) : null,
            ];
        })->values();

        return response()->json([
            'date'      => $today,
            'templates' => $payload,
        ]);
    }

    public function start(int $templateId): JsonResponse
    {
        $admin = auth('staff_api')->user();
        $today = now()->toDateString();

        $template = ChecklistTemplate::query()
            ->active()
            ->forBranch($admin->branch_id)
            ->with('items')
            ->find($templateId);
        if (! $template) {
            return response()->json(['errors' => [['code' => 'not-found', 'message' => 'Checklist not found.']]], 404);
        }

        // Idempotent: if there's already an in-progress run for today, return it.
        $existing = ChecklistRun::query()
            ->where('template_id', $templateId)
            ->where('run_date', $today)
            ->whereNull('completed_at')
            ->where(function ($q) use ($admin) {
                if ($admin->branch_id) {
                    $q->where('branch_id', $admin->branch_id)->orWhereNull('branch_id');
                } else {
                    $q->whereNull('branch_id');
                }
            })
            ->with('items')
            ->first();
        if ($existing) {
            // Backfill any new template items added since the run started.
            $template->load(['items.assignedDesignation:id,name', 'items.assignedAdmin:id,f_name,l_name']);
            $this->syncRunItems($existing, $template);
            return response()->json(['data' => $this->runPayload($existing->fresh('items'))]);
        }

        $run = DB::transaction(function () use ($template, $admin, $today) {
            $run = ChecklistRun::create([
                'template_id'         => $template->id,
                'branch_id'           => $admin->branch_id,
                'started_by_admin_id' => $admin->id,
                'started_at'          => now(),
                'run_date'            => $today,
            ]);

            // Snapshot every template item onto the run — including the
            // assignment + scheduled time. Snapshot the designation NAME
            // and assignee NAME too so the audit trail survives a rename.
            $template->load(['items.assignedDesignation:id,name', 'items.assignedAdmin:id,f_name,l_name']);
            foreach ($template->items as $item) {
                $assigneeName = $item->assignedAdmin
                    ? trim(($item->assignedAdmin->f_name ?? '') . ' ' . ($item->assignedAdmin->l_name ?? ''))
                    : null;
                ChecklistRunItem::create([
                    'run_id'                    => $run->id,
                    'template_item_id'          => $item->id,
                    'label_snapshot'            => $item->label,
                    'is_required'               => (bool) $item->is_required,
                    'requires_photo'            => (bool) $item->requires_photo,
                    'assigned_designation_id'   => $item->assigned_designation_id,
                    'assigned_designation_name' => $item->assignedDesignation?->name,
                    'assigned_admin_id'         => $item->assigned_admin_id,
                    'assigned_admin_name'       => $assigneeName,
                    'scheduled_time'            => $item->scheduled_time,
                ]);
            }

            return $run->load('items');
        });

        return response()->json(['data' => $this->runPayload($run)], 201);
    }

    public function toggleItem(Request $request, int $runId, int $itemId): JsonResponse
    {
        $admin = auth('staff_api')->user();

        $validator = Validator::make($request->all(), [
            'note'  => 'nullable|string|max:500',
            // Hard cap matches the client-side PhotoCompressor target
            // (1 MB). Anything larger means client compression failed.
            'photo' => 'nullable|image|max:1024',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        $run = $this->ownRun($runId, $admin);
        if (! $run) return response()->json(['errors' => [['code' => 'not-found', 'message' => 'Run not found.']]], 404);
        if ($run->isComplete()) {
            return response()->json([
                'errors' => [['code' => 'already-complete', 'message' => 'Checklist already completed.']],
            ], 422);
        }

        $item = ChecklistRunItem::where('run_id', $runId)->where('id', $itemId)->first();
        if (! $item) return response()->json(['errors' => [['code' => 'not-found', 'message' => 'Item not found.']]], 404);

        if ($item->isChecked()) {
            // Once checked, a step is locked — staff can't accidentally
            // (or maliciously) un-tick. Office can still wipe a row from
            // the admin panel if a genuine mistake was made.
            return response()->json([
                'errors' => [['code' => 'locked',
                    'message' => 'This step is already submitted and locked.']],
            ], 422);
        }

        // Once the staff has submitted their work for this run, all
        // their items are frozen too — keeps the audit trail clean.
        $alreadySubmitted = \App\Models\ChecklistRunSubmission::query()
            ->where('run_id', $run->id)
            ->where('admin_id', $admin->id)
            ->exists();
        if ($alreadySubmitted) {
            return response()->json([
                'errors' => [['code' => 'submitted',
                    'message' => 'You already submitted this checklist.']],
            ], 422);
        }

        // Photo-required steps cannot be ticked with a tap. Either the
        // request must carry a fresh photo OR the row must already
        // have one from a prior partial upload.
        $hasFreshPhoto = $request->hasFile('photo');
        if ($item->requires_photo && ! $hasFreshPhoto && empty($item->photo_path)) {
            return response()->json([
                'errors' => [['code' => 'photo-required',
                    'message' => 'A photo is required for this step.']],
            ], 422);
        }

        $photoPath = $item->photo_path;
        if ($hasFreshPhoto) {
            $file = $request->file('photo');
            $name = uniqid('chk_', true) . '.' . $file->getClientOriginalExtension();
            $file->storeAs('checklist', $name, 'public');
            $photoPath = 'checklist/' . $name;
        }
        $item->fill([
            'checked_at'          => now(),
            'checked_by_admin_id' => $admin->id,
            'photo_path'          => $photoPath,
            'note'                => $request->input('note'),
        ])->save();

        return response()->json(['data' => $this->runPayload($run->fresh('items'))]);
    }

    /**
     * Submit MY work — the staff confirms their own assigned items are done.
     *
     * Rules:
     *   - All required items assigned to me must be checked.
     *   - One submission per person per run (idempotent — re-submitting
     *     just touches the timestamp).
     *   - The run as a whole is auto-completed only when every distinct
     *     assignee has submitted.
     *
     * Items unassigned (no specific person + no designation) are
     * collectively-owned — they must be checked by *someone* before the
     * very last submitter triggers the run-complete.
     */
    public function complete(Request $request, int $runId): JsonResponse
    {
        $admin = auth('staff_api')->user();
        $run = $this->ownRun($runId, $admin);
        if (! $run) return response()->json(['errors' => [['code' => 'not-found', 'message' => 'Run not found.']]], 404);
        if ($run->isComplete()) return response()->json(['data' => $this->runPayload($run->fresh('items'))]);

        // What items count as "mine"? Same rule as isMine() at item-render time.
        $items = $run->items()->get();
        $mine = $items->filter(fn ($i) => $this->itemBelongsTo($i, $admin));
        $myUnchecked = $mine->where('is_required', true)->whereNull('checked_at')->count();
        if ($myUnchecked > 0) {
            return response()->json([
                'errors' => [[
                    'code'    => 'my-incomplete',
                    'message' => "$myUnchecked of your required item" . ($myUnchecked == 1 ? '' : 's') . ' still unchecked.',
                ]],
            ], 422);
        }

        // Record the per-person submission.
        \App\Models\ChecklistRunSubmission::updateOrCreate(
            ['run_id' => $run->id, 'admin_id' => $admin->id],
            [
                'admin_name'   => trim(($admin->f_name ?? '') . ' ' . ($admin->l_name ?? '')) ?: null,
                'submitted_at' => now(),
                'note'         => $request->input('notes'),
            ],
        );

        // Auto-complete the run iff every distinct assignee has submitted
        // AND every required item is checked (covers the unassigned ones).
        $expected = $this->expectedAssignees($items);
        $submittedIds = \App\Models\ChecklistRunSubmission::query()
            ->where('run_id', $run->id)
            ->pluck('admin_id')
            ->all();
        $allSubmitted = $expected->isEmpty()
            || $expected->diff($submittedIds)->isEmpty();
        $unchecked = $items->where('is_required', true)->whereNull('checked_at')->count();

        if ($allSubmitted && $unchecked === 0) {
            $run->update(['completed_at' => now()]);
        }

        return response()->json(['data' => $this->runPayload($run->fresh('items'))]);
    }

    /**
     * Distinct admin_ids the run is "waiting on". Specific assignees +
     * (if any unassigned-by-person but assigned-by-designation items
     * exist) one representative per designation. Items with no
     * assignment at all are collectively-owned and don't add any
     * required submitter — the run completes when checks land.
     */
    private function expectedAssignees(\Illuminate\Support\Collection $items): \Illuminate\Support\Collection
    {
        $explicitAdmins = $items->whereNotNull('assigned_admin_id')->pluck('assigned_admin_id')->unique();

        // For designation-only items, anyone with that designation can
        // submit, but at least one of them must. We approximate by
        // requiring one submission *per designation* — counted via the
        // first staff in the branch with that designation. Cheap + good
        // enough for restaurant-scale teams.
        $designationOnly = $items
            ->whereNull('assigned_admin_id')
            ->whereNotNull('assigned_designation_id')
            ->pluck('assigned_designation_id')
            ->unique();
        $designationReps = collect();
        if ($designationOnly->isNotEmpty()) {
            $designationReps = \Illuminate\Support\Facades\DB::table('admins')
                ->where('status', 1)
                ->whereIn('designation_id', $designationOnly)
                ->select(\Illuminate\Support\Facades\DB::raw('MIN(id) as id, designation_id'))
                ->groupBy('designation_id')
                ->pluck('id');
        }

        return $explicitAdmins->concat($designationReps)->unique()->values();
    }

    private function itemBelongsTo($item, $admin): bool
    {
        if ($item->assigned_admin_id) {
            return (int) $item->assigned_admin_id === (int) $admin->id;
        }
        if ($item->assigned_designation_id) {
            return (int) $item->assigned_designation_id === (int) $admin->designation_id;
        }
        return true;
    }

    public function recentRuns(Request $request): JsonResponse
    {
        $admin = auth('staff_api')->user();
        $perPage = (int) min(50, max(5, (int) $request->query('per_page', 20)));

        $page = ChecklistRun::query()
            ->with('template:id,name,kind')
            ->where(function ($q) use ($admin) {
                if ($admin->branch_id) {
                    $q->where('branch_id', $admin->branch_id)->orWhereNull('branch_id');
                } else {
                    $q->whereNull('branch_id');
                }
            })
            ->orderByDesc('run_date')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => $page->getCollection()->map(fn (ChecklistRun $r) => [
                'id'           => $r->id,
                'template'     => $r->template ? ['id' => $r->template->id, 'name' => $r->template->name, 'kind' => $r->template->kind] : null,
                'run_date'     => optional($r->run_date)->toDateString(),
                'started_at'   => optional($r->started_at)->toIso8601String(),
                'completed_at' => optional($r->completed_at)?->toIso8601String(),
            ])->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
            ],
        ]);
    }

    /**
     * Insert run-item snapshot rows for any template items that didn't
     * exist when the run was originally started. Never deletes — old
     * snapshots stay (their template item may have been removed but
     * the audit trail is preserved). Idempotent + safe to re-run.
     */
    private function syncRunItems(ChecklistRun $run, ChecklistTemplate $template): void
    {
        $existingTplItemIds = ChecklistRunItem::query()
            ->where('run_id', $run->id)
            ->pluck('template_item_id')
            ->all();
        $existingSet = array_flip($existingTplItemIds);

        $missing = $template->items->filter(fn ($i) => ! isset($existingSet[$i->id]));
        if ($missing->isEmpty()) return;

        foreach ($missing as $item) {
            $assigneeName = $item->assignedAdmin
                ? trim(($item->assignedAdmin->f_name ?? '') . ' ' . ($item->assignedAdmin->l_name ?? ''))
                : null;
            ChecklistRunItem::create([
                'run_id'                    => $run->id,
                'template_item_id'          => $item->id,
                'label_snapshot'            => $item->label,
                'is_required'               => (bool) $item->is_required,
                'requires_photo'            => (bool) $item->requires_photo,
                'assigned_designation_id'   => $item->assigned_designation_id,
                'assigned_designation_name' => $item->assignedDesignation?->name,
                'assigned_admin_id'         => $item->assigned_admin_id,
                'assigned_admin_name'       => $assigneeName,
                'scheduled_time'            => $item->scheduled_time,
            ]);
        }
    }

    private function ownRun(int $runId, $admin): ?ChecklistRun
    {
        return ChecklistRun::query()
            ->where('id', $runId)
            ->where(function ($q) use ($admin) {
                if ($admin->branch_id) {
                    $q->where('branch_id', $admin->branch_id)->orWhereNull('branch_id');
                } else {
                    $q->whereNull('branch_id');
                }
            })
            ->with('items')
            ->first();
    }

    private function runPayload(ChecklistRun $run): array
    {
        $items = $run->relationLoaded('items') ? $run->items : $run->items()->get();
        $checked   = $items->whereNotNull('checked_at')->count();
        $required  = $items->where('is_required', true)->count();
        $reqLeft   = $items->where('is_required', true)->whereNull('checked_at')->count();

        $admin = auth('staff_api')->user();
        $mySubmission = \App\Models\ChecklistRunSubmission::query()
            ->where('run_id', $run->id)
            ->where('admin_id', $admin->id)
            ->first();

        return [
            'id'             => $run->id,
            'template_id'    => $run->template_id,
            'branch_id'      => $run->branch_id,
            'started_at'     => optional($run->started_at)->toIso8601String(),
            'completed_at'   => optional($run->completed_at)?->toIso8601String(),
            'is_complete'    => $run->isComplete(),
            'checked_count'  => $checked,
            'total_count'    => $items->count(),
            'required_left'  => $reqLeft,
            'required_total' => $required,
            'my_submitted_at'=> optional($mySubmission?->submitted_at)->toIso8601String(),
            'items'          => $items->map(fn (ChecklistRunItem $i) => [
                'id'                 => $i->id,
                'label'              => $i->label_snapshot,
                'is_required'        => (bool) $i->is_required,
                'requires_photo'     => (bool) $i->requires_photo,
                'is_checked'         => $i->isChecked(),
                'checked_at'         => optional($i->checked_at)?->toIso8601String(),
                'note'               => $i->note,
                'photo_url'          => $i->photo_path
                    ? asset('storage/app/public/' . $i->photo_path)
                    : null,
                'assigned_designation_id'   => $i->assigned_designation_id,
                'assigned_designation_name' => $i->assigned_designation_name,
                'assigned_admin_id'         => $i->assigned_admin_id,
                'assigned_admin_name'       => $i->assigned_admin_name,
                'scheduled_time'            => $i->scheduled_time
                    ? \Illuminate\Support\Carbon::parse($i->scheduled_time)->format('H:i')
                    : null,
                // Convenience flag for the Flutter UI: is this step
                // mine specifically? (specific person OR my designation
                // OR unassigned). Lets the UI grey out other people's
                // steps without a second round-trip.
                'is_mine'                   => $this->isMine($i),
            ])->values(),
        ];
    }

    /**
     * Resolution: specific person assignment wins; else designation;
     * else "anyone" (true for everyone in the branch).
     */
    private function isMine(ChecklistRunItem $i): bool
    {
        return $this->itemBelongsTo($i, auth('staff_api')->user());
    }
}
