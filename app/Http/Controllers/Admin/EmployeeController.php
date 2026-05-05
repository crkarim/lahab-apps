<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\Admin;
use App\Model\AdminRole;
use App\Traits\UploadSizeHelperTrait;
use Box\Spout\Common\Exception\InvalidArgumentException;
use Box\Spout\Common\Exception\IOException;
use Box\Spout\Common\Exception\UnsupportedTypeException;
use Box\Spout\Writer\Exception\WriterNotOpenedException;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Rap2hpoutre\FastExcel\FastExcel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\Support\Renderable;
use Symfony\Component\HttpFoundation\StreamedResponse;


class EmployeeController extends Controller
{
    use UploadSizeHelperTrait;

    public function __construct(
        private Admin     $admin,
        private AdminRole $admin_role
    ){}

    /**
     * @return Renderable
     */
    public function index(): Renderable
    {
        $roles = $this->admin_role->whereNotIn('id', [1])->get();
        $branches = \App\Model\Branch::query()->orderBy('name')->get(['id', 'name']);
        // HRM Phase 6 — org structure pickers. Designations are global;
        // departments scoped to viewer's branch + HQ-wide; reports-to
        // pool is everyone (filtered same-branch in JS once a branch
        // is picked, so HQ admins can switch).
        $departments  = \App\Models\Department::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $designations = \App\Models\Designation::active();
        $managers = $this->admin->query()
            ->where('status', 1)
            ->orderBy('f_name')
            ->get(['id', 'f_name', 'l_name', 'employee_code', 'branch_id', 'designation']);
        return view('admin-views.employee.add-new', compact('roles', 'branches', 'departments', 'designations', 'managers'));
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $check = $this->validateUploadedFile($request, ['image']);
        if ($check !== true) {
            return $check;
        }

        $request->validate([
            'name' => 'required',
            'role_id' => 'required',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'image' => 'required|image|max:'. $this->maxImageSizeKB .'|mimes:' . implode(',', array_column(IMAGEEXTENSION, 'key')),
            'email' => 'required|email|unique:admins',
            'password' => 'required',
            'phone' => 'required',
            'identity_image' => 'required',
            'identity_type' => 'required',
            'identity_number' => 'required',
            'confirm_password' => 'same:password',
            // HRM Phase 2 fields — all optional. Salary stored as decimals
            // so we can compute payroll later without re-parsing strings.
            // employee_code is unique so two staff can't share the same
            // biometric device User ID (would split attendance between them).
            'employee_code'           => 'nullable|string|max:20|unique:admins,employee_code',
            'joining_date'            => 'nullable|date',
            'designation'             => 'nullable|string|max:100',
            'employment_type'         => 'nullable|in:full_time,part_time,contract,intern',
            'emergency_contact_name'  => 'nullable|string|max:120',
            'emergency_contact_phone' => 'nullable|string|max:30',
            // HRM Phase 6 — org structure FKs.
            'department_id'           => 'nullable|integer|exists:departments,id',
            'designation_id'          => 'nullable|integer|exists:designations,id',
            'reports_to_admin_id'     => 'nullable|integer|exists:admins,id',
            // HRM Phase 7a — disbursement details. Required block toggles
            // by payment_method; UI hides the others. Bank routing for BD
            // is the 9-digit code; mobile wallets are 11-digit MFS numbers.
            'payment_method'          => 'nullable|in:cash,bank,mobile,cheque',
            'bank_name'               => 'nullable|string|max:80',
            'bank_branch'             => 'nullable|string|max:80',
            'bank_account_name'       => 'nullable|string|max:120',
            'bank_account_number'     => 'nullable|string|max:40',
            'bank_routing_number'     => 'nullable|string|max:20',
            'mobile_provider'         => 'nullable|in:bkash,nagad,rocket,upay',
            'mobile_wallet_number'    => 'nullable|string|max:20',
            // Phase 4.1 line-item salary — array keyed by component_id.
            'salary_lines'            => 'nullable|array',
            'salary_lines.*'          => 'nullable|numeric|min:0',
        ], [
            'name.required' => translate('Role name is required!'),
            'role_name.required' => translate('Role id is Required'),
            'email.required' => translate('Email id is Required'),
            'image.required' => translate('Image is Required'),

        ]);

        if ($request->role_id == 1) {
            Toastr::warning(translate('Access Denied!'));
            return back();
        }

        $identityImageNames = [];
        if (!empty($request->file('identity_image'))) {
            foreach ($request->identity_image as $img) {
                $identityImageNames[] = Helpers::upload('admin/', APPLICATION_IMAGE_FORMAT, $img);
            }
            $identityImage = json_encode($identityImageNames);
        } else {
            $identityImage = json_encode([]);
        }

        // Insert and capture the new id so we can write salary lines.
        $newAdminId = $this->admin->insertGetId([
            'f_name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'admin_role_id' => $request->role_id,
            // Branch scoping for floor staff (waiters, branch managers).
            // NULL = HQ-wide account that sees all branches.
            'branch_id' => $request->filled('branch_id') ? (int) $request->branch_id : null,
            'identity_number' => $request->identity_number,
            'identity_type' => $request->identity_type,
            'identity_image' => $identityImage,
            'password' => bcrypt($request->password),
            'status' => 1,
            'image' => Helpers::upload('admin/', APPLICATION_IMAGE_FORMAT, $request->file('image')),
            // HRM fields. employee_code stays null when blank so the
            // unique index allows multiple unset rows.
            'employee_code'           => $request->filled('employee_code') ? $request->employee_code : null,
            'joining_date'            => $request->joining_date ?: null,
            // Free-text designation kept in sync with the picked designation
            // for back-compat with code that reads admins.designation directly.
            'designation'             => $this->resolveDesignationLabel($request),
            'employment_type'         => $request->employment_type ?: 'full_time',
            'emergency_contact_name'  => $request->emergency_contact_name,
            'emergency_contact_phone' => $request->emergency_contact_phone,
            'department_id'           => $request->filled('department_id') ? (int) $request->department_id : null,
            'designation_id'          => $request->filled('designation_id') ? (int) $request->designation_id : null,
            'reports_to_admin_id'     => $request->filled('reports_to_admin_id') ? (int) $request->reports_to_admin_id : null,
            // Disbursement — branch-blank fields when method doesn't apply
            // so we don't leave stale bank info on a cash-paid employee.
            'payment_method'          => $request->payment_method ?: 'cash',
            'bank_name'                  => $request->payment_method === 'bank' ? $request->bank_name : null,
            'bank_branch'                => $request->payment_method === 'bank' ? $request->bank_branch : null,
            'bank_account_name'          => $request->payment_method === 'bank' ? $request->bank_account_name : null,
            'bank_account_number'        => $request->payment_method === 'bank' ? $request->bank_account_number : null,
            'bank_routing_number'        => $request->payment_method === 'bank' ? $request->bank_routing_number : null,
            'mobile_provider'            => $request->payment_method === 'mobile' ? $request->mobile_provider : null,
            'mobile_wallet_number'       => $request->payment_method === 'mobile' ? $request->mobile_wallet_number : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Save line-item salary structure. Skip empty/zero rows so we
        // don't fill the table with noise. Existing rows for this
        // employee are wiped first (insert flow == fresh employee).
        $this->saveSalaryLines((int) $newAdminId, (array) $request->input('salary_lines', []));

        // Maintain the legacy admins.salary_basic / salary_allowance
        // columns as denormalised rollups so older code that reads
        // them stays correct. Values come from the freshly-saved lines.
        $this->syncLegacySalaryColumns((int) $newAdminId);

        Toastr::success(translate('Employee added successfully!'));
        return redirect()->route('admin.employee.list');
    }

    /**
     * @param Request $request
     * @return Renderable
     */
    function list(Request $request): Renderable
    {
        $search = $request['search'];
        $key = explode(' ', $request['search']);

        $query = $this->admin->with(['role', 'branch'])
            ->when($search != null, function ($query) use ($key) {
                $query->whereNotIn('id', [1])->where(function ($query) use ($key) {
                    foreach ($key as $value) {
                        $query->where('f_name', 'like', "%{$value}%")
                            ->orWhere('phone', 'like', "%{$value}%")
                            ->orWhere('email', 'like', "%{$value}%");
                    }
                });
            }, function ($query) {
                $query->whereNotIn('id', [1]);
            });

        $employees = $query->paginate(Helpers::getPagination());

        return view('admin-views.employee.list', compact('employees', 'search'));
    }

    /**
     * @param $id
     * @return Renderable
     */
    public function edit($id): Renderable
    {
        $employee = $this->admin->where(['id' => $id])->first();
        $roles = $this->admin_role->whereNotIn('id', [1])->get();
        $branches = \App\Model\Branch::query()->orderBy('name')->get(['id', 'name']);
        $departments  = \App\Models\Department::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $designations = \App\Models\Designation::active();
        // Reports-to pool excludes self + descendants (would create a cycle).
        $descendantIds = $this->collectDescendantIds((int) $id);
        $managers = $this->admin->query()
            ->where('status', 1)
            ->where('id', '!=', $id)
            ->whereNotIn('id', $descendantIds)
            ->orderBy('f_name')
            ->get(['id', 'f_name', 'l_name', 'employee_code', 'branch_id', 'designation']);
        return view('admin-views.employee.edit', compact('roles', 'employee', 'branches', 'departments', 'designations', 'managers'));
    }

    /**
     * Pick the right value for the legacy `admins.designation` string column.
     * If the form submitted a designation_id, resolve it to that title's name
     * (so old code that reads admins.designation still gets a sensible label).
     * Otherwise fall back to the manually-typed string, then the existing value.
     */
    private function resolveDesignationLabel(\Illuminate\Http\Request $request, ?string $current = null): ?string
    {
        if ($request->filled('designation_id')) {
            $des = \App\Models\Designation::find((int) $request->designation_id);
            if ($des) return $des->name;
        }
        $typed = $request->input('designation');
        if ($typed !== null && $typed !== '') return $typed;
        return $current;
    }

    /**
     * Collect all transitive direct-report ids of an admin so the
     * Reports-to picker can exclude them (preventing A → B → A cycles).
     * Iterative BFS to keep recursion bounded.
     */
    private function collectDescendantIds(int $rootId): array
    {
        $found = [];
        $queue = [$rootId];
        while ($queue) {
            $current = array_shift($queue);
            $kids = $this->admin->query()
                ->where('reports_to_admin_id', $current)
                ->pluck('id')
                ->all();
            foreach ($kids as $k) {
                if (!in_array($k, $found, true)) {
                    $found[] = $k;
                    $queue[] = $k;
                }
            }
        }
        return $found;
    }

    /**
     * @param Request $request
     * @param $id
     * @return RedirectResponse
     */
    public function update(Request $request, $id): RedirectResponse
    {
        $check = $this->validateUploadedFile($request, ['image']);
        if ($check !== true) {
            return $check;
        }

        $request->validate([
            'name' => 'required',
            'image' => 'image|max:'. $this->maxImageSizeKB .'|mimes:' . implode(',', array_column(IMAGEEXTENSION, 'key')),
            'role_id' => 'required',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'email' => 'required|email|unique:admins,email,' . $id,
            'phone' => 'required',
            'identity_type' => 'required',
            'identity_number' => 'required',
            // HRM fields — employee_code unique EXCEPT for this row.
            'employee_code'           => 'nullable|string|max:20|unique:admins,employee_code,' . $id,
            'joining_date'            => 'nullable|date',
            'designation'             => 'nullable|string|max:100',
            'employment_type'         => 'nullable|in:full_time,part_time,contract,intern',
            'emergency_contact_name'  => 'nullable|string|max:120',
            'emergency_contact_phone' => 'nullable|string|max:30',
            // HRM Phase 6 — org structure FKs.
            'department_id'           => 'nullable|integer|exists:departments,id',
            'designation_id'          => 'nullable|integer|exists:designations,id',
            'reports_to_admin_id'     => 'nullable|integer|exists:admins,id',
            // HRM Phase 7a — disbursement details. Required block toggles
            // by payment_method; UI hides the others. Bank routing for BD
            // is the 9-digit code; mobile wallets are 11-digit MFS numbers.
            'payment_method'          => 'nullable|in:cash,bank,mobile,cheque',
            'bank_name'               => 'nullable|string|max:80',
            'bank_branch'             => 'nullable|string|max:80',
            'bank_account_name'       => 'nullable|string|max:120',
            'bank_account_number'     => 'nullable|string|max:40',
            'bank_routing_number'     => 'nullable|string|max:20',
            'mobile_provider'         => 'nullable|in:bkash,nagad,rocket,upay',
            'mobile_wallet_number'    => 'nullable|string|max:20',
            // Phase 4.1 line-item salary.
            'salary_lines'            => 'nullable|array',
            'salary_lines.*'          => 'nullable|numeric|min:0',
        ], [
            'name.required' => translate('Role name is required!'),
        ]);

        // Reports-to cycle guard — picked manager can't be self or any descendant.
        $rt = $request->input('reports_to_admin_id');
        if ($rt) {
            if ((int) $rt === (int) $id) {
                return back()->withErrors(['reports_to_admin_id' => translate('An employee cannot report to themselves.')])->withInput();
            }
            $descendants = $this->collectDescendantIds((int) $id);
            if (in_array((int) $rt, $descendants, true)) {
                return back()->withErrors(['reports_to_admin_id' => translate('Pick a manager outside this employee\'s direct/indirect reports — would create a cycle.')])->withInput();
            }
        }

        if ($request->role_id == 1) {
            Toastr::warning(translate('Access Denied!'));
            return back();
        }

        $employee = $this->admin->find($id);
        $identityImage = $employee['identity_image'];

        if ($request['password'] == null) {
            $password = $employee['password'];
        } else {
            $request->validate([
                'confirm_password' => 'same:password'
            ]);
            if (strlen($request['password']) < 7) {
                Toastr::warning(translate('Password length must be 8 character.'));
                return back();
            }
            $password = bcrypt($request['password']);
        }

        if ($request->has('image')) {
            $employee['image'] = Helpers::update('admin/', $employee['image'], APPLICATION_IMAGE_FORMAT, $request->file('image'));
        }

        $identityImageNames = [];
        if (!empty($request->file('identity_image'))) {
            foreach ($request->identity_image as $img) {
                $identityImageNames[] = Helpers::upload('admin/', APPLICATION_IMAGE_FORMAT, $img);
            }
            $identityImage = json_encode($identityImageNames);
        }

        $this->admin->where(['id' => $id])->update([
            'f_name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'admin_role_id' => $request->role_id,
            'branch_id' => $request->filled('branch_id') ? (int) $request->branch_id : null,
            'password' => $password,
            'image' => $employee['image'],
            'updated_at' => now(),
            'identity_number' => $request->identity_number,
            'identity_type' => $request->identity_type,
            'identity_image' => $identityImage,
            // HRM fields. Empty input → null fallbacks so a
            // half-filled form doesn't wipe meaningful values.
            'employee_code'           => $request->filled('employee_code') ? $request->employee_code : null,
            'joining_date'            => $request->joining_date ?: null,
            'designation'             => $this->resolveDesignationLabel($request, $employee->designation ?? null),
            'employment_type'         => $request->employment_type ?: ($employee->employment_type ?? 'full_time'),
            'emergency_contact_name'  => $request->emergency_contact_name,
            'emergency_contact_phone' => $request->emergency_contact_phone,
            'department_id'           => $request->filled('department_id') ? (int) $request->department_id : null,
            'designation_id'          => $request->filled('designation_id') ? (int) $request->designation_id : null,
            'reports_to_admin_id'     => $request->filled('reports_to_admin_id') ? (int) $request->reports_to_admin_id : null,
            // Disbursement — clear opposite-method fields so we never
            // export stale bank info for an employee who's now on mobile.
            'payment_method'          => $request->payment_method ?: ($employee->payment_method ?? 'cash'),
            'bank_name'               => $request->payment_method === 'bank' ? $request->bank_name : null,
            'bank_branch'             => $request->payment_method === 'bank' ? $request->bank_branch : null,
            'bank_account_name'       => $request->payment_method === 'bank' ? $request->bank_account_name : null,
            'bank_account_number'     => $request->payment_method === 'bank' ? $request->bank_account_number : null,
            'bank_routing_number'     => $request->payment_method === 'bank' ? $request->bank_routing_number : null,
            'mobile_provider'         => $request->payment_method === 'mobile' ? $request->mobile_provider : null,
            'mobile_wallet_number'    => $request->payment_method === 'mobile' ? $request->mobile_wallet_number : null,
            // My Lahab staff app — opt-in toggle. PIN itself is set via the
            // dedicated setAppPin endpoint so we never accept a PIN in this
            // bulk update form (which would risk unintentional PIN wipes).
            'app_login_enabled'       => $request->boolean('app_login_enabled'),
        ]);

        // Refresh salary line items + sync the legacy rollup columns.
        $this->saveSalaryLines((int) $id, (array) $request->input('salary_lines', []));
        $this->syncLegacySalaryColumns((int) $id);

        Toastr::success(translate('Employee updated successfully!'));
        return back();
    }

    /**
     * Set or reset the My Lahab staff app PIN for an employee. Manager
     * picks the PIN inline (4-6 digits) and tells the staff in person.
     * Lives outside the main update() so a half-filled employee form
     * can never accidentally wipe a working PIN.
     */
    public function setAppPin(Request $request, $id): RedirectResponse
    {
        $request->validate([
            'app_pin' => 'required|digits_between:4,6',
        ]);

        $admin = $this->admin->find($id);
        if (! $admin) {
            Toastr::warning(translate('Employee not found.'));
            return back();
        }
        if ((int) $admin->admin_role_id === 1) {
            Toastr::warning(translate('Cannot set staff app PIN for Master Admin.'));
            return back();
        }

        $admin->app_pin_hash = bcrypt($request->input('app_pin'));
        $admin->save();

        Toastr::success(translate('Staff app PIN updated. Tell the employee their new PIN.'));
        return back();
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function status(Request $request): RedirectResponse
    {
        $employee = $this->admin->find($request->id);
        $employee->status = $request->status;
        $employee->save();

        Toastr::success(translate('Employee status updated!'));
        return back();
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function delete(Request $request): RedirectResponse
    {
        if ($request->id == 1) {
            Toastr::warning(translate('Master_Admin_can_not_be_deleted'));

        } else {
            $action = $this->admin->destroy($request->id);
            if ($action) {
                Toastr::success(translate('employee_deleted_successfully'));
            } else {
                Toastr::error(translate('employee_is_not_deleted'));
            }
        }
        return back();
    }

    /**
     * @return string|StreamedResponse
     * @throws IOException
     * @throws InvalidArgumentException
     * @throws UnsupportedTypeException
     * @throws WriterNotOpenedException
     */
    public function exportExcel(): StreamedResponse|string
    {
        $employees = $this->admin
            ->whereNotIn('id', [1])
            ->get(['id', 'f_name', 'l_name', 'email', 'admin_role_id', 'status']);

        return (new FastExcel($employees))->download('employees.xlsx');
    }

    /**
     * Persist salary line items keyed by component_id. Wipes existing
     * lines for the employee then inserts the non-zero ones — simpler
     * than reconciling deltas, and the unique index on
     * (admin_id, component_id) means there's nothing to dedupe.
     */
    private function saveSalaryLines(int $adminId, array $lines): void
    {
        \App\Models\AdminSalaryLine::where('admin_id', $adminId)->delete();
        $rows = [];
        $now = now();
        foreach ($lines as $componentId => $amount) {
            $cId = (int) $componentId;
            $amt = (float) $amount;
            if ($cId <= 0 || $amt <= 0) continue;
            $rows[] = [
                'admin_id'     => $adminId,
                'component_id' => $cId,
                'amount'       => $amt,
                'notes'        => null,
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
        }
        if ($rows) {
            \App\Models\AdminSalaryLine::insert($rows);
        }
    }

    /**
     * Keep the legacy admins.salary_basic + salary_allowance columns
     * in sync with the new line-items so anything that still reads
     * those columns (older code, exports, mobile apps) sees the
     * current rolled-up values.
     */
    private function syncLegacySalaryColumns(int $adminId): void
    {
        $basicId = \App\Models\SalaryComponent::where('name', 'Basic')->value('id');
        $basicAmt = $basicId
            ? (float) \App\Models\AdminSalaryLine::where('admin_id', $adminId)->where('component_id', $basicId)->value('amount')
            : 0.0;

        $allowanceTotal = (float) \App\Models\AdminSalaryLine::query()
            ->where('admin_id', $adminId)
            ->whereHas('component', fn ($q) => $q->where('type', 'allowance'))
            ->where('component_id', '!=', $basicId)
            ->sum('amount');

        $this->admin->where('id', $adminId)->update([
            'salary_basic'     => $basicAmt,
            'salary_allowance' => $allowanceTotal,
        ]);
    }
}
