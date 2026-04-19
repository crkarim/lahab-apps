<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\Admin;
use App\Model\BusinessSetting;
use App\Model\Order;
use App\Traits\UploadSizeHelperTrait;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\Support\Renderable;

class SystemController extends Controller
{
    use UploadSizeHelperTrait;

    public function __construct(
        private Order           $order,
        private Admin           $admin,
        private BusinessSetting $businessSetting,
    )
    {
    }

    /**
     * @return JsonResponse
     */
    public function restaurantData(): JsonResponse
    {
        $newOrder = $this->order->where(['checked' => 0])->count();
        return response()->json([
            'success' => 1,
            'data' => ['new_order' => $newOrder]
        ]);
    }


    /**
     * @return Application|Factory|View
     */
    public function settings(): View|Factory|Application
    {
        return view('admin-views.settings');
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function settingsUpdate(Request $request): RedirectResponse
    {
        $check = $this->validateUploadedFile($request, ['image']);
        if ($check !== true) {
            return $check;
        }

        $request->validate([
            'f_name' => 'required',
            'email' => ['required', 'unique:admins,email,' . auth('admin')->id() . ',id'],
            'phone' => 'required',
            'image' => 'image|max:'. $this->maxImageSizeKB .'|mimes:' . implode(',', array_column(IMAGEEXTENSION, 'key')),

        ], [
            'f_name.required' => translate('First name is required!'),
        ]);

        $admin = $this->admin->find(auth('admin')->id());
        $admin->f_name = $request->f_name;
        $admin->l_name = $request->l_name;
        $admin->email = $request->email;
        $admin->phone = $request->phone;
        $admin->image = $request->has('image') ? Helpers::update('admin/', $admin->image, APPLICATION_IMAGE_FORMAT, $request->file('image')) : $admin->image;
        $admin->save();

        Toastr::success(translate('Admin updated successfully!'));
        return back();
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function settingsPasswordUpdate(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => 'required|min:8',
            'confirm_password' => 'required|same:password',
        ]);
        $admin = $this->admin->find(auth('admin')->id());
        $admin->password = bcrypt($request['password']);
        $admin->save();

        Toastr::success(translate('Admin password updated successfully!'));
        return back();
    }

    /**
     * @param Request $request
     * @param $app_id
     * @return Renderable
     */
    public function appActivate(Request $request, $app_id): Renderable
    {
        $app_name = 'default';
        $app_link = 'default';
        foreach (APPS as $app) {
            if ($app['software_id'] == $app_id) {
                $app_name = $app['app_name'];
                $app_link = $app['buy_now_link'];
            }
        }

        return view('admin-views.app-activation', compact('app_id', 'app_name', 'app_link'));
    }

    /**
     * @param Request $request
     * @param $app_id
     * @return RedirectResponse
     */
    public function activationSubmit(Request $request, $app_id): RedirectResponse
    {
        $previous_active = json_decode($this->businessSetting->where('key', 'app_activation')->first()->value ?? '[]');
        $found = 0;
        foreach ($previous_active as $item) {
            if ($item->software_id == $app_id) {
                $found = 1;
            }
        }
        if (!$found) {
            $previous_active[] = [
                'software_id' => $app_id,
                'is_active' => 1
            ];
            $this->businessSetting->updateOrInsert(['key' => 'app_activation'], [
                'value' => json_encode($previous_active)
            ]);
        }

        Toastr::success('succesfully activated');
        return back();
    }
}
