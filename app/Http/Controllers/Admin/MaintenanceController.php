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
}
