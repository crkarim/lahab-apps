<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HRM Phase 6 — Org structure + tunable HR settings.
 *
 * Three new master tables:
 *   - `departments`    — Kitchen / Service / Bar / Accounts / Management.
 *                        Branch-scoped (branch_id null = HQ-wide).
 *                        head_admin_id = department head (org chart label;
 *                        not the leave approver — that's reports_to).
 *   - `designations`   — Job titles (Head Chef, Waiter, Captain, ...) with
 *                        an optional default_basic to seed pay grade math.
 *                        FK to department (loose: nullable so a designation
 *                        can be branch-agnostic / cross-department).
 *   - `hrm_settings`   — Single source of truth for tunable HR numbers
 *                        (gratuity, OT multiplier, probation default, etc).
 *                        key/value with a type hint so Blade can render
 *                        the right input.
 *
 * Admin extension:
 *   - department_id        — FK to departments
 *   - designation_id       — FK to designations (the legacy `designation`
 *                            string column stays, populated when a row is
 *                            picked, so old code that reads it still works)
 *   - reports_to_admin_id  — self-FK; drives leave approval routing.
 *                            Same-branch enforced at app layer.
 *
 * All seeds are starting points — every row is editable, deactivatable,
 * or addable from the admin UI. Compliance numbers all live in
 * hrm_settings; PayrollSummariser + future gratuity calc read them at
 * request time, never from constants.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('code', 32);
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('head_admin_id')->nullable()->index();
            $table->string('color', 16)->default('#6A6A70');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['code', 'branch_id']); // same code allowed across branches
        });

        Schema::create('designations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('code', 32);
            $table->unsignedBigInteger('department_id')->nullable()->index();
            // Pay-grade hints — UI shows them when picked so a manager can
            // remember "Captain typically gets 18k basic". Not authoritative;
            // actual salary still lives in admin_salary_lines.
            $table->decimal('default_basic', 12, 2)->nullable();
            $table->string('grade', 16)->nullable(); // 'A1', 'B2', etc.
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique('code');
        });

        Schema::create('hrm_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->text('value')->nullable();
            // type drives the Blade input renderer. 'enum' uses options column.
            $table->enum('type', ['string', 'int', 'decimal', 'bool', 'enum'])->default('string');
            $table->string('group', 40)->default('general'); // gratuity / overtime / probation / etc
            $table->string('label', 120);
            $table->text('help_text')->nullable();
            $table->text('options')->nullable(); // JSON array for enum
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Admin extensions — three new optional FKs.
        Schema::table('admins', function (Blueprint $table) {
            if (!Schema::hasColumn('admins', 'department_id')) {
                $table->unsignedBigInteger('department_id')->nullable()->after('branch_id')->index();
            }
            if (!Schema::hasColumn('admins', 'designation_id')) {
                $table->unsignedBigInteger('designation_id')->nullable()->after('department_id')->index();
            }
            if (!Schema::hasColumn('admins', 'reports_to_admin_id')) {
                $table->unsignedBigInteger('reports_to_admin_id')->nullable()->after('designation_id')->index();
            }
        });

        // -------- Seeds --------
        $now = now();

        // Departments — BD restaurant defaults. branch_id NULL = HQ-wide
        // (every branch inherits unless overridden).
        $depts = [
            ['name' => 'Kitchen',     'code' => 'kitchen',     'color' => '#E84D4F', 'order' => 10, 'desc' => 'Food preparation — chefs, line cooks, commis, stewards.'],
            ['name' => 'Service',     'code' => 'service',     'color' => '#4794FF', 'order' => 20, 'desc' => 'Floor staff — captains, waiters, hostesses.'],
            ['name' => 'Bar',         'code' => 'bar',         'color' => '#9B59B6', 'order' => 30, 'desc' => 'Bartenders, beverage runners.'],
            ['name' => 'Accounts',    'code' => 'accounts',    'color' => '#1E8E3E', 'order' => 40, 'desc' => 'Cashiers, accountants, billing.'],
            ['name' => 'Management',  'code' => 'management',  'color' => '#E67E22', 'order' => 50, 'desc' => 'Branch managers, supervisors, HR.'],
            ['name' => 'Cleaning',    'code' => 'cleaning',    'color' => '#16A085', 'order' => 60, 'desc' => 'Housekeeping, dishwashing.'],
            ['name' => 'Security',    'code' => 'security',    'color' => '#34495E', 'order' => 70, 'desc' => 'Guards, valet, gate.'],
        ];
        $deptIdByCode = [];
        foreach ($depts as $d) {
            $id = DB::table('departments')->insertGetId([
                'name'        => $d['name'],
                'code'        => $d['code'],
                'branch_id'   => null,
                'color'       => $d['color'],
                'is_active'   => true,
                'sort_order'  => $d['order'],
                'description' => $d['desc'],
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
            $deptIdByCode[$d['code']] = $id;
        }

        // Designations — typical BD restaurant job titles, FK'd to dept.
        // default_basic = ballpark monthly basic (Tk) for smoke; editable.
        $titles = [
            // Kitchen
            ['name' => 'Head Chef',        'code' => 'head_chef',        'dept' => 'kitchen',    'basic' => 50000, 'grade' => 'A1'],
            ['name' => 'Sous Chef',        'code' => 'sous_chef',        'dept' => 'kitchen',    'basic' => 35000, 'grade' => 'A2'],
            ['name' => 'Line Cook',        'code' => 'line_cook',        'dept' => 'kitchen',    'basic' => 18000, 'grade' => 'B1'],
            ['name' => 'Commis',           'code' => 'commis',           'dept' => 'kitchen',    'basic' => 13000, 'grade' => 'B2'],
            ['name' => 'Steward',          'code' => 'steward',          'dept' => 'kitchen',    'basic' => 10500, 'grade' => 'C1'],
            // Service
            ['name' => 'Restaurant Manager','code' => 'rest_manager',    'dept' => 'service',    'basic' => 45000, 'grade' => 'A1'],
            ['name' => 'Captain',          'code' => 'captain',          'dept' => 'service',    'basic' => 18000, 'grade' => 'B1'],
            ['name' => 'Waiter',           'code' => 'waiter',           'dept' => 'service',    'basic' => 12000, 'grade' => 'C1'],
            ['name' => 'Hostess',          'code' => 'hostess',          'dept' => 'service',    'basic' => 14000, 'grade' => 'C1'],
            ['name' => 'Busboy',           'code' => 'busboy',           'dept' => 'service',    'basic' => 10000, 'grade' => 'C2'],
            // Bar
            ['name' => 'Head Bartender',   'code' => 'head_bartender',   'dept' => 'bar',        'basic' => 30000, 'grade' => 'A2'],
            ['name' => 'Bartender',        'code' => 'bartender',        'dept' => 'bar',        'basic' => 16000, 'grade' => 'B1'],
            // Accounts
            ['name' => 'Accountant',       'code' => 'accountant',       'dept' => 'accounts',   'basic' => 25000, 'grade' => 'B1'],
            ['name' => 'Cashier',          'code' => 'cashier',          'dept' => 'accounts',   'basic' => 15000, 'grade' => 'C1'],
            // Management
            ['name' => 'Branch Manager',   'code' => 'branch_manager',   'dept' => 'management', 'basic' => 60000, 'grade' => 'A1'],
            ['name' => 'Assistant Manager','code' => 'asst_manager',     'dept' => 'management', 'basic' => 35000, 'grade' => 'A2'],
            ['name' => 'HR Officer',       'code' => 'hr_officer',       'dept' => 'management', 'basic' => 28000, 'grade' => 'B1'],
            // Cleaning
            ['name' => 'Cleaner',          'code' => 'cleaner',          'dept' => 'cleaning',   'basic' => 9500,  'grade' => 'D1'],
            ['name' => 'Dishwasher',       'code' => 'dishwasher',       'dept' => 'cleaning',   'basic' => 9500,  'grade' => 'D1'],
            // Security
            ['name' => 'Security Guard',   'code' => 'security_guard',   'dept' => 'security',   'basic' => 11000, 'grade' => 'C2'],
        ];
        $sort = 10;
        foreach ($titles as $t) {
            DB::table('designations')->insert([
                'name'          => $t['name'],
                'code'          => $t['code'],
                'department_id' => $deptIdByCode[$t['dept']] ?? null,
                'default_basic' => $t['basic'],
                'grade'         => $t['grade'],
                'is_active'     => true,
                'sort_order'    => $sort,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
            $sort += 10;
        }

        // HRM settings — every compliance number tunable from the UI.
        // BD Labour Act 2006 defaults seeded; "label" is what the form
        // shows; help_text inlines the section reference so HR doesn't
        // have to keep the act open.
        $settings = [
            // Gratuity (Sec 2(10) + Sec 26-27)
            ['key' => 'gratuity_min_years',       'value' => '5',     'type' => 'int',     'group' => 'gratuity', 'label' => 'Gratuity — minimum years of service',
             'help' => 'Sec 2(10) BD Labour Act: gratuity is wages of at least 30 days for every completed year of service after 5 years of continuous service. Lower it for a more generous policy.', 'options' => null, 'order' => 10],
            ['key' => 'gratuity_days_per_year',   'value' => '30',    'type' => 'int',     'group' => 'gratuity', 'label' => 'Gratuity — days of basic per year of service',
             'help' => '30 = one month\'s basic per year (BD statutory minimum).', 'options' => null, 'order' => 20],
            ['key' => 'gratuity_basis',           'value' => 'basic', 'type' => 'enum',    'group' => 'gratuity', 'label' => 'Gratuity — calculation basis',
             'help' => 'Basic salary or gross. Statute says basic; some restaurants use gross to be more generous.', 'options' => json_encode(['basic' => 'Basic only', 'gross' => 'Gross (basic + allowances)']), 'order' => 30],

            // Overtime (Sec 108)
            ['key' => 'ot_multiplier',            'value' => '2.0',   'type' => 'decimal', 'group' => 'overtime', 'label' => 'Overtime rate multiplier',
             'help' => 'Sec 108 BD Labour Act: OT must be paid at not less than 2× ordinary rate. Setting <2.0 means non-compliant; system warns but allows.', 'options' => null, 'order' => 10],
            ['key' => 'ot_holiday_multiplier',    'value' => '2.0',   'type' => 'decimal', 'group' => 'overtime', 'label' => 'Holiday/weekly-off OT multiplier',
             'help' => 'Some operators pay extra (2.5×) for work on weekly off-day or festival. Default 2.0×.', 'options' => null, 'order' => 20],

            // Probation
            ['key' => 'probation_default_months', 'value' => '6',     'type' => 'int',     'group' => 'probation', 'label' => 'Default probation period (months)',
             'help' => 'Sec 4 BD Labour Act: max 6 months for permanent workers (3+3 extension allowed for clerical).', 'options' => null, 'order' => 10],

            // Working time (Sec 100, 102)
            ['key' => 'standard_daily_hours',     'value' => '8',     'type' => 'int',     'group' => 'working_time', 'label' => 'Standard daily hours',
             'help' => 'Sec 100: max 8 hrs ordinary per day; OT beyond this.', 'options' => null, 'order' => 10],
            ['key' => 'standard_weekly_hours',    'value' => '48',    'type' => 'int',     'group' => 'working_time', 'label' => 'Standard weekly hours',
             'help' => 'Sec 102: max 48 hrs ordinary per week.', 'options' => null, 'order' => 20],
            ['key' => 'weekly_off_day',           'value' => 'friday','type' => 'enum',    'group' => 'working_time', 'label' => 'Weekly off-day',
             'help' => 'Sec 103: at least 1.5 days weekly rest. Default Friday for BD restaurants.', 'options' => json_encode(['friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday']), 'order' => 30],

            // Tip share (already in PayrollSummariser; surface for editing)
            ['key' => 'tip_share_pct',            'value' => '0',     'type' => 'decimal', 'group' => 'tips',     'label' => 'Service-charge tip share (% of monthly tips pooled)',
             'help' => 'How much of monthly service charge / tip pool flows into staff payroll. Set 0 to keep tips out of payroll. Distribution = days_clocked share.', 'options' => null, 'order' => 10],
        ];
        foreach ($settings as $s) {
            DB::table('hrm_settings')->insert([
                'key'        => $s['key'],
                'value'      => $s['value'],
                'type'       => $s['type'],
                'group'      => $s['group'],
                'label'      => $s['label'],
                'help_text'  => $s['help'],
                'options'    => $s['options'],
                'sort_order' => $s['order'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            if (Schema::hasColumn('admins', 'reports_to_admin_id')) {
                $table->dropIndex(['reports_to_admin_id']);
                $table->dropColumn('reports_to_admin_id');
            }
            if (Schema::hasColumn('admins', 'designation_id')) {
                $table->dropIndex(['designation_id']);
                $table->dropColumn('designation_id');
            }
            if (Schema::hasColumn('admins', 'department_id')) {
                $table->dropIndex(['department_id']);
                $table->dropColumn('department_id');
            }
        });
        Schema::dropIfExists('hrm_settings');
        Schema::dropIfExists('designations');
        Schema::dropIfExists('departments');
    }
};
