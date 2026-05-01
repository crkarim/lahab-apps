<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Model\Admin;
use App\Models\AttendanceLog;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Biometric attendance importer.
 *
 * Bridges a ZKTeco (or any fingerprint / face / RFID) device's
 * attendance log into our `attendance_logs` table. Accepts CSV
 * uploads with rows in either of two shapes:
 *
 *   1. Explicit:    user_id, timestamp, event_type
 *      where event_type ∈ {in, out, IN, OUT, 0, 1}
 *      0 / "in"  → clock-in;  1 / "out" → clock-out
 *
 *   2. Alternating: user_id, timestamp
 *      device didn't tag in/out — we infer by ordering events per
 *      user and toggling. First event of the day is IN.
 *
 * `user_id` matches `admins.employee_code` (the field on the employee
 * edit form). Rows for codes that don't exist are reported as
 * "unknown" rather than silently dropped.
 *
 * Idempotency: a (admin_id, clock_in_at-to-the-minute) pair already
 * present in attendance_logs is skipped. Re-importing the same file
 * is safe.
 */
class BiometricImportController extends Controller
{
    /** Render the upload form + import history (last 10 imports). */
    public function index(): Renderable
    {
        $admin    = auth('admin')->user();
        $branchId = $admin?->branch_id;

        // Recent biometric rows for context — shows whether previous
        // imports landed and lets the operator spot last sync time.
        $recent = AttendanceLog::query()
            ->where('method', 'biometric')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->with('employee:id,f_name,l_name,employee_code')
            ->orderByDesc('clock_in_at')
            ->limit(20)
            ->get();

        return view('admin-views.biometric.index', [
            'recent' => $recent,
        ]);
    }

    /**
     * POST handler: parses the uploaded CSV, creates / updates
     * attendance_logs rows, returns a result page summarising
     * imported / skipped / errored counts with line-item details.
     */
    public function import(Request $request): Renderable|RedirectResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120', // 5 MB
        ]);

        $admin    = auth('admin')->user();
        $branchId = $admin?->branch_id;

        $path = $validated['file']->getRealPath();
        $handle = @fopen($path, 'r');
        if (!$handle) {
            return back()->with('error', 'Could not read the uploaded file.');
        }

        // Detect header row — if first column is non-numeric AND not a
        // date, treat as header and skip.
        $first = fgetcsv($handle);
        $hasHeader = $first !== false && !is_numeric(trim($first[0] ?? '')) && !$this->looksLikeDate($first[1] ?? '');
        if (!$hasHeader && $first !== false) {
            // Rewind so the data row gets processed.
            rewind($handle);
        }

        // Cache employee_code -> admin lookup so we don't hit the DB
        // per row. Branch-scope it so a code from a foreign branch
        // can't sneak in via a wrongly-routed CSV.
        $codeToAdmin = Admin::query()
            ->whereNotNull('employee_code')
            ->where('employee_code', '!=', '')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->get(['id', 'employee_code', 'branch_id'])
            ->keyBy('employee_code');

        // Pre-bucket rows per employee so alternating-mode pairing
        // can walk events in chronological order. Explicit-mode rows
        // are processed inline.
        $eventsByEmployee = []; // employee_code => [['ts'=>Carbon, 'type'=>'in'/'out'/null], ...]
        $errors = [];
        $unknowns = []; // codes that didn't match an admin
        $rowNum = $hasHeader ? 1 : 0;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            // Skip empty / underfilled rows.
            if (count($row) < 2 || trim((string) ($row[0] ?? '')) === '') continue;

            $code = trim((string) $row[0]);
            $tsRaw = trim((string) ($row[1] ?? ''));
            $typeRaw = isset($row[2]) ? strtolower(trim((string) $row[2])) : null;

            try {
                $ts = Carbon::parse($tsRaw);
            } catch (\Throwable $e) {
                $errors[] = "Row $rowNum: invalid timestamp '$tsRaw'";
                continue;
            }

            // Don't accept future-dated events (clock skew on device).
            if ($ts->greaterThan(now()->addMinutes(5))) {
                $errors[] = "Row $rowNum: timestamp $tsRaw is in the future";
                continue;
            }

            if (!isset($codeToAdmin[$code])) {
                $unknowns[$code] = ($unknowns[$code] ?? 0) + 1;
                continue;
            }

            $type = match (true) {
                in_array($typeRaw, ['in', '0'], true)  => 'in',
                in_array($typeRaw, ['out', '1'], true) => 'out',
                default                                => null,
            };

            $eventsByEmployee[$code][] = ['ts' => $ts, 'type' => $type];
        }
        fclose($handle);

        // Process events. Alternating mode kicks in when the row's
        // type is null — we use the employee's current open-row state
        // to decide whether to start a new IN or close the open one.
        $imported = 0;
        $skippedDup = 0;
        $closedOpen = 0;

        foreach ($eventsByEmployee as $code => $events) {
            // Sort by timestamp ascending so alternating works right.
            usort($events, fn ($a, $b) => $a['ts']->lt($b['ts']) ? -1 : 1);

            $emp = $codeToAdmin[$code];

            foreach ($events as $ev) {
                $ts   = $ev['ts'];
                $type = $ev['type'];

                // Determine effective type if not explicit.
                if ($type === null) {
                    $open = AttendanceLog::openFor($emp->id);
                    $type = $open ? 'out' : 'in';
                }

                if ($type === 'in') {
                    // Idempotency: skip if there's already a row for
                    // this admin within the same minute. Avoids dupes
                    // when re-importing a CSV.
                    $minStart = $ts->copy()->startOfMinute();
                    $minEnd   = $ts->copy()->endOfMinute();
                    $exists = AttendanceLog::query()
                        ->where('admin_id', $emp->id)
                        ->whereBetween('clock_in_at', [$minStart, $minEnd])
                        ->exists();
                    if ($exists) { $skippedDup++; continue; }

                    AttendanceLog::create([
                        'admin_id'    => $emp->id,
                        'branch_id'   => $emp->branch_id,
                        'clock_in_at' => $ts,
                        'method'      => 'biometric',
                        'notes'       => 'Imported from biometric CSV',
                    ]);
                    $imported++;
                } else {
                    // Out — close the latest open row, if any.
                    $open = AttendanceLog::openFor($emp->id);
                    if (!$open) {
                        // Out without a matching in — log so the
                        // operator can see it in the result page,
                        // but don't fail the import.
                        $errors[] = "Out at " . $ts->format('Y-m-d H:i') . " for code $code has no matching open row";
                        continue;
                    }
                    if ($ts->lessThanOrEqualTo($open->clock_in_at)) {
                        $errors[] = "Out at " . $ts->format('Y-m-d H:i') . " precedes its open row's clock-in for code $code";
                        continue;
                    }
                    $open->clock_out_at = $ts;
                    $open->save();
                    $closedOpen++;
                }
            }
        }

        Log::info('Biometric import done', [
            'admin'     => $admin?->id,
            'imported'  => $imported,
            'closed'    => $closedOpen,
            'skipped'   => $skippedDup,
            'unknowns'  => count($unknowns),
            'errors'    => count($errors),
        ]);

        return view('admin-views.biometric.result', [
            'imported'  => $imported,
            'closed'    => $closedOpen,
            'skipped'   => $skippedDup,
            'unknowns'  => $unknowns,
            'errors'    => $errors,
        ]);
    }

    /** Sample CSV download so operators see the expected shape. */
    public function sample(): BinaryFileResponse
    {
        $body = "user_id,timestamp,event_type\n"
              . "1001,2026-05-01 09:13:42,in\n"
              . "1001,2026-05-01 18:05:11,out\n"
              . "1002,2026-05-01 09:18:00,in\n"
              . "1002,2026-05-01 17:55:33,out\n"
              . "# Alternating mode (event_type column omitted): the importer\n"
              . "# pairs first/second/third/etc. events as in/out/in/...\n"
              . "1003,2026-05-01 09:25:00\n"
              . "1003,2026-05-01 18:10:00\n";

        $tmp = tempnam(sys_get_temp_dir(), 'biometric_sample_');
        file_put_contents($tmp, $body);
        return response()->download($tmp, 'biometric_sample.csv', [
            'Content-Type' => 'text/csv',
        ])->deleteFileAfterSend();
    }

    /** Loose timestamp sniff — only used for header detection. */
    private function looksLikeDate(string $s): bool
    {
        if ($s === '') return false;
        try { Carbon::parse($s); return true; } catch (\Throwable $e) { return false; }
    }
}
