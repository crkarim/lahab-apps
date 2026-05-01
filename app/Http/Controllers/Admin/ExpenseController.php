<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashAccount;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseLine;
use App\Models\ExpensePayment;
use App\Models\Supplier;
use App\Services\Accounts\PostExpensePaymentToLedger;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Phase 8.6 — Bill / expense entry + payment.
 *
 * Workflow:
 *   1. Create — header (supplier / category / dates / branch) + line items.
 *      Line totals roll up into expense.subtotal/total. Status starts pending.
 *   2. Show — header + line items + payments. "Add payment" form posts a
 *      partial-pay row. Service auto-posts to ledger from chosen account.
 *   3. Status flips automatically: pending → partial → paid as payments
 *      accumulate. Cancel zeroes balance + excludes from supplier balance.
 *
 * Branch isolation: managers see + book bills for their branch only;
 * Master Admin sees everything.
 */
class ExpenseController extends Controller
{
    public function index(Request $request): Renderable
    {
        $admin    = auth('admin')->user();
        $isMaster = (int) ($admin?->admin_role_id ?? 0) === 1;
        $branchId = $admin?->branch_id;

        $from = $request->date('from') ?? now()->startOfMonth();
        $to   = $request->date('to')   ?? now()->endOfDay();
        $statusFilter = $request->input('status');
        $supplierFilter = $request->input('supplier_id');

        $expenses = Expense::query()
            ->with(['supplier:id,name', 'category:id,name,color,parent_id', 'category.parent:id,name', 'branch:id,name'])
            ->when(!$isMaster && $branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereBetween('bill_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($statusFilter, fn ($q) => $q->where('status', $statusFilter))
            ->when($supplierFilter, fn ($q) => $q->where('supplier_id', $supplierFilter))
            ->orderByDesc('bill_date')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $suppliers = Supplier::visibleTo($isMaster ? null : $branchId)->get(['id', 'name']);

        $totals = [
            'count'   => $expenses->count(),
            'billed'  => (float) $expenses->whereIn('status', ['pending', 'partial', 'paid'])->sum('total'),
            'paid'    => (float) $expenses->whereIn('status', ['pending', 'partial', 'paid'])->sum('paid_amount'),
        ];
        $totals['outstanding'] = round($totals['billed'] - $totals['paid'], 2);

        return view('admin-views.expense.index', [
            'expenses'        => $expenses,
            'suppliers'       => $suppliers,
            'from'            => $from,
            'to'              => $to,
            'statusFilter'    => $statusFilter,
            'supplierFilter'  => $supplierFilter,
            'totals'          => $totals,
        ]);
    }

    public function create(Request $request): Renderable
    {
        $admin    = auth('admin')->user();
        $isMaster = (int) ($admin?->admin_role_id ?? 0) === 1;
        $branchId = $admin?->branch_id;

        $suppliers  = Supplier::visibleTo($isMaster ? null : $branchId)->get();
        $categories = ExpenseCategory::query()->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();
        $branches   = \App\Model\Branch::query()->orderBy('name')->get(['id', 'name']);

        return view('admin-views.expense.create', [
            'suppliers'  => $suppliers,
            'categories' => $categories,
            'branches'   => $branches,
            'isMaster'   => $isMaster,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $admin = auth('admin')->user();
        $isMaster = (int) ($admin?->admin_role_id ?? 0) === 1;

        $validated = $request->validate([
            'supplier_id'      => 'nullable|integer|exists:suppliers,id',
            'category_id'      => 'nullable|integer|exists:expense_categories,id',
            'branch_id'        => 'nullable|integer|exists:branches,id',
            'bill_no'          => 'nullable|string|max:80',
            'bill_date'        => 'required|date',
            'due_date'         => 'nullable|date|after_or_equal:bill_date',
            'description'      => 'nullable|string|max:1000',
            'vat_amount'       => 'nullable|numeric|min:0',
            'tax_amount'       => 'nullable|numeric|min:0',
            'discount'         => 'nullable|numeric|min:0',
            'lines'            => 'required|array|min:1',
            'lines.*.description' => 'required|string|max:255',
            'lines.*.quantity'    => 'required|numeric|min:0.001',
            'lines.*.unit_price'  => 'required|numeric|min:0',
            'lines.*.category_id' => 'nullable|integer|exists:expense_categories,id',
        ]);

        $branchId = $validated['branch_id'] ?? ($admin->branch_id ?? null);
        if (!$isMaster) {
            $branchId = $admin?->branch_id;
        }

        $expense = DB::transaction(function () use ($validated, $admin, $branchId) {
            $expense = Expense::create([
                'expense_no'           => Expense::nextExpenseNo(),
                'supplier_id'          => $validated['supplier_id'] ?? null,
                'category_id'          => $validated['category_id'] ?? null,
                'branch_id'            => $branchId,
                'bill_no'              => $validated['bill_no'] ?? null,
                'bill_date'            => $validated['bill_date'],
                'due_date'             => $validated['due_date'] ?? null,
                'subtotal'             => 0,
                'vat_amount'           => (float) ($validated['vat_amount'] ?? 0),
                'tax_amount'           => (float) ($validated['tax_amount'] ?? 0),
                'discount'             => (float) ($validated['discount'] ?? 0),
                'total'                => 0,
                'status'               => 'pending',
                'description'          => $validated['description'] ?? null,
                'recorded_by_admin_id' => $admin?->id,
            ]);

            $subtotal = 0;
            $sort = 10;
            foreach ($validated['lines'] as $line) {
                $qty   = (float) $line['quantity'];
                $price = (float) $line['unit_price'];
                $total = round($qty * $price, 2);
                ExpenseLine::create([
                    'expense_id'  => $expense->id,
                    'category_id' => $line['category_id'] ?? null,
                    'description' => $line['description'],
                    'quantity'    => $qty,
                    'unit_price'  => $price,
                    'line_total'  => $total,
                    'sort_order'  => $sort,
                ]);
                $subtotal += $total;
                $sort += 10;
            }

            $total = round($subtotal + (float) $expense->vat_amount + (float) $expense->tax_amount - (float) $expense->discount, 2);
            $expense->forceFill([
                'subtotal' => $subtotal,
                'total'    => $total,
            ])->save();

            $expense->recompute();

            return $expense;
        });

        return redirect()->route('admin.expenses.show', ['id' => $expense->id])
            ->with('success', 'Bill recorded · ' . $expense->expense_no . ' · Tk ' . number_format($expense->total, 2));
    }

    public function show(int $id): Renderable|RedirectResponse
    {
        $admin    = auth('admin')->user();
        $isMaster = (int) ($admin?->admin_role_id ?? 0) === 1;
        $branchId = $admin?->branch_id;

        $expense = Expense::query()
            ->with(['supplier', 'category.parent', 'branch', 'lines.category', 'payments.cashAccount', 'payments.paidBy', 'recordedBy'])
            ->when(!$isMaster && $branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->find($id);
        if (!$expense) return redirect()->route('admin.expenses.index')->with('error', 'Bill not found.');

        $cashAccountsForPayment = CashAccount::visibleTo($isMaster ? null : $branchId)->get();

        return view('admin-views.expense.show', [
            'expense'                => $expense,
            'cashAccountsForPayment' => $cashAccountsForPayment,
        ]);
    }

    /** Add a payment against the bill. Auto-posts to ledger. */
    public function addPayment(Request $request, int $id): RedirectResponse
    {
        $admin    = auth('admin')->user();
        $isMaster = (int) ($admin?->admin_role_id ?? 0) === 1;
        $branchId = $admin?->branch_id;

        $expense = Expense::query()
            ->when(!$isMaster && $branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->find($id);
        if (!$expense) return back()->with('error', 'Bill not found.');
        if ($expense->status === 'cancelled') return back()->with('error', 'Cannot add payment to a cancelled bill.');

        $validated = $request->validate([
            'amount'          => 'required|numeric|min:0.01',
            'method'          => 'required|in:cash,bank,mobile,cheque',
            'reference'       => 'nullable|string|max:80',
            'paid_at'         => 'nullable|date',
            'cash_account_id' => 'nullable|integer|exists:cash_accounts,id',
            'notes'           => 'nullable|string|max:500',
        ]);

        // Reference required for non-cash methods (matches payslip mark-paid pattern).
        if (in_array($validated['method'], ['bank', 'mobile', 'cheque'], true) && empty($validated['reference'])) {
            return back()->with('error', 'Reference is required for ' . $validated['method'] . ' payments.');
        }

        $balanceDue = $expense->balanceDue();
        if ((float) $validated['amount'] > $balanceDue + 0.005) {
            return back()->with('error', 'Payment exceeds balance due · Tk ' . number_format($balanceDue, 2));
        }

        $payment = DB::transaction(function () use ($expense, $validated, $admin) {
            $payment = ExpensePayment::create([
                'payment_no'       => ExpensePayment::nextPaymentNo(),
                'expense_id'       => $expense->id,
                'cash_account_id'  => $validated['cash_account_id'] ?? null,
                'amount'           => (float) $validated['amount'],
                'method'           => $validated['method'],
                'reference'        => $validated['reference'] ?? null,
                'paid_at'          => $validated['paid_at'] ?? now(),
                'paid_by_admin_id' => $admin?->id,
                'notes'            => $validated['notes'] ?? null,
            ]);
            $expense->recompute();
            return $payment;
        });

        // Auto-post to ledger (best-effort; logs warning if no account).
        try {
            PostExpensePaymentToLedger::for($payment);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Expense payment auto-post crashed', [
                'payment_id' => $payment->id,
                'error'      => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'Payment posted · ' . $payment->payment_no . ' · Tk ' . number_format($payment->amount, 2));
    }

    public function cancel(int $id): RedirectResponse
    {
        $admin    = auth('admin')->user();
        $isMaster = (int) ($admin?->admin_role_id ?? 0) === 1;
        $branchId = $admin?->branch_id;

        $expense = Expense::query()
            ->when(!$isMaster && $branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->find($id);
        if (!$expense) return back()->with('error', 'Bill not found.');

        if ($expense->payments()->exists()) {
            return back()->with('error', 'Cannot cancel — payments already posted. Refund / reverse those first.');
        }

        $expense->status = 'cancelled';
        $expense->save();
        $expense->supplier?->recomputeBalance();
        return back()->with('success', 'Bill cancelled.');
    }
}
