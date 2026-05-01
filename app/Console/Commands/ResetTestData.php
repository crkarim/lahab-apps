<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-shot test-data reset for go-live.
 *
 * Wipes every transactional + test-master row in the system, leaving
 * only the product catalogue + business settings + system tables
 * intact. The "master data with seeded BD defaults" tables (departments,
 * designations, hrm_settings, leave_types, salary_components,
 * expense_categories, cash_accounts) get truncated and **re-seeded
 * inline** so the next run starts from the same baseline a fresh
 * install gives you.
 *
 * Branch products' sold_quantity counters get reset to 0 so daily/fixed
 * stock starts the period clean.
 *
 * Safety:
 *   - --dry-run prints what would happen without writing
 *   - --confirm skips the interactive prompt (for scripted runs)
 *   - default is an interactive double-confirm with the env name shown
 *   - SET FOREIGN_KEY_CHECKS=0 around the truncates; restored after
 *
 * Reversibility:
 *   - NONE without a fresh DB dump. Always `mysqldump > backup.sql`
 *     before running on production.
 *
 * Usage:
 *   php artisan reset:test-data --dry-run     # show what would be wiped
 *   php artisan reset:test-data               # interactive confirm
 *   php artisan reset:test-data --confirm     # skip the prompt
 */
class ResetTestData extends Command
{
    protected $signature = 'reset:test-data
                            {--dry-run : Print plan without writing anything}
                            {--confirm : Skip the interactive confirmation prompt}';

    protected $description = 'Wipe all transactional + test data; keep products + settings; re-seed BD-default master rows. NOT REVERSIBLE — back up the DB first.';

    /**
     * Tables truncated wholesale. Order doesn't matter once FK checks
     * are off, but we list them roughly leaf-first for readability.
     */
    private const WIPE_TABLES = [
        // POS / orders
        'order_partial_payments',
        'order_change_amount',
        'order_status_histories',
        'order_details',
        'orders',
        // Customer-side
        'customer_addresses',
        'transactions',
        'wallet_bonuses',
        'conversations',
        'messages',
        'notifications',
        'users',
        // Cash ledger
        'account_transactions',
        // HRM operations
        'attendance_logs',
        'leave_requests',
        'salary_advances',
        'admin_salary_lines',
        'payslips',
        'payroll_runs',
        'shift_payouts',
        'shifts',
        'cash_handovers',
        'work_schedules',
        // Bills (suppliers wiped too — Phase 8.6 seeds nothing here)
        'expense_payments',
        'expense_lines',
        'expenses',
        'suppliers',
        // Print + biometric noise
        'print_failures',
        'biometric_imports',
    ];

    /**
     * Tables truncated AND re-seeded with the BD-default rows shipped
     * by migrations. Each entry has a closure that runs after truncate.
     */
    private function reseeders(Carbon $now): array
    {
        return [
            'cash_accounts'       => fn () => $this->reseedCashAccounts($now),
            'departments'         => fn () => $this->reseedDepartments($now),
            'designations'        => fn () => $this->reseedDesignations($now),
            'hrm_settings'        => fn () => $this->reseedHrmSettings($now),
            'leave_types'         => fn () => $this->reseedLeaveTypes($now),
            'salary_components'   => fn () => $this->reseedSalaryComponents($now),
            'expense_categories'  => fn () => $this->reseedExpenseCategories($now),
        ];
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $skipPrompt = (bool) $this->option('confirm');

        $env = config('app.env', 'unknown');
        $appUrl = config('app.url', 'unknown');

        $this->info('───────────────────────────────────────────────────────');
        $this->info(' RESET TEST DATA');
        $this->info(' env: ' . $env . '   url: ' . $appUrl);
        $this->info('───────────────────────────────────────────────────────');

        // Show counts up-front so the operator sees how much they're nuking.
        $this->line('Current row counts:');
        $totalToWipe = 0;
        $reseed = $this->reseeders(now());
        foreach ([...self::WIPE_TABLES, ...array_keys($reseed)] as $t) {
            if (!Schema::hasTable($t)) continue;
            $n = DB::table($t)->count();
            if ($n > 0) {
                $this->line(sprintf('  %-30s %d', $t, $n));
                $totalToWipe += $n;
            }
        }
        // Special: count test admin (employee) rows separately.
        $testAdmins = DB::table('admins')->where('admin_role_id', '!=', 1)->count();
        $this->line(sprintf('  %-30s %d (admins where admin_role_id != 1)', 'admins (employees)', $testAdmins));
        $totalToWipe += $testAdmins;

        // Count what's preserved so the operator sees what stays.
        $masterAdmin = DB::table('admins')->where('admin_role_id', 1)->count();
        $products = Schema::hasTable('products') ? DB::table('products')->count() : 0;
        $branches = Schema::hasTable('branches') ? DB::table('branches')->count() : 0;
        $tables   = Schema::hasTable('tables')   ? DB::table('tables')->count()   : 0;
        $this->line('');
        $this->line('Preserved:');
        $this->line(sprintf('  %-30s %d', 'admins (Master Admin)', $masterAdmin));
        $this->line(sprintf('  %-30s %d', 'products', $products));
        $this->line(sprintf('  %-30s %d', 'branches', $branches));
        $this->line(sprintf('  %-30s %d', 'tables (dining)', $tables));
        $this->line('  business_settings, currencies, languages, payment gateways, banners, coupons, etc.');

        $this->line('');
        $this->warn('TOTAL ROWS TO BE WIPED: ' . $totalToWipe);
        $this->warn('Re-seed from BD defaults: ' . implode(', ', array_keys($reseed)));

        if ($dryRun) {
            $this->line('');
            $this->info('Dry-run — no writes performed. Pass --confirm to run for real.');
            return self::SUCCESS;
        }

        if (!$skipPrompt) {
            $this->line('');
            if (!$this->confirm('Type yes to wipe everything above. This is NOT reversible without a DB backup.', false)) {
                $this->info('Aborted.');
                return self::SUCCESS;
            }
            // Second guard for production-shaped envs.
            if (in_array($env, ['production', 'prod', 'live'], true)) {
                $confirm = $this->ask('Production env detected. Type the env name "' . $env . '" exactly to proceed');
                if ($confirm !== $env) {
                    $this->error('Env name did not match. Aborting.');
                    return self::FAILURE;
                }
            }
        }

        $this->line('');
        $this->info('Disabling FK checks…');
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            // 1. Wipe transactional / test tables.
            foreach (self::WIPE_TABLES as $t) {
                if (!Schema::hasTable($t)) {
                    $this->line('  (skip — missing) ' . $t);
                    continue;
                }
                DB::table($t)->truncate();
                $this->line('  truncated: ' . $t);
            }

            // 2. Wipe employees (admins WHERE role != 1) but KEEP Master.
            $deletedEmployees = DB::table('admins')->where('admin_role_id', '!=', 1)->delete();
            $this->line('  deleted employees: ' . $deletedEmployees);

            // 3. Truncate + re-seed master tables with BD defaults.
            $now = now();
            foreach ($this->reseeders($now) as $table => $reseeder) {
                if (!Schema::hasTable($table)) {
                    $this->line('  (skip — missing) ' . $table);
                    continue;
                }
                DB::table($table)->truncate();
                $reseeder();
                $this->line('  reset + reseeded: ' . $table);
            }

            // 4. Reset per-row counters that test sales accumulated.
            if (Schema::hasTable('product_by_branches')) {
                DB::table('product_by_branches')->update(['sold_quantity' => 0]);
                $this->line('  reset product_by_branches.sold_quantity = 0');
            }
            if (Schema::hasTable('branch_products')) {
                DB::table('branch_products')->update(['sold_quantity' => 0]);
                $this->line('  reset branch_products.sold_quantity = 0');
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            $this->info('FK checks re-enabled.');
        }

        $this->line('');
        $this->info('Done. Run a smoke check next:');
        $this->line('  - log in as Master Admin');
        $this->line('  - confirm products list is intact');
        $this->line('  - confirm /admin/cash-accounts shows the seeded HQ Cash + per-branch tills');
        $this->line('  - confirm /admin/expense-categories shows the 17 seeded BD categories');
        $this->line('  - place a test POS order to confirm the auto-post writes correctly');
        return self::SUCCESS;
    }

    // ── Re-seeders (data lifted from the corresponding migrations) ──

    private function reseedCashAccounts(Carbon $now): void
    {
        // One HQ Cash + one Cash Till per branch (mirrors the 2026_05_01_340000 migration).
        DB::table('cash_accounts')->insert([
            'name' => 'HQ Cash on Hand', 'code' => 'hq_cash', 'type' => 'cash', 'provider' => null,
            'account_number' => null, 'branch_id' => null,
            'opening_balance' => 0, 'opening_date' => $now->toDateString(), 'current_balance' => 0,
            'is_active' => true, 'sort_order' => 10, 'color' => '#1E8E3E',
            'notes' => 'Default HQ-level cash safe. Edit or rename as needed.',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $branches = DB::table('branches')->select('id', 'name')->get();
        $sort = 100;
        foreach ($branches as $b) {
            DB::table('cash_accounts')->insert([
                'name' => $b->name . ' — Cash Till', 'code' => 'till', 'type' => 'cash', 'provider' => null,
                'account_number' => null, 'branch_id' => $b->id,
                'opening_balance' => 0, 'opening_date' => $now->toDateString(), 'current_balance' => 0,
                'is_active' => true, 'sort_order' => $sort, 'color' => '#4794FF',
                'notes' => 'Daily till for branch ' . $b->name . '. Top up + close out daily.',
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $sort += 10;
        }
    }

    private function reseedDepartments(Carbon $now): void
    {
        $rows = [
            ['Kitchen',     'kitchen',     '#E84D4F', 10, 'Food preparation — chefs, line cooks, commis, stewards.'],
            ['Service',     'service',     '#4794FF', 20, 'Floor staff — captains, waiters, hostesses.'],
            ['Bar',         'bar',         '#9B59B6', 30, 'Bartenders, beverage runners.'],
            ['Accounts',    'accounts',    '#1E8E3E', 40, 'Cashiers, accountants, billing.'],
            ['Management',  'management',  '#E67E22', 50, 'Branch managers, supervisors, HR.'],
            ['Cleaning',    'cleaning',    '#16A085', 60, 'Housekeeping, dishwashing.'],
            ['Security',    'security',    '#34495E', 70, 'Guards, valet, gate.'],
        ];
        foreach ($rows as [$name, $code, $color, $order, $desc]) {
            DB::table('departments')->insert([
                'name' => $name, 'code' => $code, 'branch_id' => null,
                'color' => $color, 'is_active' => true, 'sort_order' => $order,
                'description' => $desc, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    private function reseedDesignations(Carbon $now): void
    {
        $deptIdByCode = DB::table('departments')->pluck('id', 'code')->toArray();
        $titles = [
            ['Head Chef',         'head_chef',         'kitchen',    50000, 'A1'],
            ['Sous Chef',         'sous_chef',         'kitchen',    35000, 'A2'],
            ['Line Cook',         'line_cook',         'kitchen',    18000, 'B1'],
            ['Commis',            'commis',            'kitchen',    13000, 'B2'],
            ['Steward',           'steward',           'kitchen',    10500, 'C1'],
            ['Restaurant Manager','rest_manager',      'service',    45000, 'A1'],
            ['Captain',           'captain',           'service',    18000, 'B1'],
            ['Waiter',            'waiter',            'service',    12000, 'C1'],
            ['Hostess',           'hostess',           'service',    14000, 'C1'],
            ['Busboy',            'busboy',            'service',    10000, 'C2'],
            ['Head Bartender',    'head_bartender',    'bar',        30000, 'A2'],
            ['Bartender',         'bartender',         'bar',        16000, 'B1'],
            ['Accountant',        'accountant',        'accounts',   25000, 'B1'],
            ['Cashier',           'cashier',           'accounts',   15000, 'C1'],
            ['Branch Manager',    'branch_manager',    'management', 60000, 'A1'],
            ['Assistant Manager', 'asst_manager',      'management', 35000, 'A2'],
            ['HR Officer',        'hr_officer',        'management', 28000, 'B1'],
            ['Cleaner',           'cleaner',           'cleaning',   9500,  'D1'],
            ['Dishwasher',        'dishwasher',        'cleaning',   9500,  'D1'],
            ['Security Guard',    'security_guard',    'security',   11000, 'C2'],
        ];
        $sort = 10;
        foreach ($titles as [$name, $code, $deptCode, $basic, $grade]) {
            DB::table('designations')->insert([
                'name' => $name, 'code' => $code,
                'department_id' => $deptIdByCode[$deptCode] ?? null,
                'default_basic' => $basic, 'grade' => $grade,
                'is_active' => true, 'sort_order' => $sort,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $sort += 10;
        }
    }

    private function reseedHrmSettings(Carbon $now): void
    {
        $rows = [
            ['gratuity_min_years',       '5',     'int',     'gratuity', 'Gratuity — minimum years of service', 'Sec 2(10) BD Labour Act: gratuity is wages of at least 30 days for every completed year of service after 5 years of continuous service. Lower it for a more generous policy.', null, 10],
            ['gratuity_days_per_year',   '30',    'int',     'gratuity', 'Gratuity — days of basic per year of service', '30 = one month\'s basic per year (BD statutory minimum).', null, 20],
            ['gratuity_basis',           'basic', 'enum',    'gratuity', 'Gratuity — calculation basis', 'Basic salary or gross. Statute says basic; some restaurants use gross to be more generous.', json_encode(['basic' => 'Basic only', 'gross' => 'Gross (basic + allowances)']), 30],
            ['ot_multiplier',            '2.0',   'decimal', 'overtime', 'Overtime rate multiplier', 'Sec 108 BD Labour Act: OT must be paid at not less than 2× ordinary rate. Setting <2.0 means non-compliant; system warns but allows.', null, 10],
            ['ot_holiday_multiplier',    '2.0',   'decimal', 'overtime', 'Holiday/weekly-off OT multiplier', 'Some operators pay extra (2.5×) for work on weekly off-day or festival. Default 2.0×.', null, 20],
            ['probation_default_months', '6',     'int',     'probation', 'Default probation period (months)', 'Sec 4 BD Labour Act: max 6 months for permanent workers (3+3 extension allowed for clerical).', null, 10],
            ['standard_daily_hours',     '8',     'int',     'working_time', 'Standard daily hours', 'Sec 100: max 8 hrs ordinary per day; OT beyond this.', null, 10],
            ['standard_weekly_hours',    '48',    'int',     'working_time', 'Standard weekly hours', 'Sec 102: max 48 hrs ordinary per week.', null, 20],
            ['weekly_off_day',           'friday','enum',    'working_time', 'Weekly off-day', 'Sec 103: at least 1.5 days weekly rest. Default Friday for BD restaurants.', json_encode(['friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday']), 30],
            ['tip_share_pct',            '0',     'decimal', 'tips', 'Service-charge tip share (% of monthly tips pooled)', 'How much of monthly service charge / tip pool flows into staff payroll. Set 0 to keep tips out of payroll. Distribution = days_clocked share.', null, 10],
        ];
        foreach ($rows as [$key, $value, $type, $group, $label, $help, $options, $order]) {
            DB::table('hrm_settings')->insert([
                'key' => $key, 'value' => $value, 'type' => $type, 'group' => $group,
                'label' => $label, 'help_text' => $help, 'options' => $options,
                'sort_order' => $order, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    private function reseedLeaveTypes(Carbon $now): void
    {
        $rows = [
            ['Casual',    'casual',    10,  true,  '#4794FF', 10],
            ['Sick',      'sick',      14,  true,  '#E84D4F', 20],
            ['Annual',    'annual',    20,  true,  '#1E8E3E', 30],
            ['Festival',  'festival',  11,  true,  '#E67E22', 40],
            ['Maternity', 'maternity', 112, true,  '#C548A8', 50],
            ['Unpaid',    'unpaid',    0,   false, '#6A6A70', 90],
        ];
        foreach ($rows as [$name, $code, $days, $paid, $color, $order]) {
            DB::table('leave_types')->insert([
                'name' => $name, 'code' => $code, 'days_per_year' => $days, 'is_paid' => $paid,
                'color' => $color, 'is_active' => true, 'sort_order' => $order,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    private function reseedSalaryComponents(Carbon $now): void
    {
        // Mirrors the 2026_05_01_180000 migration + the default_pct backfill
        // from 2026_05_01_300000 (BD-standard 60/30/5/5 split).
        $rows = [
            ['Basic',               'allowance', false, true, 10, 60],
            ['House Rent',          'allowance', false, true, 20, 30],
            ['Medical Allowance',   'allowance', false, true, 25, 5],
            ['Transport Allowance', 'allowance', false, true, 30, 5],
            ['Food Allowance',      'allowance', false, true, 40, null],
            ['Mobile Allowance',    'allowance', false, true, 50, null],
            ['Service Charge',      'allowance', false, true, 60, null],
            ['General Allowance',   'allowance', false, true, 70, null],
            ['Income Tax',          'deduction', false, true, 80, null],
            ['Provident Fund',      'deduction', false, true, 90, null],
            ['Advance Recovery',    'deduction', false, true, 100, null],
            ['Other Deduction',     'deduction', false, true, 110, null],
        ];
        foreach ($rows as [$name, $type, $taxable, $active, $sort, $pct]) {
            DB::table('salary_components')->insert([
                'name' => $name, 'type' => $type, 'is_taxable' => $taxable,
                'is_active' => $active, 'sort_order' => $sort, 'default_pct' => $pct,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    private function reseedExpenseCategories(Carbon $now): void
    {
        $tops = [
            ['Rent',         'rent',         '#9B59B6', 10, 'Monthly building / branch rent.'],
            ['Utilities',    'utilities',    '#4794FF', 20, 'Electricity, water, gas, internet — separate sub-categories below.'],
            ['Fuel',         'fuel',         '#E84D4F', 30, 'Generator diesel / petrol, vehicle fuel.'],
            ['Raw Materials','raw_materials','#1E8E3E', 40, 'Meat, fish, vegetables, rice, oil — anything that goes into food.'],
            ['Consumables',  'consumables',  '#16A085', 50, 'Napkins, packaging, takeaway boxes, dishwash liquid.'],
            ['Maintenance',  'maintenance',  '#E67E22', 60, 'AC servicing, fridge repair, equipment fixes.'],
            ['Cleaning',     'cleaning',     '#34495E', 70, 'Cleaning supplies, pest control, deep clean services.'],
            ['Licenses',     'licenses',     '#C0392B', 80, 'Trade license, food license, BSTI, NBR, fire safety.'],
            ['Marketing',    'marketing',    '#E91E63', 90, 'Foodpanda commission, ads, banner printing.'],
            ['Office',       'office',       '#607D8B', 100, 'Stationery, printer ink, software subscriptions.'],
            ['Transport',    'transport',    '#FFC107', 110, 'Vegetable runs, delivery rider top-up, local transport.'],
            ['Misc',         'misc',         '#6A6A70', 200, 'Anything that doesn\'t fit elsewhere — keep light.'],
        ];
        $idByCode = [];
        foreach ($tops as [$name, $code, $color, $order, $desc]) {
            $idByCode[$code] = DB::table('expense_categories')->insertGetId([
                'name' => $name, 'code' => $code, 'parent_id' => null,
                'color' => $color, 'is_active' => true, 'sort_order' => $order,
                'description' => $desc, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $subs = [
            ['Electricity', 'utilities_electricity', 'utilities', 21, 'PDB / DESCO / Palli Bidyut bills.'],
            ['Water',       'utilities_water',       'utilities', 22, 'WASA / city water bills.'],
            ['Gas',         'utilities_gas',         'utilities', 23, 'Titas pipeline gas + cylinder LPG (when not generator fuel).'],
            ['Internet',    'utilities_internet',    'utilities', 24, 'ISP bills.'],
            ['Phone',       'utilities_phone',       'utilities', 25, 'Branch landline / SMS gateway top-up.'],
        ];
        foreach ($subs as [$name, $code, $parentCode, $order, $desc]) {
            DB::table('expense_categories')->insert([
                'name' => $name, 'code' => $code,
                'parent_id' => $idByCode[$parentCode] ?? null,
                'color' => '#4794FF', 'is_active' => true,
                'sort_order' => $order, 'description' => $desc,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }
}
