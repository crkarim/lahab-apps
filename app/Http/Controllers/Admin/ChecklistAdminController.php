<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Model\Branch;
use App\Models\ChecklistRun;
use App\Models\ChecklistTemplate;
use App\Models\ChecklistTemplateItem;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Admin-side CRUD for the My Lahab open/close checklists.
 *
 *   Templates: list / create / edit / archive.
 *   Items:     managed inline on the template edit page (add/remove/reorder).
 *   Runs:      read-only audit viewer.
 *
 * All paths are gated by the `checklists` module key in the route group.
 */
class ChecklistAdminController extends Controller
{
    public function index(Request $request): Renderable
    {
        $kind = (string) $request->query('kind', '');
        $search = (string) $request->query('q', '');

        $templates = ChecklistTemplate::query()
            ->when($kind !== '', fn ($q) => $q->where('kind', $kind))
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->withCount('items')
            ->orderByRaw("FIELD(kind, 'open', 'daily', 'close', 'weekly')")
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(30)
            ->appends($request->query());

        $branches = Branch::orderBy('name')->get(['id', 'name']);

        // Today-status per template: pending / in-progress / done.
        // Single grouped query so the page stays cheap with many runs.
        $today = now()->toDateString();
        $todayRuns = \App\Models\ChecklistRun::query()
            ->whereIn('template_id', $templates->pluck('id'))
            ->where('run_date', $today)
            ->select('template_id', 'id', 'completed_at', 'started_at')
            ->get()
            ->groupBy('template_id');

        // Last run timestamp per template (any date) for "Last used".
        $lastRuns = \App\Models\ChecklistRun::query()
            ->whereIn('template_id', $templates->pluck('id'))
            ->select('template_id', \Illuminate\Support\Facades\DB::raw('MAX(run_date) as run_date'))
            ->groupBy('template_id')
            ->pluck('run_date', 'template_id');

        // Per-template item totals + checked counts (for in-progress display).
        $todayProgress = \Illuminate\Support\Facades\DB::table('checklist_run_items as ri')
            ->join('checklist_runs as r', 'r.id', '=', 'ri.run_id')
            ->whereIn('r.template_id', $templates->pluck('id'))
            ->where('r.run_date', $today)
            ->select(
                'r.template_id',
                \Illuminate\Support\Facades\DB::raw('SUM(CASE WHEN ri.checked_at IS NOT NULL THEN 1 ELSE 0 END) as checked'),
                \Illuminate\Support\Facades\DB::raw('COUNT(*) as total'),
            )
            ->groupBy('r.template_id')
            ->get()
            ->keyBy('template_id');

        // Top-strip totals.
        $stats = [
            'total_assignments' => $templates->total(),
            'started_today'     => \App\Models\ChecklistRun::where('run_date', $today)->count(),
            'completed_today'   => \App\Models\ChecklistRun::where('run_date', $today)->whereNotNull('completed_at')->count(),
        ];

        return view('admin-views.checklist.list', compact(
            'templates', 'branches', 'kind', 'search',
            'todayRuns', 'lastRuns', 'todayProgress', 'stats',
        ));
    }

    public function create(): Renderable
    {
        $branches = Branch::orderBy('name')->get(['id', 'name']);
        return view('admin-views.checklist.add-new', compact('branches'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name'      => 'required|string|max:120',
            'kind'      => 'required|in:open,daily,close,weekly',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'sort_order'=> 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'notes'     => 'nullable|string',
        ]);

        $template = ChecklistTemplate::create([
            'name'                => $request->input('name'),
            'kind'                => $request->input('kind'),
            'branch_id'           => $request->filled('branch_id') ? (int) $request->branch_id : null,
            'sort_order'          => (int) $request->input('sort_order', 0),
            'is_active'           => $request->boolean('is_active', true),
            'notes'               => $request->input('notes'),
            'created_by_admin_id' => Auth::guard('admin')->id(),
        ]);

        Toastr::success(translate('Checklist created. Add items next.'));
        return redirect()->route('admin.checklist.edit', [$template->id]);
    }

    public function edit($id): Renderable
    {
        $template = ChecklistTemplate::with(['items.assignedDesignation:id,name', 'items.assignedAdmin:id,f_name,l_name'])->findOrFail($id);
        $branches = Branch::orderBy('name')->get(['id', 'name']);
        $designations = \App\Models\Designation::orderBy('name')->get(['id', 'name']);
        // Pool of staff this template can be assigned to. Branch-scoped
        // when the template is for one branch; everyone otherwise.
        $staffQ = \App\Model\Admin::query()
            ->where('status', 1)
            ->where('admin_role_id', '!=', 1)
            ->orderBy('f_name');
        if ($template->branch_id) {
            $staffQ->where('branch_id', $template->branch_id);
        }
        $staff = $staffQ->get(['id', 'f_name', 'l_name', 'designation_id']);
        return view('admin-views.checklist.edit', compact('template', 'branches', 'designations', 'staff'));
    }

    public function update(Request $request, $id): RedirectResponse
    {
        $template = ChecklistTemplate::findOrFail($id);

        $request->validate([
            'name'      => 'required|string|max:120',
            'kind'      => 'required|in:open,daily,close,weekly',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'sort_order'=> 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'notes'     => 'nullable|string',
        ]);

        $template->update([
            'name'      => $request->input('name'),
            'kind'      => $request->input('kind'),
            'branch_id' => $request->filled('branch_id') ? (int) $request->branch_id : null,
            'sort_order'=> (int) $request->input('sort_order', 0),
            'is_active' => $request->boolean('is_active', true),
            'notes'     => $request->input('notes'),
        ]);

        Toastr::success(translate('Checklist updated.'));
        return back();
    }

    public function destroy($id): RedirectResponse
    {
        DB::transaction(function () use ($id) {
            ChecklistTemplateItem::where('template_id', $id)->delete();
            ChecklistTemplate::where('id', $id)->delete();
        });
        Toastr::success(translate('Checklist deleted.'));
        return redirect()->route('admin.checklist.list');
    }

    public function addItem(Request $request, $id): RedirectResponse
    {
        $template = ChecklistTemplate::findOrFail($id);
        $request->validate([
            'label'                   => 'required|string|max:200',
            'is_required'             => 'nullable|boolean',
            'requires_photo'          => 'nullable|boolean',
            'notes'                   => 'nullable|string',
            'assigned_designation_id' => 'nullable|integer|exists:designations,id',
            'assigned_admin_id'       => 'nullable|integer|exists:admins,id',
            'scheduled_time'          => 'nullable|date_format:H:i',
        ]);

        // Append to the end by default.
        $next = (int) ChecklistTemplateItem::where('template_id', $template->id)->max('sort_order') + 1;

        ChecklistTemplateItem::create([
            'template_id'             => $template->id,
            'label'                   => $request->input('label'),
            'sort_order'              => $next,
            'is_required'             => $request->boolean('is_required', true),
            'requires_photo'          => $request->boolean('requires_photo', true),
            'notes'                   => $request->input('notes'),
            'assigned_designation_id' => $request->filled('assigned_designation_id')
                ? (int) $request->assigned_designation_id : null,
            'assigned_admin_id'       => $request->filled('assigned_admin_id')
                ? (int) $request->assigned_admin_id : null,
            'scheduled_time'          => $request->input('scheduled_time') ?: null,
        ]);

        Toastr::success(translate('Item added.'));
        return back();
    }

    public function removeItem($id, $itemId): RedirectResponse
    {
        ChecklistTemplateItem::where('template_id', $id)->where('id', $itemId)->delete();
        Toastr::success(translate('Item removed.'));
        return back();
    }

    public function runs(Request $request): Renderable
    {
        $branchId = (int) $request->query('branch_id', 0) ?: null;
        $date     = $request->query('date');
        $status   = (string) $request->query('status', '');
        $search   = (string) $request->query('q', '');

        $runs = ChecklistRun::query()
            ->with(['template:id,name,kind', 'branch:id,name', 'startedBy:id,f_name,l_name'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($date, fn ($q) => $q->where('run_date', $date))
            ->when($status === 'completed', fn ($q) => $q->whereNotNull('completed_at'))
            ->when($status === 'in_progress', fn ($q) => $q->whereNull('completed_at'))
            ->when($search !== '', fn ($q) => $q->whereHas('template', fn ($qq) => $qq->where('name', 'like', "%{$search}%")))
            ->orderByDesc('run_date')
            ->orderByDesc('id')
            ->paginate(30)
            ->appends($request->query());

        // Per-run progress + photo count via grouped queries.
        $runIds = $runs->pluck('id');
        $progressRows = \Illuminate\Support\Facades\DB::table('checklist_run_items')
            ->whereIn('run_id', $runIds)
            ->select(
                'run_id',
                \Illuminate\Support\Facades\DB::raw('COUNT(*) as total'),
                \Illuminate\Support\Facades\DB::raw('SUM(CASE WHEN checked_at IS NOT NULL THEN 1 ELSE 0 END) as checked'),
                \Illuminate\Support\Facades\DB::raw('SUM(CASE WHEN photo_path IS NOT NULL THEN 1 ELSE 0 END) as photos'),
            )
            ->groupBy('run_id')
            ->get()
            ->keyBy('run_id');

        // Submitter avatars (initials) per run.
        $submitters = \Illuminate\Support\Facades\DB::table('checklist_run_submissions')
            ->whereIn('run_id', $runIds)
            ->select('run_id', 'admin_name')
            ->orderBy('submitted_at')
            ->get()
            ->groupBy('run_id');

        // Stats strip.
        $today = now()->toDateString();
        $todayRunsQ = ChecklistRun::where('run_date', $today)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
        $stats = [
            'started_today'   => (clone $todayRunsQ)->count(),
            'completed_today' => (clone $todayRunsQ)->whereNotNull('completed_at')->count(),
            'in_progress'     => (clone $todayRunsQ)->whereNull('completed_at')->count(),
        ];

        $branches = Branch::orderBy('name')->get(['id', 'name']);
        return view('admin-views.checklist.runs', compact(
            'runs', 'branches', 'branchId', 'date', 'status', 'search',
            'progressRows', 'submitters', 'stats',
        ));
    }

    /**
     * Per-run drill-down: every step + checked-by + photo + note.
     * The "management preview with photo" the office asked for.
     */
    public function runDetail($id): Renderable
    {
        $run = ChecklistRun::with([
            'template:id,name,kind',
            'branch:id,name',
            'startedBy:id,f_name,l_name',
            'items.checkedBy:id,f_name,l_name',
        ])->findOrFail($id);
        return view('admin-views.checklist.run-detail', compact('run'));
    }
}
