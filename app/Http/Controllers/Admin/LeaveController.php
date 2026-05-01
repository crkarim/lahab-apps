<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Model\Admin;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * HRM Phase 5.2 + 6.2 — Leave management admin surface.
 *
 * Three lanes on a single page:
 *   1. My requests              — what the viewer has filed (any status)
 *   2. My approvals queue       — pending requests from people who report
 *                                 directly to the viewer (or all branch-
 *                                 scoped pending if viewer is Master Admin)
 *   3. Recent decisions         — last 30 approved/rejected/cancelled
 *
 * Approval routing (Phase 6.2):
 *   - Each request goes to the requester's `reports_to_admin_id` (their
 *     direct manager). Master Admin (admin_role_id=1) is always a valid
 *     approver as escape hatch.
 *   - Branch managers no longer auto-approve everyone in their branch —
 *     only their direct reports.
 *   - If the requester has no manager set AND viewer isn't Master Admin,
 *     no one in the queue can approve it → nudges HR to set reports_to.
 *
 * BD Labour Act seeded entitlements live in `leave_types.days_per_year`;
 * balance is computed live as
 *   days_per_year − Σ approved request days this calendar year.
 */
class LeaveController extends Controller
{
    public function index(Request $request): Renderable
    {
        $admin    = auth('admin')->user();
        $branchId = $admin?->branch_id;
        $isMaster = $admin && (int) $admin->admin_role_id === 1;

        $types = LeaveType::active();

        // Lane 1 — viewer's own requests.
        $myRequests = LeaveRequest::query()
            ->with(['type:id,name,code,color,is_paid', 'reviewedBy:id,f_name,l_name'])
            ->where('admin_id', $admin?->id)
            ->orderByDesc('created_at')
            ->limit(40)
            ->get();

        // Lane 2 — approval queue (Phase 6.2 routing).
        // Master Admin sees all branch-scoped pending; everyone else sees
        // only requests from people who report directly to them.
        $approvalQuery = LeaveRequest::query()
            ->with(['employee:id,f_name,l_name,designation,employee_code,reports_to_admin_id', 'type:id,name,code,color,is_paid', 'branch:id,name'])
            ->where('status', 'pending')
            ->where('admin_id', '!=', $admin?->id);

        if ($isMaster) {
            $approvalQuery->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
        } else {
            $directReportIds = Admin::query()
                ->where('reports_to_admin_id', $admin?->id)
                ->pluck('id');
            $approvalQuery->whereIn('admin_id', $directReportIds);
        }
        $pendingApprovals = $approvalQuery->orderBy('from_date')->get();

        // Lane 3 — recent decisions (branch-scoped, any reviewer/requester).
        $recentDecisions = LeaveRequest::query()
            ->with(['employee:id,f_name,l_name,employee_code', 'type:id,name,code,color', 'reviewedBy:id,f_name,l_name'])
            ->whereIn('status', ['approved', 'rejected', 'cancelled'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderByDesc('updated_at')
            ->limit(30)
            ->get();

        // Balance card for the viewer — only meaningful for non–Master Admin
        // (admin_role_id == 1 isn't an employee in the HR sense).
        $myBalances = collect();
        if ($admin && !$isMaster) {
            $myBalances = $types->map(function (LeaveType $t) use ($admin) {
                $taken = LeaveRequest::takenThisYear($admin->id, $t->id);
                return [
                    'type'      => $t,
                    'taken'     => $taken,
                    'remaining' => max(0, (int) $t->days_per_year - $taken),
                ];
            });
        }

        // Eligible employees the viewer can file leave on behalf of.
        // Master Admin: anyone in their branch scope. Manager (has direct
        // reports): only their reports. Plain employee: only themselves
        // (form hides selector).
        $hasReports    = $admin && Admin::where('reports_to_admin_id', $admin->id)->exists();
        $isManager     = $isMaster || $hasReports;
        $eligibleStaff = collect();
        if ($isMaster) {
            $eligibleStaff = Admin::query()
                ->where('status', 1)
                ->where('admin_role_id', '!=', 1)
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->orderBy('f_name')
                ->get(['id', 'f_name', 'l_name', 'designation', 'employee_code']);
        } elseif ($hasReports) {
            $eligibleStaff = Admin::query()
                ->where('status', 1)
                ->where('reports_to_admin_id', $admin->id)
                ->orderBy('f_name')
                ->get(['id', 'f_name', 'l_name', 'designation', 'employee_code']);
        }

        // Nudge: viewer has no manager set → flag so HR knows to fix it.
        // Without a reports_to, no one but Master Admin can approve their leave.
        $needsManager = $admin && !$isMaster && !$admin->reports_to_admin_id;

        return view('admin-views.leave.index', [
            'types'            => $types,
            'myRequests'       => $myRequests,
            'pendingApprovals' => $pendingApprovals,
            'recentDecisions'  => $recentDecisions,
            'myBalances'       => $myBalances,
            'eligibleStaff'    => $eligibleStaff,
            'isManager'        => $isManager,
            'isMaster'         => $isMaster,
            'needsManager'     => $needsManager,
        ]);
    }

    /**
     * File a new leave request. Employees file for themselves; managers
     * may file on behalf of any employee in their branch (Master Admin: any).
     * Status starts 'pending' even when filed by a manager — keeps the
     * audit trail honest; the manager can approve in the next click.
     */
    public function store(Request $request): RedirectResponse
    {
        $admin = auth('admin')->user();
        if (!$admin) return back()->with('error', 'Not authenticated.');

        $validated = $request->validate([
            'admin_id'      => 'nullable|integer|exists:admins,id',
            'leave_type_id' => 'required|integer|exists:leave_types,id',
            'from_date'     => 'required|date',
            'to_date'       => 'required|date|after_or_equal:from_date',
            'days'          => 'nullable|integer|min:1',
            'reason'        => 'nullable|string|max:1000',
        ]);

        $isManager = (int) $admin->admin_role_id === 1 || !empty($admin->branch_id);
        $targetId  = $validated['admin_id'] ?? null;

        // Non-managers can only file for themselves; ignore any submitted admin_id.
        if (!$isManager || !$targetId) {
            $targetId = $admin->id;
        }

        $employee = Admin::find($targetId);
        if (!$employee) return back()->with('error', 'Employee not found.');

        // Branch isolation — a branch manager can't file across branches.
        if ($admin->branch_id && $employee->branch_id !== $admin->branch_id) {
            return back()->with('error', 'That employee is not in your branch.');
        }

        $from = Carbon::parse($validated['from_date'])->startOfDay();
        $to   = Carbon::parse($validated['to_date'])->startOfDay();
        $days = (int) ($validated['days'] ?? ($from->diffInDays($to) + 1));
        if ($days < 1) $days = 1;

        // Soft balance check — warn and block over-budget requests for paid types
        // (Unpaid type has days_per_year=0 → skip).
        $type = LeaveType::find($validated['leave_type_id']);
        if ($type && $type->is_paid && (int) $type->days_per_year > 0) {
            $taken = LeaveRequest::takenThisYear($employee->id, $type->id, (int) $from->format('Y'));
            $wouldUse = $taken + $days;
            if ($wouldUse > (int) $type->days_per_year) {
                $remaining = max(0, (int) $type->days_per_year - $taken);
                return back()->with('error',
                    "Over balance · {$type->name}: {$days} day(s) requested, only {$remaining} remaining for " . $from->format('Y') . '.'
                );
            }
        }

        LeaveRequest::create([
            'admin_id'      => $employee->id,
            'leave_type_id' => $type->id,
            'branch_id'     => $employee->branch_id,
            'from_date'     => $from->toDateString(),
            'to_date'       => $to->toDateString(),
            'days'          => $days,
            'reason'        => $validated['reason'] ?? null,
            'status'        => 'pending',
        ]);

        return back()->with('success', 'Leave request filed · ' . $days . ' day(s) ' . $type->name);
    }

    /** Approve a pending request (branch manager / Master Admin). */
    public function approve(Request $request, int $id): RedirectResponse
    {
        return $this->decide($request, $id, 'approved');
    }

    /** Reject a pending request (branch manager / Master Admin). */
    public function reject(Request $request, int $id): RedirectResponse
    {
        return $this->decide($request, $id, 'rejected');
    }

    /**
     * Employee withdrawing their own pending request before review.
     * Once a request is approved/rejected the employee can't unilaterally
     * cancel — that needs a manager edit.
     */
    public function cancel(Request $request, int $id): RedirectResponse
    {
        $admin = auth('admin')->user();
        $req   = LeaveRequest::find($id);
        if (!$req) return back()->with('error', 'Leave request not found.');

        if ((int) $req->admin_id !== (int) $admin?->id) {
            return back()->with('error', 'You can only cancel your own request.');
        }
        if ($req->status !== 'pending') {
            return back()->with('error', 'Only pending requests can be cancelled.');
        }

        $req->forceFill([
            'status'      => 'cancelled',
            'reviewed_at' => now(),
            'review_note' => trim((string) $request->input('review_note', 'Cancelled by employee')),
        ])->save();

        return back()->with('success', 'Leave request #' . $req->id . ' cancelled.');
    }

    /**
     * Shared approve/reject logic. Caller passes the target status.
     *
     * Approval routing (Phase 6.2):
     *   1. Master Admin (admin_role_id == 1) can approve anything.
     *   2. Otherwise the viewer must be the requester's `reports_to_admin_id`.
     * Requester cannot self-approve. No branch fall-through any more — if
     * an employee has no manager set, only Master Admin can approve, which
     * is the cue for HR to set their `reports_to`.
     */
    private function decide(Request $request, int $id, string $status): RedirectResponse
    {
        $admin = auth('admin')->user();
        if (!$admin) return back()->with('error', 'Not authenticated.');

        $req = LeaveRequest::with('employee:id,reports_to_admin_id,branch_id')->find($id);
        if (!$req) return back()->with('error', 'Leave request not found.');

        if ($req->status !== 'pending') {
            return back()->with('error', 'This request has already been ' . $req->status . '.');
        }
        if ((int) $req->admin_id === (int) $admin->id) {
            return back()->with('error', 'You cannot review your own leave request.');
        }

        $isMaster      = (int) $admin->admin_role_id === 1;
        $isDirectMgr   = $req->employee && (int) $req->employee->reports_to_admin_id === (int) $admin->id;
        if (!$isMaster && !$isDirectMgr) {
            return back()->with('error', 'Only the requester\'s direct manager (or Master Admin) can review this. Set their "Reports to" on the employee record if you should be the approver.');
        }

        $req->forceFill([
            'status'               => $status,
            'reviewed_by_admin_id' => $admin->id,
            'reviewed_at'          => now(),
            'review_note'          => trim((string) $request->input('review_note', '')),
        ])->save();

        $verb = $status === 'approved' ? 'approved' : 'rejected';
        return back()->with('success', 'Leave request #' . $req->id . ' ' . $verb . '.');
    }
}
