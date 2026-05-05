<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\Branch;
use App\Models\DeliveryChargeByArea;
use App\Models\DeliveryChargeSetup;
use App\Traits\UploadSizeHelperTrait;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    use UploadSizeHelperTrait;

    public function __construct(
        private Branch $branch,
        private DeliveryChargeSetup $deliveryChargeSetup,
        private DeliveryChargeByArea $deliveryChargeByArea
    )
    {}

    /**
     * @return Renderable
     */
    public function index(): Renderable
    {
        return view('admin-views.branch.index');
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $check = $this->validateUploadedFile($request, ['image', 'cover_image']);
        if ($check !== true) {
            return $check;
        }

        $request->validate([
            'name' => 'required|max:255|unique:branches',
            'email' => 'required|max:255|unique:branches',
            'password' => 'required|min:8|max:255',
            'preparation_time' => 'required',
            'image' => 'required|image|max:'. $this->maxImageSizeKB .'|mimes:' . implode(',', array_column(IMAGEEXTENSION, 'key')),
            'cover_image' => 'required|image|max:'. $this->maxImageSizeKB .'|mimes:' . implode(',', array_column(IMAGEEXTENSION, 'key')),
        ], [
            'name.required' => translate('Name is required!'),
        ]);

        if (!empty($request->file('image'))) {
            $imageName = Helpers::upload('branch/', APPLICATION_IMAGE_FORMAT, $request->file('image'));
        } else {
            $imageName = 'def.png';
        }

        if (!empty($request->file('cover_image'))) {
            $coverImageName = Helpers::upload('branch/', APPLICATION_IMAGE_FORMAT, $request->file('cover_image'));
        } else {
            $coverImageName = 'def.png';
        }
        $defaultBranch = $this->branch->find(1);
        $defaultLat = $defaultBranch->latitude ?? '23.777176';
        $defaultLong = $defaultBranch->longitude ?? '90.399452';
        $defaultCoverage = $defaultBranch->coverage ?? 100;

        $branch = $this->branch;
        $branch->name = $request->name;
        $branch->email = $request->email;
        $branch->latitude = $request->latitude ?? $defaultLat;
        $branch->longitude = $request->longitude ?? $defaultLong;
        $branch->coverage = $request->coverage ?? $defaultCoverage;
        $branch->address = $request->address;
        $branch->phone = $request->phone ?? null;
        $branch->password = bcrypt($request->password);
        $branch->preparation_time = $request->preparation_time;
        $branch->image = $imageName;
        $branch->cover_image = $coverImageName;
        $branch->save();

        $branchDeliveryCharge = $this->deliveryChargeSetup;
        $branchDeliveryCharge->branch_id = $branch->id;
        $branchDeliveryCharge->delivery_charge_type = 'fixed';
        $branchDeliveryCharge->fixed_delivery_charge = 0;
        $branchDeliveryCharge->save();

        return redirect()->route('admin.branch.list')->with('branch-store', true);
    }

    /**
     * @param $id
     * @return Renderable
     */
    public function edit($id): Renderable
    {
        $branch = $this->branch->find($id);
        return view('admin-views.branch.edit', compact('branch'));
    }

    /**
     * @param Request $request
     * @param $id
     * @return RedirectResponse
     */
    public function update(Request $request, $id): RedirectResponse
    {
        $check = $this->validateUploadedFile($request, ['image', 'cover_image']);
        if ($check !== true) {
            return $check;
        }

        $request->validate([
            'name' => 'required|max:255',
            'preparation_time' => 'required',
            'email' => ['required', 'unique:branches,email,' . $id . ',id'],
            'image' => 'image|max:'. $this->maxImageSizeKB .'|mimes:' . implode(',', array_column(IMAGEEXTENSION, 'key')),
            'cover_image' => 'image|max:'. $this->maxImageSizeKB .'|mimes:' . implode(',', array_column(IMAGEEXTENSION, 'key')),
            'password' => 'nullable|min:8|max:255',
        ], [
            'name.required' => translate('Name is required!'),
        ]);

        $request->validate([
            'name' => 'required',
            'email' => 'required'
        ], [
            'name.required' => translate('Name is required!'),
        ]);

        $request->validate([
            'attendance_geo_radius_m' => 'nullable|integer|min:25|max:5000',
        ]);

        $branch = $this->branch->find($id);
        $branch->name = $request->name;
        $branch->email = $request->email;
        $branch->longitude = $request->longitude ? $request->longitude : $branch->longitude;
        $branch->latitude = $request->latitude ? $request->latitude : $branch->latitude;
        $branch->coverage = $request->coverage ? $request->coverage : $branch->coverage;;
        $branch->address = $request->address;
        $branch->image = $request->has('image') ? Helpers::update('branch/', $branch->image, APPLICATION_IMAGE_FORMAT, $request->file('image')) : $branch->image;
        $branch->cover_image = $request->has('cover_image') ? Helpers::update('branch/', $branch->cover_image, APPLICATION_IMAGE_FORMAT, $request->file('cover_image')) : $branch->cover_image;
        if ($request['password'] != null) {
            $branch->password = bcrypt($request->password);
        }
        $branch->phone = $request->phone ?? '';
        $branch->preparation_time = $request->preparation_time;
        // My Lahab — geofence radius for attendance clock-in. Only update
        // if explicitly provided so a half-filled form doesn't reset it.
        if ($request->filled('attendance_geo_radius_m')) {
            $branch->attendance_geo_radius_m = (int) $request->attendance_geo_radius_m;
        }
        $branch->save();

        Toastr::success(translate('Branch updated successfully!'));
        return back();
    }

    /**
     * My Lahab — rotate the attendance QR token for a branch. Used when
     * the printed QR is suspected leaked. Forces all printed posters to
     * be reprinted, which is intentional.
     */
    public function regenerateAttendanceQr($id): RedirectResponse
    {
        $branch = $this->branch->find($id);
        if (! $branch) {
            Toastr::warning(translate('Branch not found.'));
            return back();
        }
        $branch->attendance_qr_token = 'lahab-att-' . \Illuminate\Support\Str::random(40);
        $branch->save();
        Toastr::success(translate('Attendance QR regenerated. Reprint the poster at this branch.'));
        return back();
    }

    /**
     * My Lahab — listing of every branch's attendance QR, intended as
     * a one-stop "print all the posters" page so managers don't have to
     * dig into each branch's edit form to find the QR.
     */
    public function attendanceQrPosters(): Renderable
    {
        $branches = $this->branch->orderBy('name')->get();
        return view('admin-views.branch.attendance-qr-posters', compact('branches'));
    }

    /**
     * My Lahab — render the branch's attendance QR as an SVG. SVG (not
     * PNG) because the docker image doesn't ship with the imagick PHP
     * extension that simple-qrcode's PNG backend needs. SVG is rendered
     * natively by every browser and prints crisp at any size.
     */
    public function attendanceQrImage($id)
    {
        $branch = $this->branch->find($id);
        if (! $branch || ! $branch->attendance_qr_token) {
            abort(404);
        }
        $svg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
            ->size(400)
            ->margin(2)
            ->generate($branch->attendance_qr_token);

        return response($svg, 200, ['Content-Type' => 'image/svg+xml']);
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function delete(Request $request): RedirectResponse
    {
        $branch = $this->branch->find($request->id);

        if ($branch && $branch->delete()) {
            $this->deliveryChargeSetup->where(['branch_id' => $request->id])->delete();
            $this->deliveryChargeByArea->where(['branch_id' => $request->id])->delete();

            Toastr::success(translate('Branch removed along with related data!'));
        } else {
            Toastr::error(translate('Failed to remove branch!'));
        }
        return back();
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function status(Request $request): RedirectResponse
    {
        $branch = $this->branch->find($request->id);
        $branch->status = $request->status;
        $branch->save();

        Toastr::success(translate('Branch status updated!'));
        return back();
    }

    /**
     * @param Request $request
     * @return Renderable
     */
    public function list(Request $request): Renderable
    {
        $search = $request['search'];
        $query = $this->branch
            ->with('delivery_charge_setup')
            ->when($search, function ($q) use ($search) {
                $key = explode(' ', $search);
                foreach ($key as $value) {
                    $q->orWhere('id', 'like', "%{$value}%")
                        ->orWhere('name', 'like', "%{$value}%");
                }
            });

        $queryParam = ['search' => $request['search']];
        $branches = $query->orderBy('id', 'DESC')->paginate(Helpers::getPagination())->appends($queryParam);

        return view('admin-views.branch.list', compact('branches', 'search'));
    }
}
