<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * One-off web wrapper for the artisan commands cPanel hosts won't let
 * you run from a shell. Lives behind admin auth + a one-time token so
 * a passer-by who bookmarks the URL can't run migrations on a live db.
 *
 * Usage from a logged-in admin browser:
 *   1. Hit `/admin/maintenance` — page shows current cache state and a
 *      single "Run update" button.
 *   2. Click the button — runs `migrate --force`, clears config / view /
 *      route caches, and dumps the artisan output back on the page.
 *   3. After the deploy is settled, REMOVE this controller + route.
 *
 * Why a token: cPanel installs sometimes leave admin sessions wide open
 * via stale cookies. The token (set in `.env` as `MAINTENANCE_TOKEN`)
 * adds a second factor: even with an admin session, you can't trigger
 * a migration without knowing the token. If MAINTENANCE_TOKEN is empty
 * the route refuses to run anything (fail-closed default).
 */
class MaintenanceController extends Controller
{
    public function index(Request $request)
    {
        return view('admin-views.maintenance.index', [
            'has_token'   => filled(env('MAINTENANCE_TOKEN')),
            'last_output' => Cache::get('maintenance_last_output'),
        ]);
    }

    public function run(Request $request)
    {
        $expected = (string) env('MAINTENANCE_TOKEN', '');
        $given    = (string) $request->input('token', '');
        if ($expected === '' || !hash_equals($expected, $given)) {
            return back()->with('error', 'Invalid or missing maintenance token.');
        }

        $log = [];
        $log[] = '=== migrate --force ===';
        Artisan::call('migrate', ['--force' => true]);
        $log[] = trim(Artisan::output());

        $log[] = "\n=== config:clear ===";
        Artisan::call('config:clear');
        $log[] = trim(Artisan::output());

        $log[] = "\n=== route:clear ===";
        Artisan::call('route:clear');
        $log[] = trim(Artisan::output());

        $log[] = "\n=== view:clear ===";
        Artisan::call('view:clear');
        $log[] = trim(Artisan::output());

        $log[] = "\n=== cache:clear ===";
        Artisan::call('cache:clear');
        $log[] = trim(Artisan::output());

        $output = implode("\n", $log);
        Cache::put('maintenance_last_output', $output, now()->addHour());

        return back()->with('success', 'Update ran successfully — see output below.');
    }

    /**
     * Run the test-data reset via the web wrapper. Three guards:
     *   1. MAINTENANCE_TOKEN must match (same as the migrate path)
     *   2. The operator must type a confirmation phrase exactly
     *      (env-name on production, "RESET" on local) — protects against
     *      a fat-finger admin clicking through dialogs.
     *   3. The artisan command itself runs with --confirm so it doesn't
     *      block on stdin (web request can't answer prompts).
     *
     * On finish: stores the artisan output in cache so the page can
     * render it, and returns the operator to the maintenance page.
     */
    public function resetTestData(Request $request)
    {
        $expected = (string) env('MAINTENANCE_TOKEN', '');
        $given    = (string) $request->input('token', '');
        if ($expected === '' || !hash_equals($expected, $given)) {
            return back()->with('error', 'Invalid or missing maintenance token.');
        }

        // Confirmation phrase — env name on prod, "RESET" anywhere else.
        // Forces the operator to actually type a fresh string into the
        // form, not just check a checkbox.
        $env = config('app.env', 'unknown');
        $expectedPhrase = in_array($env, ['production', 'prod', 'live'], true) ? $env : 'RESET';
        $phrase = trim((string) $request->input('confirm_phrase', ''));
        if ($phrase !== $expectedPhrase) {
            return back()->with('error', 'Confirmation phrase did not match. Type "' . $expectedPhrase . '" exactly to proceed.');
        }

        // Dry-run flag — page can request a preview without writing.
        $dryRun = (bool) $request->input('dry_run', false);

        $log = [];
        $log[] = '=== reset:test-data ' . ($dryRun ? '--dry-run' : '--confirm') . ' ===';
        $log[] = '(env: ' . $env . ')';
        Artisan::call('reset:test-data', $dryRun ? ['--dry-run' => true] : ['--confirm' => true]);
        $log[] = trim(Artisan::output());

        if (!$dryRun) {
            // Real run already cleared in-flight ledger / order rows;
            // bust caches in case anything denormalised was sitting there.
            $log[] = "\n=== cache:clear ===";
            Artisan::call('cache:clear');
            $log[] = trim(Artisan::output());
        }

        $output = implode("\n", $log);
        Cache::put('maintenance_last_output', $output, now()->addHour());

        return back()->with($dryRun ? 'success' : 'success',
            $dryRun ? 'Dry-run done — review the output below.' : 'Reset completed. Output below.');
    }
}
