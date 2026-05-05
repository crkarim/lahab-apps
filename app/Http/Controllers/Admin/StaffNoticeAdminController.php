<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\Branch;
use App\Models\StaffNotice;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Admin-side CRUD for the My Lahab staff notice board. Publishes:
 *   - notice rows the staff Flutter app reads via /api/v1/staff/notices
 *   - an optional FCM topic push (lahab_staff_all OR lahab_staff_b{branch})
 *     on save so phones light up immediately.
 */
class StaffNoticeAdminController extends Controller
{
    public function __construct(private StaffNotice $notice) {}

    public function index(Request $request): Renderable
    {
        $search = (string) $request->query('search', '');
        $notices = $this->notice
            ->when($search !== '', fn ($q) => $q->where('title', 'like', "%{$search}%"))
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->appends($request->query());

        $branches = Branch::orderBy('name')->get(['id', 'name']);
        return view('admin-views.staff-notice.list', compact('notices', 'branches', 'search'));
    }

    public function create(): Renderable
    {
        $branches = Branch::orderBy('name')->get(['id', 'name']);
        return view('admin-views.staff-notice.add-new', compact('branches'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateNotice($request);
        $data['posted_by_admin_id'] = Auth::guard('admin')->id();
        $data['published_at']       = $data['published_at'] ?? now();

        $notice = StaffNotice::create($data);

        if ($request->boolean('send_push')) {
            $this->pushToTopic($notice);
        }

        Toastr::success(translate('Notice published.'));
        return redirect()->route('admin.staff-notice.list');
    }

    public function edit($id): Renderable
    {
        $notice   = StaffNotice::findOrFail($id);
        $branches = Branch::orderBy('name')->get(['id', 'name']);
        return view('admin-views.staff-notice.edit', compact('notice', 'branches'));
    }

    public function update(Request $request, $id): RedirectResponse
    {
        $notice = StaffNotice::findOrFail($id);
        $data = $this->validateNotice($request);
        $notice->update($data);

        if ($request->boolean('send_push')) {
            $this->pushToTopic($notice->fresh());
        }

        Toastr::success(translate('Notice updated.'));
        return redirect()->route('admin.staff-notice.list');
    }

    public function destroy($id): RedirectResponse
    {
        StaffNotice::where('id', $id)->delete();
        Toastr::success(translate('Notice deleted.'));
        return back();
    }

    private function validateNotice(Request $request): array
    {
        $request->validate([
            'title'        => 'required|string|max:200',
            'body'         => 'required|string',
            'branch_id'    => 'nullable|integer|exists:branches,id',
            'published_at' => 'nullable|date',
            'expires_at'   => 'nullable|date|after_or_equal:published_at',
            'is_pinned'    => 'nullable|boolean',
            'image'        => 'nullable|image|max:4096',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = 'staff-notice/' . uniqid('sn_', true) . '.' . $request->file('image')->getClientOriginalExtension();
            $request->file('image')->storeAs('staff-notice', basename($imagePath), 'public');
        }

        return array_filter([
            'title'        => $request->input('title'),
            'body'         => $request->input('body'),
            'branch_id'    => $request->filled('branch_id') ? (int) $request->branch_id : null,
            'published_at' => $request->input('published_at'),
            'expires_at'   => $request->input('expires_at'),
            'is_pinned'    => $request->boolean('is_pinned'),
            'image'        => $imagePath,
        ], fn ($v) => $v !== null);
    }

    /**
     * Fan-out the notice over FCM topics. Staff app subscribes to:
     *   - lahab_staff_all          (every staff)
     *   - lahab_staff_b{branch_id} (per-branch)
     * so we just publish to the right one. No DB iteration over fcm_tokens.
     */
    private function pushToTopic(StaffNotice $notice): void
    {
        $topic = $notice->branch_id
            ? "lahab_staff_b{$notice->branch_id}"
            : 'lahab_staff_all';

        Helpers::send_push_notif_to_topic(
            data: [
                'title'        => $notice->title,
                'description'  => strip_tags($notice->body),
                'order_id'     => '',
                'order_status' => '',
            ],
            topic: $topic,
            type: 'staff_notice',
        );
    }
}
