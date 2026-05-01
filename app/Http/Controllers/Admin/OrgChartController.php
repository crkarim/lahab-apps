<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Model\Admin;
use App\Models\Department;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;

/**
 * HRM Phase 6.2 — Org chart.
 *
 * Read-only tree of: branch → departments → managers → direct reports.
 *
 * Branch managers see their branch + HQ-wide departments only.
 * Master Admin sees all branches.
 *
 * The data layout is:
 *   [
 *     'branch_label' => [
 *       'departments' => [
 *         [ 'dept' => Department, 'top' => [ Admin (no manager in dept) ], 'children' => [adminId => [Admin, Admin]] ]
 *       ]
 *     ]
 *   ]
 *
 * "Top" admins are dept members with no manager (dept head, GM-level);
 * "children" maps a manager id → their direct reports for tree expansion.
 * The view recurses on `children[manager_id]`.
 */
class OrgChartController extends Controller
{
    public function index(Request $request): Renderable
    {
        $admin    = auth('admin')->user();
        $isMaster = (int) ($admin?->admin_role_id ?? 0) === 1;
        $branchId = $admin?->branch_id;

        // Load all admins in scope (single query — tree assembly is in PHP).
        $staff = Admin::query()
            ->where('status', 1)
            ->when(!$isMaster && $branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('f_name')
            ->get([
                'id', 'f_name', 'l_name', 'employee_code', 'designation',
                'department_id', 'designation_id', 'reports_to_admin_id',
                'branch_id', 'admin_role_id', 'image',
            ]);

        // Index by id for O(1) lookup; reports map by manager id.
        $byId = $staff->keyBy('id');
        $childrenByMgr = [];
        foreach ($staff as $s) {
            if ($s->reports_to_admin_id) {
                $childrenByMgr[$s->reports_to_admin_id][] = $s;
            }
        }

        // Departments in scope.
        $departments = Department::query()
            ->where('is_active', true)
            ->when(!$isMaster && $branchId, fn ($q) => $q->where(function ($qq) use ($branchId) {
                $qq->whereNull('branch_id')->orWhere('branch_id', $branchId);
            }))
            ->orderBy('sort_order')
            ->get();

        // Bucket staff by department.
        $byDept = [];
        $unassigned = [];
        foreach ($staff as $s) {
            if ($s->department_id) {
                $byDept[$s->department_id][] = $s;
            } else {
                $unassigned[] = $s;
            }
        }

        // For each department, the "top" nodes are dept members whose
        // manager is NOT in the same dept (or who have no manager).
        $tree = [];
        foreach ($departments as $dept) {
            $members = $byDept[$dept->id] ?? [];
            $tops    = [];
            foreach ($members as $m) {
                $mgrId   = $m->reports_to_admin_id;
                $mgrInDept = $mgrId && isset($byId[$mgrId]) && (int) $byId[$mgrId]->department_id === (int) $dept->id;
                if (!$mgrInDept) {
                    $tops[] = $m;
                }
            }
            $tree[] = [
                'dept'        => $dept,
                'tops'        => $tops,
                'all_count'   => count($members),
            ];
        }

        return view('admin-views.org-chart.index', [
            'tree'           => $tree,
            'unassigned'     => $unassigned,
            'childrenByMgr'  => $childrenByMgr,
            'byId'           => $byId,
            'isMaster'       => $isMaster,
            'totalStaff'     => $staff->count(),
        ]);
    }
}
