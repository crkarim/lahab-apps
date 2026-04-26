<?php

namespace App\Http\Controllers\Branch;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\Branch;
use App\Model\Order;
use App\Model\OrderDetail;
use App\Model\ProductByBranch;
use App\Model\Table;
use App\Traits\UploadSizeHelperTrait;
use Brian2694\Toastr\Facades\Toastr;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\Support\Renderable;

class DashboardController extends Controller
{
    use UploadSizeHelperTrait;

    public function __construct(
        private Order        $order,
        private OrderDetail  $orderDetail,
        private Branch       $branch,
    )
    {}

    /**
     * Branch-scoped dashboard. Mirrors the admin dashboard layout exactly
     * but every query is filtered to the authenticated branch — so the
     * branch manager sees their own snapshot, their own funnel, their
     * own trend, never the other branches'.
     *
     * @return Renderable
     */
    public function dashboard(): Renderable
    {
        Helpers::update_daily_product_stock();

        $branchId   = auth('branch')->id();
        $today      = Carbon::today();
        $yesterday  = Carbon::yesterday();
        $currency   = Helpers::currency_code();

        $snapshot   = $this->snapshotForRange($today, $yesterday, $branchId);
        $live       = $this->liveOpsFunnel($branchId);
        $trend      = $this->revenueTrend($branchId);
        $topToday   = $this->topDishesToday($today, $branchId);
        $recent     = $this->recentOrders(5, $branchId);
        $alerts     = $this->operationalAlerts($today, $branchId);

        return view('branch-views.dashboard', compact(
            'snapshot', 'live', 'trend', 'topToday', 'recent', 'alerts', 'currency'
        ));
    }

    private function snapshotForRange(Carbon $today, Carbon $yesterday, int $branchId): array
    {
        $base = fn () => $this->order->newQuery()->where('branch_id', $branchId);

        $todayAgg = $base()
            ->whereDate('created_at', $today)
            ->selectRaw("
                COUNT(*) as orders_count,
                COALESCE(SUM(CASE WHEN order_status NOT IN ('canceled','failed') THEN order_amount ELSE 0 END), 0) as revenue
            ")
            ->first();

        $yestAgg = $base()
            ->whereDate('created_at', $yesterday)
            ->selectRaw("
                COUNT(*) as orders_count,
                COALESCE(SUM(CASE WHEN order_status NOT IN ('canceled','failed') THEN order_amount ELSE 0 END), 0) as revenue
            ")
            ->first();

        $tablesInUse = $base()
            ->where('order_type', 'dine_in')
            ->where('payment_status', '!=', 'paid')
            ->whereNotIn('order_status', ['completed', 'canceled', 'failed'])
            ->whereNotNull('table_id')
            ->distinct('table_id')
            ->count('table_id');

        $totalTables = Table::query()
            ->where('is_active', 1)
            ->where('branch_id', $branchId)
            ->count();

        $ordersToday = (int) $todayAgg->orders_count;
        $revToday    = (float) $todayAgg->revenue;
        $aov         = $ordersToday > 0 ? $revToday / $ordersToday : 0;

        $delta = function ($now, $prev) {
            if ((float) $prev == 0.0) return $now > 0 ? 100 : 0;
            return (int) round((($now - $prev) / $prev) * 100);
        };

        return [
            'revenue'           => $revToday,
            'revenue_delta_pct' => $delta($revToday, (float) $yestAgg->revenue),
            'orders'            => $ordersToday,
            'orders_delta_pct'  => $delta($ordersToday, (int) $yestAgg->orders_count),
            'aov'               => $aov,
            'aov_delta_pct'     => $delta($aov, (int) $yestAgg->orders_count > 0 ? ((float) $yestAgg->revenue / (int) $yestAgg->orders_count) : 0),
            'tables_in_use'     => $tablesInUse,
            'tables_total'      => $totalTables,
        ];
    }

    private function liveOpsFunnel(int $branchId): array
    {
        $base = fn () => $this->order->newQuery()->where('branch_id', $branchId);

        return [
            'pending_kitchen' => (clone $base())
                ->whereIn('order_type', ['pos', 'dine_in'])
                ->where(function ($q) {
                    $q->whereNull('kot_sent_at')
                      ->orWhereIn('order_status', ['pending', 'confirmed']);
                })
                ->whereNotIn('order_status', ['completed', 'delivered', 'canceled', 'failed'])
                ->count(),
            'in_kitchen' => (clone $base())
                ->whereIn('order_status', ['cooking', 'processing'])
                ->count(),
            'on_route' => (clone $base())
                ->where('order_type', 'delivery')
                ->where('order_status', 'out_for_delivery')
                ->count(),
            'awaiting_payment' => (clone $base())
                ->where('payment_status', '!=', 'paid')
                ->whereIn('order_status', ['delivered', 'completed'])
                ->count(),
        ];
    }

    private function revenueTrend(int $branchId): array
    {
        return [
            '7d'  => $this->trendByDay(7, $branchId),
            '30d' => $this->trendByDay(30, $branchId),
            '12m' => $this->trendByMonth(12, $branchId),
        ];
    }

    private function trendByDay(int $days, int $branchId): array
    {
        $start = Carbon::now()->subDays($days - 1)->startOfDay();

        $rows = $this->order->newQuery()
            ->where('branch_id', $branchId)
            ->where('order_status', '!=', 'canceled')
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as d, COALESCE(SUM(order_amount),0) as revenue, COUNT(*) as orders')
            ->groupBy('d')
            ->get()
            ->keyBy(fn ($r) => (string) $r->d);

        $labels = []; $revenue = []; $orders = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i)->toDateString();
            $labels[]  = Carbon::parse($date)->format('M j');
            $revenue[] = (float) ($rows[$date]->revenue ?? 0);
            $orders[]  = (int)   ($rows[$date]->orders  ?? 0);
        }

        return ['labels' => $labels, 'revenue' => $revenue, 'orders' => $orders];
    }

    private function trendByMonth(int $months, int $branchId): array
    {
        $start = Carbon::now()->startOfMonth()->subMonths($months - 1);

        $rows = $this->order->newQuery()
            ->where('branch_id', $branchId)
            ->where('order_status', '!=', 'canceled')
            ->where('created_at', '>=', $start)
            ->selectRaw("DATE_FORMAT(created_at,'%Y-%m') as m, COALESCE(SUM(order_amount),0) as revenue, COUNT(*) as orders")
            ->groupBy('m')
            ->get()
            ->keyBy(fn ($r) => (string) $r->m);

        $labels = []; $revenue = []; $orders = [];
        for ($i = 0; $i < $months; $i++) {
            $key = $start->copy()->addMonths($i)->format('Y-m');
            $labels[]  = $start->copy()->addMonths($i)->format('M Y');
            $revenue[] = (float) ($rows[$key]->revenue ?? 0);
            $orders[]  = (int)   ($rows[$key]->orders  ?? 0);
        }

        return ['labels' => $labels, 'revenue' => $revenue, 'orders' => $orders];
    }

    private function topDishesToday(Carbon $today, int $branchId): \Illuminate\Support\Collection
    {
        return $this->orderDetail->newQuery()
            ->whereHas('order', function ($q) use ($today, $branchId) {
                $q->whereDate('created_at', $today)
                  ->where('branch_id', $branchId)
                  ->whereNotIn('order_status', ['canceled', 'failed']);
            })
            ->with('product:id,name,image')
            ->select('product_id', DB::raw('SUM(quantity) as qty'), DB::raw('SUM(quantity * price) as revenue'))
            ->groupBy('product_id')
            ->orderByDesc('qty')
            ->take(5)
            ->get();
    }

    private function recentOrders(int $limit, int $branchId): \Illuminate\Database\Eloquent\Collection
    {
        return $this->order->newQuery()
            ->with(['customer:id,f_name,l_name', 'table:id,number'])
            ->where('branch_id', $branchId)
            ->latest()
            ->take($limit)
            ->get(['id', 'user_id', 'table_id', 'order_type', 'order_status', 'payment_status', 'order_amount', 'created_at']);
    }

    private function operationalAlerts(Carbon $today, int $branchId): array
    {
        $lowStockThreshold = 5;

        $lowStock = ProductByBranch::query()
            ->where('branch_id', $branchId)
            ->where('stock_type', '!=', 'unlimited')
            ->where('stock', '<=', $lowStockThreshold)
            ->count();

        $pendingRefunds = $this->order->newQuery()
            ->where('branch_id', $branchId)
            ->where(function ($q) {
                $q->where('order_status', 'returned')
                  ->orWhere(function ($qq) {
                      $qq->whereIn('order_status', ['delivered', 'completed'])
                         ->where('payment_status', '!=', 'paid');
                  });
            })
            ->count();

        $staffOnShift = $this->order->newQuery()
            ->where('branch_id', $branchId)
            ->whereDate('created_at', $today)
            ->whereNotNull('placed_by_admin_id')
            ->distinct('placed_by_admin_id')
            ->count('placed_by_admin_id');

        return [
            'low_stock'        => $lowStock,
            'pending_refunds'  => $pendingRefunds,
            'staff_on_shift'   => $staffOnShift,
        ];
    }

    /**
     * @return Renderable
     */
    public function settings(): Renderable
    {
        return view('branch-views.settings');
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
            'name' => 'required',
            'phone' => 'required',
            'image' => 'image|max:'. $this->maxImageSizeKB .'|mimes:' . implode(',', array_column(IMAGEEXTENSION, 'key')),
        ]);

        $branch = $this->branch->find(auth('branch')->id());

        if ($request->has('image')) {
            $imageName = Helpers::update('branch/', $branch->image, APPLICATION_IMAGE_FORMAT, $request->file('image'));
        } else {
            $imageName = $branch['image'];
        }

        $branch->name = $request->name;
        $branch->image = $imageName;
        $branch->phone = $request->phone;
        $branch->save();

        Toastr::success(translate('Branch updated successfully!'));
        return back();
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function settingsPasswordUpdate(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => 'required|same:confirm_password|min:8|max:255',
            'confirm_password' => 'required|max:255',
        ]);

        $branch = $this->branch->find(auth('branch')->id());
        $branch->password = bcrypt($request['password']);
        $branch->save();

        Toastr::success(translate('Branch password updated successfully!'));
        return back();
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function orderStats(Request $request): JsonResponse
    {
        session()->put('statistics_type', $request['statistics_type']);
        $data = self::orderStatisticsData();

        return response()->json([
            'view' => view('branch-views.partials._dashboard-order-stats', compact('data'))->render()
        ], 200);
    }

    /**
     * @return array
     */
    public function orderStatisticsData(): array
    {
        $today = session()->has('statistics_type') && session('statistics_type') == 'today' ? 1 : 0;
        $thisMonth = session()->has('statistics_type') && session('statistics_type') == 'this_month' ? 1 : 0;

        $pending = $this->order
            ->where(['order_status' => 'pending', 'branch_id' => auth('branch')->id()])
            ->when($today, function ($query) {
                return $query->whereDate('created_at', Carbon::today());
            })
            ->when($thisMonth, function ($query) {
                return $query->whereMonth('created_at', Carbon::now());
            })
            ->count();

        $confirmed = $this->order
            ->where(['order_status' => 'confirmed', 'branch_id' => auth('branch')->id()])
            ->when($today, function ($query) {
                return $query->whereDate('created_at', Carbon::today());
            })
            ->when($thisMonth, function ($query) {
                return $query->whereMonth('created_at', Carbon::now());
            })
            ->count();

        $processing = $this->order
            ->where(['order_status' => 'processing', 'branch_id' => auth('branch')->id()])
            ->when($today, function ($query) {
                return $query->whereDate('created_at', Carbon::today());
            })
            ->when($thisMonth, function ($query) {
                return $query->whereMonth('created_at', Carbon::now());
            })
            ->count();

        $outForDelivery = $this->order
            ->where(['order_status' => 'out_for_delivery', 'branch_id' => auth('branch')->id()])
            ->when($today, function ($query) {
                return $query->whereDate('created_at', Carbon::today());
            })
            ->when($thisMonth, function ($query) {
                return $query->whereMonth('created_at', Carbon::now());
            })
            ->count();

        $delivered = $this->order
            ->where(['order_status' => 'delivered', 'branch_id' => auth('branch')->id()])
            ->when($today, function ($query) {
                return $query->whereDate('created_at', Carbon::today());
            })
            ->when($thisMonth, function ($query) {
                return $query->whereMonth('created_at', Carbon::now());
            })
            ->count();

        $canceled = $this->order
            ->where(['order_status' => 'canceled', 'branch_id' => auth('branch')->id()])
            ->when($today, function ($query) {
                return $query->whereDate('created_at', Carbon::today());
            })
            ->when($thisMonth, function ($query) {
                return $query->whereMonth('created_at', Carbon::now());
            })
            ->count();

        $all = $this->order
            ->where(['branch_id' => auth('branch')->id()])
            ->when($today, function ($query) {
                return $query->whereDate('created_at', Carbon::today());
            })
            ->when($thisMonth, function ($query) {
                return $query->whereMonth('created_at', Carbon::now());
            })
            ->count();

        $returned = $this->order
            ->where(['order_status' => 'returned', 'branch_id' => auth('branch')->id()])
            ->when($today, function ($query) {
                return $query->whereDate('created_at', Carbon::today());
            })
            ->when($thisMonth, function ($query) {
                return $query->whereMonth('created_at', Carbon::now());
            })
            ->count();

        $failed = $this->order
            ->where(['order_status' => 'failed', 'branch_id' => auth('branch')->id()])
            ->when($today, function ($query) {
                return $query->whereDate('created_at', Carbon::today());
            })
            ->when($thisMonth, function ($query) {
                return $query->whereMonth('created_at', Carbon::now());
            })
            ->count();

        $data = [
            'pending' => $pending,
            'confirmed' => $confirmed,
            'processing' => $processing,
            'out_for_delivery' => $outForDelivery,
            'delivered' => $delivered,
            'all' => $all,
            'returned' => $returned,
            'failed' => $failed,
            'canceled' => $canceled
        ];

        return $data;
    }


    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function orderStatistics(Request $request): JsonResponse
    {
        $dateType = $request->type;

        $orderData = array();
        if ($dateType == 'yearOrder') {
            $number = 12;
            $from = Carbon::now()->startOfYear()->format('Y-m-d');
            $to = Carbon::now()->endOfYear()->format('Y-m-d');

            $orders = $this->order->where(['order_status' => 'delivered', 'branch_id' => auth('branch')->id()])
                ->select(
                    DB::raw('(count(id)) as total'),
                    DB::raw('YEAR(created_at) year, MONTH(created_at) month')
                )
                ->whereBetween('created_at', [Carbon::parse(now())->startOfYear(), Carbon::parse(now())->endOfYear()])
                ->groupby('year', 'month')->get()->toArray();

            for ($inc = 1; $inc <= $number; $inc++) {
                $orderData[$inc] = 0;
                foreach ($orders as $match) {
                    if ($match['month'] == $inc) {
                        $orderData[$inc] = $match['total'];
                    }
                }
            }
            $keyRange = array("Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec");

        } elseif ($dateType == 'MonthOrder') {
            $from = date('Y-m-01');
            $to = date('Y-m-t');
            $number = date('d', strtotime($to));
            $keyRange = range(1, $number);

            $orders = $this->order->where(['order_status' => 'delivered', 'branch_id' => auth('branch')->id()])
                ->select(
                    DB::raw('(count(id)) as total'),
                    DB::raw('YEAR(created_at) year, MONTH(created_at) month, DAY(created_at) day')
                )
                ->whereBetween('created_at', [Carbon::parse(now())->startOfMonth(), Carbon::parse(now())->endOfMonth()])
                ->groupby('created_at')
                ->get()
                ->toArray();

            for ($inc = 1; $inc <= $number; $inc++) {
                $orderData[$inc] = 0;
                foreach ($orders as $match) {
                    if ($match['day'] == $inc) {
                        $orderData[$inc] += $match['total'];
                    }
                }
            }

        } elseif ($dateType == 'WeekOrder') {
            $from = Carbon::now()->startOfWeek(Carbon::SUNDAY);
            $to = Carbon::now()->endOfWeek(Carbon::SATURDAY);

            $orders = $this->order->where(['order_status' => 'delivered', 'branch_id' => auth('branch')->id()])
                ->whereBetween('created_at', [$from, $to])->get();

            $datRange = CarbonPeriod::create($from, $to)->toArray();
            $keyRange = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
            $orderData = [];
            foreach ($datRange as $date) {

                $orderData[] = $orders->whereBetween('created_at', [$date, Carbon::parse($date)->endOfDay()])->count();
            }
        }

        $label = $keyRange;
        $orderDataFinal = $orderData;

        $data = array(
            'orders_label' => $label,
            'orders' => array_values($orderDataFinal),
        );

        return response()->json($data);
    }


    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function earningStatistics(Request $request): JsonResponse
    {
        $dateType = $request->type;

        $earningData = array();
        if ($dateType == 'yearEarn') {

            $earning = [];
            $earningData = $this->order->where(['order_status' => 'delivered', 'branch_id' => auth('branch')->id()])->select(
                    DB::raw('IFNULL(sum(order_amount),0) as sums'),
                    DB::raw('YEAR(created_at) year, MONTH(created_at) month')
                )
                ->whereBetween('created_at', [Carbon::parse(now())->startOfYear(), Carbon::parse(now())->endOfYear()])
                ->groupby('year', 'month')->get()->toArray();

            for ($inc = 1; $inc <= 12; $inc++) {
                $earning[$inc] = 0;
                foreach ($earningData as $match) {
                    if ($match['month'] == $inc) {
                        $earning[$inc] = Helpers::set_price($match['sums']);
                    }
                }
            }

            $keyRange = array("Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec");
            $orderData = $earning;

        } elseif ($dateType == 'MonthEarn') {
            $from = date('Y-m-01');
            $to = date('Y-m-t');
            $number = date('d', strtotime($to));
            $keyRange = range(1, $number);

            $earning = $this->order
                ->where(['order_status' => 'delivered', 'branch_id' => auth('branch')->id()])
                ->select(DB::raw('IFNULL(sum(order_amount),0) as sums'), DB::raw('YEAR(created_at) year, MONTH(created_at) month, DAY(created_at) day'))
                ->whereBetween('created_at', [Carbon::parse(now())->startOfMonth(), Carbon::parse(now())->endOfMonth()])
                ->groupby('created_at')
                ->get()
                ->toArray();

            for ($inc = 1; $inc <= $number; $inc++) {
                $earningData[$inc] = 0;
                foreach ($earning as $match) {
                    if ($match['day'] == $inc) {
                        $earningData[$inc] += $match['sums'];
                    }
                }
            }

            $orderData = $earningData;
        } elseif ($dateType == 'WeekEarn') {
            $from = Carbon::now()->startOfWeek(Carbon::SUNDAY);
            $to = Carbon::now()->endOfWeek(Carbon::SATURDAY);

            $orders = $this->order
                ->where(['order_status' => 'delivered', 'branch_id' => auth('branch')->id()])
                ->whereBetween('created_at', [$from, $to])->get();

            $datRange = CarbonPeriod::create($from, $to)->toArray();
            $keyRange = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
            $orderData = [];
            foreach ($datRange as $date) {
                $orderData[] = $orders->whereBetween('created_at', [$date, Carbon::parse($date)->endOfDay()])->sum('order_amount');
            }
        }

        $label = $keyRange;
        $earningDataFinal = $orderData;

        $data = array(
            'earning_label' => $label,
            'earning' => array_values($earningDataFinal),
        );

        return response()->json($data);
    }

}

