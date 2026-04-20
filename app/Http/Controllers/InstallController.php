<?php

namespace App\Http\Controllers;

use App\CentralLogics\Helpers;
use App\Traits\ActivationClass;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;

class InstallController extends Controller
{
    use ActivationClass;

    public function step0(): Factory|View|Application
    {
        return view('installation.step0');
    }

    public function step1(Request $request): View|Factory|RedirectResponse|Application
    {
        if (Hash::check('step_1', $request['token'])) {
            $permission['curl_enabled'] = function_exists('curl_version');
            $permission['db_file_write_perm'] = is_writable(base_path('.env'));
            $permission['routes_file_write_perm'] = is_writable(base_path('app/Providers/RouteServiceProvider.php'));
            return view('installation.step1', compact('permission'));
        }
        session()->flash('error', 'Access denied!');
        return redirect()->route('step0');
    }

    public function step2(Request $request): View|Factory|RedirectResponse|Application
    {
        if (Hash::check('step_2', $request['token'])) {
            return view('installation.step2');
        }
        session()->flash('error', 'Access denied!');
        return redirect()->route('step0');
    }

    public function step3(Request $request): View|Factory|RedirectResponse|Application
    {
        if (Hash::check('step_3', $request['token'])) {
            return view('installation.step3');
        }
        session()->flash('error', 'Access denied!');
        return redirect()->route('step0');
    }

    public function step4(Request $request): View|Factory|RedirectResponse|Application
    {
        if (Hash::check('step_4', $request['token'])) {
            return view('installation.step4');
        }
        session()->flash('error', 'Access denied!');
        return redirect()->route('step0');
    }

    public function step5(Request $request): View|Factory|RedirectResponse|Application
    {
        if (Hash::check('step_5', $request['token'])) {
            return view('installation.step5');
        }
        session()->flash('error', 'Access denied!');
        return redirect()->route('step0');
    }

    public function purchase_code(Request $request): RedirectResponse
    {
        return redirect('step3?token=' . bcrypt('step_3'));
    }

    public function system_settings(Request $request): View|Factory|RedirectResponse|Application
    {
        if (!Hash::check('step_6', $request['token'])) {
            session()->flash('error', 'Access denied!');
            return redirect()->route('step0');
        }

        Validator::make($request->all(),[
            'admin_password' => 'required|min:8',
            'confirm_password' => 'required|same:admin_password',
        ])->validate();

        DB::table('admins')->insertOrIgnore([
            'f_name' => $request['admin_f_name'],
            'l_name' => $request['admin_l_name'],
            'email' => $request['admin_email'],
            'password' => bcrypt($request['admin_password']),
            'phone' => $request['phone_code'].$request['admin_phone'],
            'admin_role_id' => 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        DB::table('branches')->insertOrIgnore([
            'id' => 1,
            'name' => 'Main Branch',
            'email' => $request['admin_email'],
            'password' => bcrypt($request['admin_password']),
            'coverage' => 0,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        DB::table('admin_roles')->insertOrIgnore([
            'id' => 1,
            'name' => 'Master Admin',
            'module_access' => null,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        DB::table('business_settings')->where(['key' => 'restaurant_name'])->update([
            'value' => $request['web_name']
        ]);

        Helpers::setEnvironmentValue('APP_STATE', 'installed');
        return view('installation.step6');
    }

    public function database_installation(Request $request): RedirectResponse
    {
        if (self::check_database_connection($request->DB_HOST, $request->DB_DATABASE, $request->DB_USERNAME, $request->DB_PASSWORD)) {

            $existingKey = env('APP_KEY');
            $key = $existingKey ?: 'base64:' . base64_encode(random_bytes(32));
            $output = "APP_NAME=Lahab\n"
                . "APP_ENV=production\n"
                . "APP_KEY={$key}\n"
                . "APP_DEBUG=false\n"
                . "APP_MODE=live\n"
                . "APP_LOG_LEVEL=debug\n"
                . "APP_URL=" . URL::to('/') . "\n"
                . "APP_STATE=install\n\n"
                . "DB_CONNECTION=mysql\n"
                . "DB_HOST={$request->DB_HOST}\n"
                . "DB_PORT=3306\n"
                . "DB_DATABASE={$request->DB_DATABASE}\n"
                . "DB_USERNAME={$request->DB_USERNAME}\n"
                . "DB_PASSWORD={$request->DB_PASSWORD}\n\n"
                . "BROADCAST_DRIVER=log\n"
                . "CACHE_DRIVER=file\n"
                . "SESSION_DRIVER=file\n"
                . "SESSION_LIFETIME=120\n"
                . "QUEUE_CONNECTION=sync\n\n"
                . "REDIS_HOST=127.0.0.1\n"
                . "REDIS_PASSWORD=null\n"
                . "REDIS_PORT=6379\n\n"
                . "PUSHER_APP_ID=\n"
                . "PUSHER_APP_KEY=\n"
                . "PUSHER_APP_SECRET=\n"
                . "PUSHER_APP_CLUSTER=mt1\n\n"
                . "SOFTWARE_VERSION=11.7\n";
            $file = fopen(base_path('.env'), 'w');
            fwrite($file, $output);
            fclose($file);

            $path = base_path('.env');
            if (file_exists($path)) {
                return redirect()->route('step4', ['token' => $request['token']]);
            } else {
                session()->flash('error', 'Database error!');
                return redirect()->route('step3', ['token' => bcrypt('step_3')]);
            }
        } else {
            session()->flash('error', 'Database error!');
            return redirect()->route('step3', ['token' => bcrypt('step_3')]);
        }
    }

    public function import_sql(): RedirectResponse
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '-1');
        try {
            $sql_path = base_path('installation/backup/database.sql');
            DB::unprepared(file_get_contents($sql_path));
            return redirect()->route('step5',['token' => bcrypt('step_5')]);
        } catch (\Exception $exception) {
            session()->flash('error', 'Your database is not clean, do you want to clean database then import?');
            return back();
        }
    }

    public function force_import_sql(): RedirectResponse
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '-1');
        try {
            Artisan::call('db:wipe');
            $sql_path = base_path('installation/backup/database.sql');
            DB::unprepared(file_get_contents($sql_path));
            return  redirect()->route('step5',['token' => bcrypt('step_5')]);
        } catch (\Exception $exception) {
            session()->flash('error', 'Check your database permission!');
            return back();
        }
    }

    function check_database_connection($db_host = "", $db_name = "", $db_user = "", $db_pass = ""): bool
    {
        if (@mysqli_connect($db_host, $db_user, $db_pass, $db_name)) {
            return true;
        } else {
            return false;
        }
    }
}
