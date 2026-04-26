<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\Admin;
use App\Model\Branch;
use App\Model\Category;
use App\Model\Order;
use App\Model\OrderDetail;
use App\Model\Product;
use App\Model\ProductByBranch;
use App\Model\Review;
use App\Model\Table;
use App\User;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\Support\Renderable;

class DashboardController extends Controller
{
    public function __construct(
        private Order       $order,
        private OrderDetail $orderDetail,
        private Admin       $admin,
        private Review      $review,
        private User        $user,
        private Product     $product,
        private Category    $category,
        private Branch      $branch
    )
    {}

    /**
     * Dashboard home for admin/owner. Built around four bands that an
     * operator actually checks first: today's snapshot, live operations,
     * the revenue trend, and what's selling. Every aggregate below is a
     * single SQL roundtrip — the previous version loaded the entire
     * orders table into PHP just to draw a pie chart.
     *
     * @return Renderable
     */
    public function dashboard(): Renderable
    {
        Helpers::update_daily_product_stock();

        $today      = Carbon::today();
        $yesterday  = Carbon::yesterday();
        $currency   = Helpers::currency_code();

        $snapshot   = $this->snapshotForRange($today, $yesterday);
        $live       = $this->liveOpsFunnel();
        $trend      = $this->revenueTrend();
        $topToday   = $this->topDishesToday($today);
        $recent     = $this->recentOrders(5);
        $alerts     = $this->operationalAlerts($today);

        return view('admin-views.dashboard', compact(
            'snapshot', 'live', 'trend', 'topToday', 'recent', 'alerts', 'currency'
        ));
    }

    /**
     * Today's headline KPIs with vs-yesterday delta. One query per metric;
     * no in-memory filtering. Revenue excludes canceled/failed orders so
     * the number matches what the cash drawer reports.
     */
    private function snapshotForRange(Carbon $today, Carbon $yesterday, ?int $branchId = null): array
    {
        $base = fn () => $this->order->newQuery()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

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

        // Tables in use right now: any dine-in order whose table is still
        // open (not paid, not closed). Stays accurate even if a table
        // turns over multiple times in the day.
        $tablesInUse = $base()
            ->where('order_type', 'dine_in')
            ->where('payment_status', '!=', 'paid')
            ->whereNotIn('order_status', ['completed', 'canceled', 'failed'])
            ->whereNotNull('table_id')
            ->distinct('table_id')
            ->count('table_id');

        $totalTables = Table::query()
            ->where('is_active', 1)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
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

    /**
     * In-flight order funnel — what's happening on the floor right now,
     * not historical totals. Each bucket is clickable into the matching
     * Active Orders filter on the front-end.
     */
    private function liveOpsFunnel(?int $branchId = null): array
    {
        $base = fn () => $this->order->newQuery()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

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

    /**
     * Revenue + order-count trend, prebuilt for three ranges so the UI
     * can swap on the client without an AJAX roundtrip. Each range is
     * one grouped query; missing days are zero-filled in PHP.
     */
    private function revenueTrend(?int $branchId = null): array
    {
        return [
            '7d'  => $this->trendByDay(7, $branchId),
            '30d' => $this->trendByDay(30, $branchId),
            '12m' => $this->trendByMonth(12, $branchId),
        ];
    }

    private function trendByDay(int $days, ?int $branchId): array
    {
        $start = Carbon::now()->subDays($days - 1)->startOfDay();

        $rows = $this->order->newQuery()
            ->where('order_status', '!=', 'canceled')
            ->where('created_at', '>=', $start)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
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

    private function trendByMonth(int $months, ?int $branchId): array
    {
        $start = Carbon::now()->startOfMonth()->subMonths($months - 1);

        $rows = $this->order->newQuery()
            ->where('order_status', '!=', 'canceled')
            ->where('created_at', '>=', $start)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
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

    private function topDishesToday(Carbon $today, ?int $branchId = null): \Illuminate\Support\Collection
    {
        return $this->orderDetail->newQuery()
            ->whereHas('order', function ($q) use ($today, $branchId) {
                $q->whereDate('created_at', $today)
                  ->whereNotIn('order_status', ['canceled', 'failed'])
                  ->when($branchId, fn ($qq) => $qq->where('branch_id', $branchId));
            })
            ->with('product:id,name,image')
            ->select('product_id', DB::raw('SUM(quantity) as qty'), DB::raw('SUM(quantity * price) as revenue'))
            ->groupBy('product_id')
            ->orderByDesc('qty')
            ->take(5)
            ->get();
    }

    private function recentOrders(int $limit, ?int $branchId = null): \Illuminate\Database\Eloquent\Collection
    {
        return $this->order->newQuery()
            ->with(['customer:id,f_name,l_name', 'table:id,number'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->latest()
            ->take($limit)
            ->get(['id', 'user_id', 'table_id', 'order_type', 'order_status', 'payment_status', 'order_amount', 'created_at']);
    }

    /**
     * Three operational alert cards. Wired to live queries even though
     * data may be empty today — that way the moment stock gets low or
     * a refund is filed, the cards light up without a code change.
     */
    private function operationalAlerts(Carbon $today, ?int $branchId = null): array
    {
        $lowStockThreshold = 5;

        $lowStock = ProductByBranch::query()
            ->where('stock_type', '!=', 'unlimited')
            ->where('stock', '<=', $lowStockThreshold)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->count();

        // Refunds aren't a separate table yet — surface the closest signal:
        // orders flagged 'returned' or marked completed but unpaid (i.e. a
        // balance the customer is owed back / still owes). Real refund
        // workflow can swap in here later.
        $pendingRefunds = $this->order->newQuery()
            ->where(function ($q) {
                $q->where('order_status', 'returned')
                  ->orWhere(function ($qq) {
                      $qq->whereIn('order_status', ['delivered', 'completed'])
                         ->where('payment_status', '!=', 'paid');
                  });
            })
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->count();

        // Staff "on shift" today = anyone who actually rang an order.
        // Best signal we have without a real shift table.
        $staffOnShift = $this->order->newQuery()
            ->whereDate('created_at', $today)
            ->whereNotNull('placed_by_admin_id')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->distinct('placed_by_admin_id')
            ->count('placed_by_admin_id');

        return [
            'low_stock'        => $lowStock,
            'pending_refunds'  => $pendingRefunds,
            'staff_on_shift'   => $staffOnShift,
        ];
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function orderStats(Request $request): JsonResponse
    {
        session()->put('statistics_type', $request['statistics_type']);
        $data = self::orderStatsData();

        return response()->json([
            'view' => view('admin-views.partials._dashboard-order-stats', compact('data'))->render()
        ], 200);
    }

    /**
     * @return array
     */
    public function orderStatsData(): array
    {
        $today = session()->has('statistics_type') && session('statistics_type') == 'today' ? 1 : 0;
        $this_month = session()->has('statistics_type') && session('statistics_type') == 'this_month' ? 1 : 0;

        $pending = $this->order
            ->where(['order_status' => 'pending'])
            ->notSchedule()
            ->when($today, function ($query) {
                return $query->whereDate('created_at', \Carbon\Carbon::today());
            })
            ->when($this_month, function ($query) {
                return $query->whereMonth('created_at', Carbon::now());
            })
            ->count();
        $confirmed = $this->order
            ->where(['order_status' => 'confirmed'])
            ->when($today, function ($query) {
                return $query->whereDate('created_at', Carbon::today());
            })
            ->when($this_month, function ($query) {
                return $query->whereMonth('created_at', Carbon::now());
            })
            ->count();
        $processing = $this->order
            ->where(['order_status' => 'processing'])
            ->when($today, function ($query) {
                return $query->whereDate('created_at', Carbon::today());
            })
            ->when($this_month, function ($query) {
                return $query->whereMonth('created_at', Carbon::now());
            })
            ->count();
        $outForDelivery = $this->order
            ->where(['order_status' => 'out_for_delivery'])
            ->when($today, function ($query) {
                return $query->whereDate('created_at', Carbon::today());
            })
            ->when($this_month, function ($query) {
                return $query->whereMonth('created_at', Carbon::now());
            })
            ->count();
        $canceled = $this->order
            ->where(['order_status' => 'canceled'])
            ->when($today, function ($query) {
                return $query->whereDate('created_at', Carbon::today());
            })
            ->when($this_month, function ($query) {
                return $query->whereMonth('created_at', Carbon::now());
            })
            ->count();
        $delivered = $this->order
            ->where(['order_status' => 'delivered'])
            ->when($today, function ($query) {
                return $query->whereDate('created_at', Carbon::today());
            })
            ->when($this_month, function ($query) {
                return $query->whereMonth('created_at', Carbon::now());
            })
            ->count();
        $all = $this->order
            ->when($today, function ($query) {
                return $query->whereDate('created_at', Carbon::today());
            })
            ->when($this_month, function ($query) {
                return $query->whereMonth('created_at', Carbon::now());
            })
            ->count();
        $returned = $this->order
            ->where(['order_status' => 'returned'])
            ->when($today, function ($query) {
                return $query->whereDate('created_at', Carbon::today());
            })
            ->when($this_month, function ($query) {
                return $query->whereMonth('created_at', Carbon::now());
            })
            ->count();
        $failed = $this->order
            ->where(['order_status' => 'failed'])
            ->when($today, function ($query) {
                return $query->whereDate('created_at', Carbon::today());
            })
            ->when($this_month, function ($query) {
                return $query->whereMonth('created_at', Carbon::now());
            })
            ->count();

        return [
            'pending' => $pending,
            'confirmed' => $confirmed,
            'processing' => $processing,
            'out_for_delivery' => $outForDelivery,
            'canceled' => $canceled,
            'delivered' => $delivered,
            'all' => $all,
            'returned' => $returned,
            'failed' => $failed
        ];
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

            $orders = $this->order->where(['order_status' => 'delivered'])
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


            $orders = $this->order->where(['order_status' => 'delivered'])
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

            $orders = $this->order->where(['order_status' => 'delivered'])
                ->whereBetween('created_at', [$from, $to])->get();

            $dateRange = CarbonPeriod::create($from, $to)->toArray();
            $keyRange = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
            $orderData = [];
            foreach ($dateRange as $date) {

                $orderData[] = $orders->whereBetween('created_at', [$date, Carbon::parse($date)->endOfDay()])->count();
            }
        }

        $label = $keyRange;
        $finalOrderData = $orderData;

        $data = array(
            'orders_label' => $label,
            'orders' => array_values($finalOrderData),
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
            $earningData = $this->order->where([
                'order_status' => 'delivered',
            ])->select(
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

            $earning = $this->order->where(['order_status' => 'delivered'])
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

            $orders = $this->order->where(['order_status' => 'delivered'])->whereBetween('created_at', [$from, $to])->get();

            $dateRange = CarbonPeriod::create($from, $to)->toArray();
            $keyRange = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
            $orderData = [];
            foreach ($dateRange as $date) {
                $orderData[] = $orders->whereBetween('created_at', [$date, Carbon::parse($date)->endOfDay()])->sum('order_amount');
            }
        }

        $label = $keyRange;
        $finalEarningData = $orderData;

        $data = array(
            'earning_label' => $label,
            'earning' => array_values($finalEarningData),
        );
        return response()->json($data);
    }


}
