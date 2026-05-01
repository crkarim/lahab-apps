<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Model\Order;
use App\Model\OrderDetail;
use Barryvdh\DomPDF\Facade as PDF;
use Brian2694\Toastr\Facades\Toastr;
use Carbon\Carbon;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\Support\Renderable;

class ReportController extends Controller
{
    public function __construct(
        private Order       $order,
        private OrderDetail $orderDetail,
    )
    {
    }

    /**
     * @return Renderable
     */
    public function orderIndex(): Renderable
    {
        if (session()->has('from_date') == false) {
            session()->put('from_date', date('Y-m-01'));
            session()->put('to_date', date('Y-m-30'));
        }

        return view('admin-views.report.order-index');
    }

    /**
     * @param Request $request
     * @return Renderable
     */
    public function earningIndex(Request $request): Renderable
    {
        $from = Carbon::parse($request->from)->startOfDay();
        $to = Carbon::parse($request->to)->endOfDay();

        if ($request->from > $request->to) {
            Toastr::warning(translate('Invalid date range!'));
        }

        $startDate = $request->from;
        $endDate = $request->to;

        $orders = $this->order->where(['order_status' => 'delivered'])
            ->when($request->from && $request->to, function ($q) use ($from, $to) {
                session()->put('from_date', $from);
                session()->put('to_date', $to);
                $q->whereBetween('created_at', [$from, $to]);
            })->get();

        $addonTaxAmount = 0;

        foreach ($orders as $order) {
            foreach ($order->details as $detail) {
                $addonTaxAmount += $detail->add_on_tax_amount;
            }
        }

        $productTax = $orders->sum('total_tax_amount');
        $total_tax = $productTax + $addonTaxAmount;
        $total_sold = $orders->sum('order_amount');

        if ($startDate == null) {
            session()->put('from_date', date('Y-m-01'));
            session()->put('to_date', date('Y-m-30'));
        }

        return view('admin-views.report.earning-index', compact('total_tax', 'total_sold', 'from', 'to', 'startDate', 'endDate'));
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function setDate(Request $request): RedirectResponse
    {
        $fromDate = Carbon::parse($request['from'])->startOfDay();
        $toDate = Carbon::parse($request['to'])->endOfDay();

        session()->put('from_date', $fromDate);
        session()->put('to_date', $toDate);

        return back();
    }

    /**
     * @return Renderable
     */
    public function deliverymanReport(): Renderable
    {
        $orders = $this->order->with(['customer', 'branch'])->paginate(25);
        return view('admin-views.report.driver-index', compact('orders'));
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function deliverymanFilter(Request $request): JsonResponse
    {
        $fromDate = Carbon::parse($request->formDate)->startOfDay();
        $toDate = Carbon::parse($request->toDate)->endOfDay();

        $orders = $this->order
            ->where(['delivery_man_id' => $request['delivery_man']])
            ->where(['order_status' => 'delivered'])
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->get();

        return response()->json([
            'view' => view('admin-views.order.partials._table', compact('orders'))->render(),
            'delivered_qty' => $orders->count()
        ]);
    }

    /**
     * @return Renderable
     */
    public function productReport(): Renderable
    {
        return view('admin-views.report.product-report');
    }

    /**
     * Day-End Report — close-of-day reconciliation surface for the
     * cashier. Three sections: top-line summary (orders, gross, tips,
     * net), per-method payment breakdown (cash / card / bKash / etc.
     * — to match the drawer at close), and per-waiter sheet
     * (orders + revenue + tips for tip distribution).
     *
     * Branch scoping: HQ admins (admins.branch_id IS NULL) can pick
     * "all" or a specific branch via the dropdown. Branch-scoped admins
     * see their own branch only.
     *
     * NOTE: report scopes "all sales today" — once Shifts ship, retro-fit
     * by adding a `shift_id` filter and joining `orders.shift_id`.
     */
    public function dayEnd(Request $request): Renderable
    {
        $admin = auth('admin')->user();
        $forcedBranch = $admin?->branch_id;

        $date = $request->query('date')
            ? Carbon::parse($request->query('date'))
            : Carbon::today();
        $branchId = $forcedBranch ?? $request->query('branch_id', 'all');

        $start = (clone $date)->startOfDay();
        $end   = (clone $date)->endOfDay();

        $orders = Order::query()
            ->with(['order_partial_payments', 'placedBy:id,f_name,l_name', 'branch:id,name'])
            ->whereBetween('created_at', [$start, $end])
            ->where('payment_status', 'paid')
            ->when($branchId !== 'all', fn ($q) => $q->where('branch_id', $branchId))
            ->get();

        // Summary (top-line totals)
        $orderCount = $orders->count();
        $grossSales = $orders->sum(fn ($o) => (float) $o->order_amount);
        $totalTax   = $orders->sum(fn ($o) => (float) ($o->total_tax_amount ?? 0));
        $totalDiscount = $orders->sum(fn ($o) =>
            (float) ($o->extra_discount ?? 0)
            + (float) ($o->coupon_discount_amount ?? 0)
            + (float) ($o->referral_discount ?? 0)
        );
        $totalTips  = $orders->sum(fn ($o) => (float) ($o->tip_amount ?? 0));

        // Per-account payment breakdown — Phase 8.5 split. Group inflows
        // by cash_account_id so EBL vs DBBL show separately. Falls back
        // to the legacy paid_with string for rows that don't have an
        // account picked yet (older sales / API entries).
        $accountInflows = []; // [keyLabel => total]
        $methodInflows  = []; // [legacyMethod => total] for unmatched rows
        $accountIdsTouched = [];
        foreach ($orders as $o) {
            foreach ($o->order_partial_payments as $p) {
                $amt = (float) $p->paid_amount;
                if (!empty($p->cash_account_id)) {
                    $accountIdsTouched[(int) $p->cash_account_id] = true;
                    $key = (int) $p->cash_account_id;
                    $accountInflows[$key] = ($accountInflows[$key] ?? 0) + $amt;
                } else {
                    $key = $p->paid_with ?: 'unknown';
                    $methodInflows[$key] = ($methodInflows[$key] ?? 0) + $amt;
                }
            }
            // Orders without partial payment rows fall back to payment_method.
            if ($o->order_partial_payments->isEmpty() && $o->payment_method) {
                $payable = (float) $o->order_amount + (float) ($o->tip_amount ?? 0);
                $methodInflows[$o->payment_method] = ($methodInflows[$o->payment_method] ?? 0) + $payable;
            }
        }
        ksort($methodInflows);

        // Resolve account ids → CashAccount rows for display labels.
        $accountsById = [];
        if (!empty($accountIdsTouched)) {
            try {
                $accountsById = \App\Models\CashAccount::query()
                    ->whereIn('id', array_keys($accountIdsTouched))
                    ->get(['id', 'name', 'type', 'color', 'account_number'])
                    ->keyBy('id');
            } catch (\Throwable $e) { /* pre-migration */ }
        }

        // Outflows panel — sum every OUT row in the ledger today, grouped
        // by ref_type so the cashier sees where cash left during the day
        // (supplier bills, payslip payouts, salary advances, drawer
        // shortages, manual adjustments). Phase 8.5 + 8.6 + 8.5c sources.
        $outflows = [];
        $outflowsTotal = 0;
        try {
            $rows = \App\Models\AccountTransaction::query()
                ->where('direction', 'out')
                ->whereBetween('transacted_at', [$start, $end])
                ->when($branchId !== 'all', fn ($q) => $q->where('branch_id', $branchId))
                ->select('ref_type', \DB::raw('COALESCE(SUM(amount), 0) AS amt'), \DB::raw('COUNT(*) AS row_count'))
                ->groupBy('ref_type')
                ->get();
            $labels = [
                'expense_payment'         => 'Supplier bills',
                'salary_advance'          => 'Salary advances given',
                'salary_advance_recovery' => 'Advance recoveries (in)', // shouldn't appear since out-only
                'payslip'                 => 'Salaries paid',
                'shift'                   => 'Drawer shortages',
                'pos_order_change'        => 'Change returned to customers',
                ''                        => 'Manual cash-out',
            ];
            foreach ($rows as $r) {
                $key = (string) ($r->ref_type ?? '');
                $outflows[] = [
                    'label' => $labels[$key] ?? ('Other (' . ($key ?: 'manual') . ')'),
                    'count' => (int) $r->row_count,
                    'amount' => (float) $r->amt,
                ];
                $outflowsTotal += (float) $r->amt;
            }
            usort($outflows, fn ($a, $b) => $b['amount'] <=> $a['amount']);
        } catch (\Throwable $e) { /* pre-migration */ }

        // Per-waiter sheet — tip distribution lives here.
        $waiters = [];
        foreach ($orders as $o) {
            $waiterId = $o->placed_by_admin_id ?? 0;
            $name = $o->placedBy
                ? trim(($o->placedBy->f_name ?? '') . ' ' . ($o->placedBy->l_name ?? ''))
                : 'Unassigned';
            if (!isset($waiters[$waiterId])) {
                $waiters[$waiterId] = ['name' => $name ?: 'Unassigned', 'orders' => 0, 'revenue' => 0, 'tips' => 0];
            }
            $waiters[$waiterId]['orders']++;
            $waiters[$waiterId]['revenue'] += (float) $o->order_amount;
            $waiters[$waiterId]['tips']    += (float) ($o->tip_amount ?? 0);
        }
        uasort($waiters, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

        $branches = $forcedBranch
            ? \App\Model\Branch::where('id', $forcedBranch)->get(['id', 'name'])
            : \App\Model\Branch::orderBy('name')->get(['id', 'name']);

        // Handover reconciliation — surfaces three signals at the bottom
        // of the report:
        //   - cash submitted by waiters today (sum of cash_handovers.total_cash + total_tips)
        //   - cash received by cashier today (same, scoped to status=received)
        //   - any handovers still pending (red flag — money in flight)
        $handoverScope = \App\Models\CashHandover::query()
            ->whereBetween('submitted_at', [$start, $end])
            ->when($branchId !== 'all', fn ($q) => $q->where('branch_id', $branchId));
        // total_tips is a slice of total_cash, not additive. Drawer
        // figure is total_cash alone — that's the actual money.
        $hSubmitted = (clone $handoverScope)->sum('total_cash');
        $hReceived  = (clone $handoverScope)->where('status', 'received')->sum('total_cash');
        $hPendingRows = (clone $handoverScope)
            ->where('status', 'pending')
            ->with('waiter:id,f_name,l_name')
            ->orderByDesc('submitted_at')
            ->get();
        $hDisputedRows = (clone $handoverScope)
            ->where('status', 'disputed')
            ->with(['waiter:id,f_name,l_name', 'cashier:id,f_name,l_name'])
            ->orderByDesc('updated_at')
            ->get();

        return view('admin-views.report.day-end', [
            'date'              => $date->format('Y-m-d'),
            'dateHuman'         => $date->format('d M Y'),
            'branchId'          => $branchId,
            'forcedBranch'      => $forcedBranch,
            'branches'          => $branches,
            'orderCount'        => $orderCount,
            'grossSales'        => $grossSales,
            'totalTax'          => $totalTax,
            'totalDiscount'     => $totalDiscount,
            'totalTips'         => $totalTips,
            'netSales'          => $grossSales + $totalTips - $totalDiscount,
            'accountInflows'    => $accountInflows,
            'methodInflows'     => $methodInflows,
            'accountsById'      => $accountsById,
            'totalInflowsAccountSide' => array_sum($accountInflows) + array_sum($methodInflows),
            'outflows'          => $outflows,
            'outflowsTotal'     => $outflowsTotal,
            'waiters'           => array_values($waiters),
            'hSubmitted'        => (float) $hSubmitted,
            'hReceived'         => (float) $hReceived,
            'hPendingRows'      => $hPendingRows,
            'hDisputedRows'     => $hDisputedRows,
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function productReportFilter(Request $request): JsonResponse
    {
        $fromDate = Carbon::parse($request->from)->startOfDay();
        $toDate = Carbon::parse($request->to)->endOfDay();

        $orders = $this->order->when($request['branch_id'] != 'all', function ($query) use ($request) {
            $query->where('branch_id', $request['branch_id']);
        })
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->latest()
            ->get();

        $data = [];
        $totalSold = 0;
        $totalQuantity = 0;
        foreach ($orders as $order) {
            foreach ($order->details as $detail) {
                if ($request['product_id'] != 'all') {
                    if ($detail['product_id'] == $request['product_id']) {
                        $price = Helpers::variation_price(json_decode($detail->product_details, true), $detail['variations']) - $detail['discount_on_product'];
                        $orderTotal = $price * $detail['quantity'];
                        $data[] = [
                            'order_id' => $order['id'],
                            'date' => $order['created_at'],
                            'customer' => $order->customer,
                            'price' => $orderTotal,
                            'quantity' => $detail['quantity'],
                        ];
                        $totalSold += $orderTotal;
                        $totalQuantity += $detail['quantity'];
                    }

                } else {
                    $price = Helpers::variation_price(json_decode($detail->product_details, true), $detail['variations']) - $detail['discount_on_product'];
                    $orderTotal = $price * $detail['quantity'];
                    $data[] = [
                        'order_id' => $order['id'],
                        'date' => $order['created_at'],
                        'customer' => $order->customer,
                        'price' => $orderTotal,
                        'quantity' => $detail['quantity'],
                    ];
                    $totalSold += $orderTotal;
                    $totalQuantity += $detail['quantity'];
                }
            }
        }

        session()->put('export_data', $data);

        return response()->json([
            'order_count' => count($data),
            'item_qty' => $totalQuantity,
            'order_sum' => Helpers::set_symbol($totalSold),
            'view' => view('admin-views.report.partials._table', compact('data'))->render(),
        ]);
    }

    /**
     * @return mixed
     */
    public function exportProductReport(): mixed
    {
        if (session()->has('export_data')) {
            $data = session('export_data');

        } else {
            $orders = $this->order->all();
            $data = [];
            $totalSold = 0;
            $totalQuantity = 0;
            foreach ($orders as $order) {
                foreach ($order->details as $detail) {
                    $price = Helpers::variation_price(json_decode($detail->product_details, true), $detail['variations']) - $detail['discount_on_product'];
                    $orderTotal = $price * $detail['quantity'];
                    $data[] = [
                        'order_id' => $order['id'],
                        'date' => $order['created_at'],
                        'customer' => $order->customer,
                        'price' => $orderTotal,
                        'quantity' => $detail['quantity'],
                    ];
                    $totalSold += $orderTotal;
                    $totalQuantity += $detail['quantity'];
                }
            }
        }

        $pdf = PDF::loadView('admin-views.report.partials._report', compact('data'));
        return $pdf->download('report_' . rand(00001, 99999) . '.pdf');
    }

    /**
     * @return Application|Factory|View
     */
    public function saleReport(): Factory|View|Application
    {
        return view('admin-views.report.sale-report');
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function saleFilter(Request $request): JsonResponse
    {
        $fromDate = Carbon::parse($request->from)->startOfDay();
        $toDate = Carbon::parse($request->to)->endOfDay();

        if ($request['branch_id'] == 'all') {
            $orders = $this->order->whereBetween('created_at', [$fromDate, $toDate])->pluck('id')->toArray();

        } else {
            $orders = $this->order
                ->where(['branch_id' => $request['branch_id']])
                ->whereBetween('created_at', [$fromDate, $toDate])
                ->pluck('id')
                ->toArray();
        }

        $data = [];
        $totalSold = 0;
        $totalQuantity = 0;

        foreach ($this->orderDetail->whereIn('order_id', $orders)->latest()->get() as $detail) {
            $price = $detail['price'] - $detail['discount_on_product'];
            $orderTotal = $price * $detail['quantity'];
            $data[] = [
                'order_id' => $detail['order_id'],
                'date' => $detail['created_at'],
                'price' => $orderTotal,
                'quantity' => $detail['quantity'],
            ];
            $totalSold += $orderTotal;
            $totalQuantity += $detail['quantity'];
        }

        return response()->json([
            'order_count' => count($data),
            'item_qty' => $totalQuantity,
            'order_sum' => Helpers::set_symbol($totalSold),
            'view' => view('admin-views.report.partials._table', compact('data'))->render(),
        ]);
    }

    /**
     * @return mixed
     */
    public function exportSaleReport(): mixed
    {
        $data = session('export_sale_data');
        $pdf = PDF::loadView('admin-views.report.partials._report', compact('data'));

        return $pdf->download('sale_report_' . rand(00001, 99999) . '.pdf');
    }
}
