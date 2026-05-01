@extends('layouts.admin.app')

@section('title', translate('Bill') . ' ' . $expense->expense_no)

@section('content')
@php $sym = fn ($v) => \App\CentralLogics\Helpers::set_symbol($v); $balance = $expense->balanceDue(); @endphp

<style>
    .lh-bd-page { max-width: 1100px; margin: 0 auto; }
    .lh-bd-banner {
        background: #1A1A1A; color: #fff;
        border-radius: 16px; padding: 22px 26px; margin-bottom: 14px;
        display: flex; gap: 18px; align-items: center; flex-wrap: wrap;
    }
    .lh-bd-banner h1 { margin: 0; font-size: 20px; font-weight: 800; }
    .lh-bd-banner p { margin: 2px 0 0; color: #9095A0; font-size: 13px; }
    .lh-bd-banner .pill { display: inline-block; padding: 3px 12px; font-size: 11px; font-weight: 800; letter-spacing: 1.2px; border-radius: 999px; }
    .lh-bd-banner .pill.pending   { background:#FFF4E5; color:#B45A0A; }
    .lh-bd-banner .pill.partial   { background:#EEF7FF; color:#4794FF; }
    .lh-bd-banner .pill.paid      { background:#ECFFEF; color:#1E8E3E; }
    .lh-bd-banner .pill.cancelled { background:#404550; color:#9095A0; }

    .lh-bd-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 14px; }
    @media (max-width: 800px) { .lh-bd-grid { grid-template-columns: 1fr; } }

    .lh-card { background:#fff; border:1px solid #E5E7EB; border-radius:12px; padding:16px 18px; margin-bottom:14px; }
    .lh-card h3 { font-size:11px; font-weight:800; letter-spacing:1.4px; color:#6A6A70; text-transform:uppercase; margin:0 0 12px; }

    .lh-table { width:100%; border-collapse:collapse; font-size:13px; }
    .lh-table th { font-size:10px; font-weight:700; color:#6A6A70; text-transform:uppercase; letter-spacing:.8px; padding:8px 10px; border-bottom:1px solid #F0F2F5; text-align:left; }
    .lh-table td { padding:8px 10px; border-bottom:1px solid #F0F2F5; }
    .lh-table tr:last-child td { border-bottom:0; }
    .lh-table .num { font-variant-numeric:tabular-nums; font-weight:700; text-align:right; }
    .lh-table .num.outstanding { color:#C82626; }

    .lh-totals { margin-top:14px; padding-top:14px; border-top:2px solid #1A1A1A; }
    .lh-totals table { width: 100%; max-width: 380px; margin-left: auto; font-size:13px; }
    .lh-totals td { padding: 4px 8px; }
    .lh-totals td.label { color:#6A6A70; text-align:right; }
    .lh-totals td.amt { text-align:right; font-variant-numeric:tabular-nums; font-weight:700; }
    .lh-totals tr.grand td { font-size:16px; padding-top:8px; }

    .lh-meta { font-size:13px; }
    .lh-meta .pair { display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #F0F2F5; }
    .lh-meta .pair:last-child { border-bottom:0; }
    .lh-meta .pair .label { color:#6A6A70; font-weight:600; }
    .lh-meta .pair .val   { color:#1A1A1A; font-weight:700; text-align:right; }

    .lh-pay-form { background:#FAFBFC; border:1px solid #E5E7EB; border-radius:8px; padding:14px; margin-top:10px; }
    .lh-pay-form label { font-size:11px; font-weight:700; color:#6A6A70; }
    .lh-pay-form input, .lh-pay-form select, .lh-pay-form textarea { width:100%; border:1px solid #E5E7EB; border-radius:6px; padding:7px 10px; font-size:13px; margin-top:3px; font-variant-numeric:tabular-nums; }
    .lh-pay-form .form-row { display:flex; gap:8px; margin-bottom:8px; }
    .lh-pay-form .form-row > div { flex:1; }
    .lh-pay-form button { font-weight:700; }

    .lh-empty { padding:14px; text-align:center; color:#6A6A70; font-size:12px; }
    .pay-mono { font-family:monospace; font-size:11px; color:#6A6A70; }
</style>

<div class="lh-bd-page">

    @if(session('error'))<div class="alert alert-soft-danger">{{ session('error') }}</div>@endif
    @if(session('success'))<div class="alert alert-soft-success">{{ session('success') }}</div>@endif

    <div class="lh-bd-banner">
        <span class="pill {{ $expense->status }}">{{ strtoupper($expense->status) }}</span>
        <div style="flex:1;">
            <h1>{{ $expense->expense_no }}@if($expense->bill_no) · {{ $expense->bill_no }}@endif</h1>
            <p>
                {{ $expense->bill_date->format('d M Y') }}
                @if($expense->due_date) · {{ translate('due') }} {{ $expense->due_date->format('d M Y') }}@endif
                @if($expense->branch) · {{ $expense->branch->name }}@endif
                @if($expense->recordedBy) · {{ translate('recorded by') }} {{ trim(($expense->recordedBy->f_name ?? '') . ' ' . ($expense->recordedBy->l_name ?? '')) }}@endif
            </p>
        </div>
        <a href="{{ route('admin.expenses.index') }}" class="btn btn-light btn-sm" style="font-weight:700;">← {{ translate('All bills') }}</a>
    </div>

    <div class="lh-bd-grid">
        <div>
            <div class="lh-card">
                <h3>{{ translate('Line items') }}</h3>
                <table class="lh-table">
                    <thead>
                        <tr>
                            <th>{{ translate('Description') }}</th>
                            <th>{{ translate('Category') }}</th>
                            <th class="num">{{ translate('Qty') }}</th>
                            <th class="num">{{ translate('Unit') }}</th>
                            <th class="num">{{ translate('Total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($expense->lines as $l)
                            <tr>
                                <td>{{ $l->description }}</td>
                                <td style="font-size:11px; color:#6A6A70;">{{ $l->category?->name ?: '—' }}</td>
                                <td class="num" style="font-weight:600;">{{ rtrim(rtrim(number_format($l->quantity, 3, '.', ''), '0'), '.') }}</td>
                                <td class="num">{{ $sym($l->unit_price) }}</td>
                                <td class="num">{{ $sym($l->line_total) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="lh-empty">{{ translate('No line items.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="lh-totals">
                    <table>
                        <tr><td class="label">{{ translate('Subtotal') }}</td><td class="amt">{{ $sym($expense->subtotal) }}</td></tr>
                        @if((float) $expense->vat_amount > 0)<tr><td class="label">{{ translate('VAT') }}</td><td class="amt">{{ $sym($expense->vat_amount) }}</td></tr>@endif
                        @if((float) $expense->tax_amount > 0)<tr><td class="label">{{ translate('Tax / AIT') }}</td><td class="amt">{{ $sym($expense->tax_amount) }}</td></tr>@endif
                        @if((float) $expense->discount > 0)<tr><td class="label">{{ translate('Discount') }}</td><td class="amt">− {{ $sym($expense->discount) }}</td></tr>@endif
                        <tr class="grand"><td class="label">{{ translate('TOTAL') }}</td><td class="amt">{{ $sym($expense->total) }}</td></tr>
                        @if((float) $expense->paid_amount > 0)
                            <tr><td class="label" style="color:#1E8E3E;">{{ translate('Paid') }}</td><td class="amt" style="color:#1E8E3E;">{{ $sym($expense->paid_amount) }}</td></tr>
                            <tr class="grand" style="border-top:1px solid #C82626;"><td class="label" style="color:#C82626;">{{ translate('Outstanding') }}</td><td class="amt" style="color:#C82626;">{{ $sym($balance) }}</td></tr>
                        @endif
                    </table>
                </div>

                @if($expense->description)
                    <div style="margin-top:14px; padding:10px 14px; background:#F4F6F8; border-radius:6px; font-size:12px; color:#1A1A1A;">
                        {{ $expense->description }}
                    </div>
                @endif
            </div>

            <div class="lh-card">
                <h3>{{ translate('Payments') }} <small style="font-weight:600; color:#1A1A1A; margin-left:6px;">{{ $expense->payments->count() }}</small></h3>

                <table class="lh-table">
                    <thead>
                        <tr>
                            <th>{{ translate('When') }}</th>
                            <th>{{ translate('Pay #') }}</th>
                            <th>{{ translate('Method') }}</th>
                            <th>{{ translate('Account') }}</th>
                            <th>{{ translate('Reference') }}</th>
                            <th class="num">{{ translate('Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($expense->payments as $p)
                        <tr>
                            <td style="font-size:11px;">{{ optional($p->paid_at)->format('d M, H:i') }}</td>
                            <td><span class="pay-mono">{{ $p->payment_no }}</span></td>
                            <td>{{ strtoupper($p->method) }}</td>
                            <td style="font-size:12px;">{{ $p->cashAccount?->name ?: '—' }}</td>
                            <td class="pay-mono">{{ $p->reference ?: '—' }}</td>
                            <td class="num" style="color:#1E8E3E;">{{ $sym($p->amount) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="lh-empty">{{ translate('No payments yet — add one on the right.') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <div class="lh-card">
                <h3>{{ translate('Supplier') }}</h3>
                @if($expense->supplier)
                    <div class="lh-meta">
                        <div class="pair"><span class="label">{{ translate('Name') }}</span><span class="val">{{ $expense->supplier->name }}</span></div>
                        @if($expense->supplier->contact_person)<div class="pair"><span class="label">{{ translate('Contact') }}</span><span class="val">{{ $expense->supplier->contact_person }}</span></div>@endif
                        @if($expense->supplier->phone)<div class="pair"><span class="label">{{ translate('Phone') }}</span><span class="val"><a href="tel:{{ $expense->supplier->phone }}">{{ $expense->supplier->phone }}</a></span></div>@endif
                        @if($expense->supplier->bin)<div class="pair"><span class="label">{{ translate('BIN') }}</span><span class="val pay-mono">{{ $expense->supplier->bin }}</span></div>@endif
                        <div class="pair"><span class="label">{{ translate('Terms') }}</span><span class="val">{{ str_replace('net_', 'NET ', $expense->supplier->payment_terms) }}</span></div>
                        <div class="pair"><span class="label">{{ translate('Total outstanding') }}</span><span class="val" style="color:#C82626;">{{ $sym($expense->supplier->outstanding_balance) }}</span></div>
                    </div>
                @else
                    <p style="color:#6A6A70; font-size:13px; margin:0;">{{ translate('Direct purchase / petty cash — no supplier on file.') }}</p>
                @endif
            </div>

            @if(in_array($expense->status, ['pending', 'partial'], true) && $balance > 0.005)
            <div class="lh-card">
                <h3>{{ translate('Add payment') }}</h3>
                <p style="font-size:12px; color:#6A6A70; margin:0 0 8px;">
                    {{ translate('Outstanding') }}: <strong style="color:#C82626;">{{ $sym($balance) }}</strong>
                </p>

                <form method="POST" action="{{ route('admin.expenses.payments.add', ['id' => $expense->id]) }}" class="lh-pay-form">
                    @csrf

                    <div class="form-row">
                        <div>
                            <label>{{ translate('Amount (Tk)') }}</label>
                            <input type="number" name="amount" step="0.01" min="0.01" max="{{ $balance }}" required value="{{ number_format($balance, 2, '.', '') }}">
                        </div>
                        <div>
                            <label>{{ translate('Method') }}</label>
                            <select name="method" required id="lh-pay-method-sel" onchange="lhTogglePayRef(this.value)">
                                <option value="cash">💵 {{ translate('Cash') }}</option>
                                <option value="bank">🏦 {{ translate('Bank') }}</option>
                                <option value="mobile">📱 {{ translate('Mobile money') }}</option>
                                <option value="cheque">🧾 {{ translate('Cheque') }}</option>
                            </select>
                        </div>
                    </div>

                    @if($cashAccountsForPayment->count() > 0)
                    <div style="margin-bottom:8px;">
                        <label>{{ translate('Paid from account') }}</label>
                        <select name="cash_account_id">
                            <option value="">— {{ translate('not posted to ledger') }} —</option>
                            @foreach($cashAccountsForPayment as $a)
                                @php
                                    $emoji = match($a->type) { 'cash' => '💵', 'bank' => '🏦', 'mfs' => '📱', 'cheque' => '🧾', default => '•' };
                                @endphp
                                <option value="{{ $a->id }}">{{ $emoji }} {{ $a->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                    <div id="lh-pay-ref-row" style="display:none; margin-bottom:8px;">
                        <label>{{ translate('Reference') }} <small style="color:#C82626;">*</small></label>
                        <input type="text" name="reference" maxlength="80" placeholder="{{ translate('bank txn / bKash trxID / cheque #') }}">
                    </div>

                    <div class="form-row">
                        <div>
                            <label>{{ translate('Paid at') }}</label>
                            <input type="datetime-local" name="paid_at" value="{{ now()->format('Y-m-d\TH:i') }}">
                        </div>
                    </div>

                    <div style="margin-bottom:10px;">
                        <label>{{ translate('Notes') }}</label>
                        <input type="text" name="notes" maxlength="500">
                    </div>

                    <button type="submit" class="btn btn-success" style="width:100%;">{{ translate('Post payment') }}</button>
                </form>

                <script>
                    function lhTogglePayRef(method) {
                        var row = document.getElementById('lh-pay-ref-row');
                        var ref = row.querySelector('input[name=reference]');
                        var show = method !== 'cash';
                        row.style.display = show ? 'block' : 'none';
                        ref.required = show;
                    }
                </script>
            </div>
            @endif

            @if($expense->status !== 'cancelled' && $expense->payments->isEmpty())
            <div class="lh-card">
                <form method="POST" action="{{ route('admin.expenses.cancel', ['id' => $expense->id]) }}" onsubmit="return confirm('{{ translate('Cancel this bill? Cannot be reversed once it has any payments.') }}')">
                    @csrf
                    <button type="submit" class="btn btn-light" style="width:100%; color:#C82626; font-weight:700;">{{ translate('Cancel bill') }}</button>
                </form>
            </div>
            @endif
        </div>
    </div>

</div>
@endsection
