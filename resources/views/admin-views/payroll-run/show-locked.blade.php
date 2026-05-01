@extends('layouts.admin.app')

@section('title', translate('Payroll Run') . ' #' . $run->id)

@section('content')
@php $sym = fn ($v) => \App\CentralLogics\Helpers::set_symbol($v); @endphp

<style>
    .lh-run-page { max-width: 1200px; margin: 0 auto; }
    .lh-banner {
        background: linear-gradient(135deg, #E6F2FF 0%, #fff 100%);
        border: 1px solid #c8e0f7; border-radius: 16px;
        padding: 18px 22px; margin-bottom: 16px;
        display: flex; gap: 14px; align-items: center; flex-wrap: wrap;
    }
    .lh-banner.paid { background: linear-gradient(135deg, #ECFFEF 0%, #fff 100%); border-color: #c7eed2; }
    .lh-banner h1 { margin: 0; font-size: 18px; font-weight: 800; color: #1A1A1A; }
    .lh-banner p { margin: 2px 0 0; color: #6A6A70; font-size: 13px; }
    .lh-badge {
        display: inline-block; padding: 3px 10px;
        border-radius: 999px; font-size: 11px; font-weight: 800; letter-spacing: 1px;
    }
    .lh-badge.locked { background: #E6F2FF; color: #4794FF; }
    .lh-badge.paid   { background: #ECFFEF; color: #1E8E3E; }

    .lh-totals { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-bottom: 14px; }
    @media (max-width: 800px) { .lh-totals { grid-template-columns: repeat(2, 1fr); } }
    .lh-tile { background: #fff; border: 1px solid #E5E7EB; border-radius: 12px; padding: 12px 14px; }
    .lh-tile .label { font-size: 10px; font-weight: 700; color: #6A6A70; text-transform: uppercase; letter-spacing: 1px; }
    .lh-tile .value { font-size: 18px; font-weight: 800; color: #1A1A1A; font-variant-numeric: tabular-nums; margin-top: 2px; }
    .lh-tile.gross .value { color: #1E8E3E; }

    .lh-card { background: #fff; border: 1px solid #E5E7EB; border-radius: 12px; padding: 4px 0; overflow-x: auto; }
    .lh-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .lh-table th { font-size: 11px; font-weight: 700; color: #6A6A70; text-transform: uppercase; letter-spacing: 1px; padding: 10px 14px; border-bottom: 1px solid #F0F2F5; text-align: left; white-space: nowrap; }
    .lh-table td { padding: 11px 14px; border-bottom: 1px solid #F0F2F5; vertical-align: middle; }
    .lh-table tr:last-child td { border-bottom: 0; }
    .lh-table .num { font-variant-numeric: tabular-nums; font-weight: 600; color: #1A1A1A; text-align: right; }
    .lh-table .net { font-weight: 800; color: #1E8E3E; }
    .lh-table .who strong { color: #1A1A1A; }
    .lh-table .who small  { display: block; color: #6A6A70; font-size: 11px; }
    .lh-table .code-pill { display: inline-block; padding: 1px 6px; background: #FFF4E5; color: #E67E22; border-radius: 3px; font-family: monospace; font-size: 11px; font-weight: 700; }
    .lh-table .paid-pill { background: #ECFFEF; color: #1E8E3E; padding: 2px 8px; border-radius: 999px; font-size: 10px; font-weight: 800; letter-spacing: 0.8px; }
</style>

<div class="lh-run-page">

    <div style="margin-bottom:14px;">
        <a href="{{ route('admin.payroll-runs.index') }}" class="btn btn-light btn-sm">← {{ translate('All runs') }}</a>
    </div>

    @if(session('error'))<div class="alert alert-soft-danger">{{ session('error') }}</div>@endif
    @if(session('success'))<div class="alert alert-soft-success">{{ session('success') }}</div>@endif

    <div class="lh-banner {{ $run->isPaid() ? 'paid' : '' }}">
        <span class="lh-badge {{ $run->status }}">{{ strtoupper($run->status) }}</span>
        <div>
            <h1>{{ translate('Run') }} #{{ $run->id }} · {{ optional($run->period_from)->format('d M') }} → {{ optional($run->period_to)->format('d M Y') }}</h1>
            <p>
                @if($run->locked_at)
                    {{ translate('Locked') }} {{ $run->locked_at->format('d M Y, H:i') }}
                    @if($run->lockedBy) {{ translate('by') }} {{ trim(($run->lockedBy->f_name ?? '') . ' ' . ($run->lockedBy->l_name ?? '')) }}@endif
                @endif
                @if($run->paid_at) · {{ translate('All paid') }} {{ $run->paid_at->format('d M Y') }}@endif
                @if($run->branch) · {{ $run->branch->name }}@endif
            </p>
        </div>
    </div>

    <div class="lh-totals">
        <div class="lh-tile gross">
            <div class="label">{{ translate('Net payable') }}</div>
            <div class="value">{{ $sym($run->total_net) }}</div>
        </div>
        <div class="lh-tile">
            <div class="label">{{ translate('Gross') }}</div>
            <div class="value">{{ $sym($run->total_gross) }}</div>
        </div>
        <div class="lh-tile">
            <div class="label">{{ translate('Deductions') }}</div>
            <div class="value">{{ $sym($run->total_deductions) }}</div>
        </div>
        <div class="lh-tile">
            <div class="label">{{ translate('Advances') }}</div>
            <div class="value">{{ $sym($run->total_advances) }}</div>
        </div>
        <div class="lh-tile">
            <div class="label">{{ translate('Tips') }}</div>
            <div class="value">{{ $sym($run->total_tips) }}</div>
        </div>
    </div>

    <div class="lh-card">
        <table class="lh-table">
            <thead>
                <tr>
                    <th>{{ translate('Employee') }}</th>
                    <th>{{ translate('Days') }}</th>
                    <th class="num" style="text-align:right;">{{ translate('Basic') }}</th>
                    <th class="num" style="text-align:right;">{{ translate('Allow.') }}</th>
                    <th class="num" style="text-align:right;">{{ translate('Tips') }}</th>
                    <th class="num" style="text-align:right;">{{ translate('Deduct.') }}</th>
                    <th class="num" style="text-align:right;">{{ translate('Advance') }}</th>
                    <th class="num" style="text-align:right;">{{ translate('Net') }}</th>
                    <th>{{ translate('Paid') }}</th>
                </tr>
            </thead>
            <tbody>
            @foreach($payslips as $p)
                @php $snap = $p->employee_snapshot_json ?? []; @endphp
                <tr>
                    <td class="who">
                        <strong>{{ trim(($snap['f_name'] ?? '') . ' ' . ($snap['l_name'] ?? '')) }}</strong>
                        <small>
                            @if(!empty($snap['employee_code']))<span class="code-pill">{{ $snap['employee_code'] }}</span>@endif
                            {{ $snap['designation'] ?? translate('Staff') }}
                        </small>
                    </td>
                    <td>{{ $p->days_clocked }}/{{ $p->calendar_days }}</td>
                    <td class="num">{{ $sym($p->prorated_basic) }}</td>
                    <td class="num">{{ $sym($p->prorated_allowance) }}</td>
                    <td class="num">{{ $sym($p->tip_share) }}</td>
                    <td class="num" style="color:{{ $p->prorated_deduction > 0 ? '#E84D4F' : '#A0A4AB' }};">
                        {{ $p->prorated_deduction > 0 ? '− ' . $sym($p->prorated_deduction) : '—' }}
                    </td>
                    <td class="num" style="color:{{ $p->advance_recovery > 0 ? '#E84D4F' : '#A0A4AB' }};">
                        {{ $p->advance_recovery > 0 ? '− ' . $sym($p->advance_recovery) : '—' }}
                    </td>
                    <td class="num net">{{ $sym($p->net) }}</td>
                    <td>
                        @if($p->paid_at)
                            <span class="paid-pill">PAID</span>
                            <small style="display:block; color:#6A6A70; font-size:10px;">
                                {{ $p->paid_at->format('d M, H:i') }} · {{ strtoupper($p->paid_method ?? 'cash') }}
                                @if($p->paid_reference)
                                    <br><span style="font-family:monospace;">{{ $p->paid_reference }}</span>
                                @endif
                            </small>
                        @else
                            @php
                                // Each unpaid row pre-fills the modal with the
                                // employee's preferred payment method so HR doesn't
                                // re-pick it for every row.
                                $emp = \App\Model\Admin::find($p->admin_id);
                                $defaultMethod = $emp?->payment_method ?? 'cash';
                                $payeeLabel = trim(($snap['f_name'] ?? '') . ' ' . ($snap['l_name'] ?? '')) ?: ('#' . $p->id);
                            @endphp
                            <button type="button" class="btn btn-success btn-sm"
                                    style="padding:3px 10px; font-size:11px; font-weight:700;"
                                    onclick='lhPrepMarkPaid({!! json_encode([
                                        "id"      => $p->id,
                                        "name"    => $payeeLabel,
                                        "amount"  => (float) $p->net,
                                        "method"  => $defaultMethod,
                                        "bank"    => $emp?->bank_name ? trim(($emp->bank_name ?: '') . ' · ' . ($emp->bank_account_number ?: '')) : null,
                                        "wallet"  => $emp?->mobile_wallet_number ? strtoupper($emp->mobile_provider ?: '') . ' · ' . $emp->mobile_wallet_number : null,
                                    ], JSON_HEX_APOS | JSON_HEX_QUOT) !!})'>
                                {{ translate('Mark paid') }}
                            </button>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div style="margin-top:14px; font-size:11px; color:#6A6A70; line-height:1.6;">
        <strong>{{ translate('Frozen snapshot') }}:</strong>
        {{ translate('Numbers above were captured at lock time and won\'t change even if attendance, salary structure, or tip flow change later. Pay slip PDF/email + bank-batch CSV export are next.') }}
    </div>
</div>

{{-- Mark-paid modal — captures method + reference (bank txn / bKash trxID
     / cheque #) for non-cash payments. Reference is enforced server-side
     too. Pre-filled with the employee's preferred method from their record. --}}
<div class="modal-overlay" id="lh-pay-modal" onclick="if(event.target===this) this.classList.remove('open')">
    <form method="POST" id="lh-pay-form" class="modal-card">
        @csrf
        <h2>{{ translate('Mark payslip paid') }}</h2>
        <p style="font-size:13px; color:#1A1A1A; margin-bottom:14px;">
            <strong id="lh-pay-name">—</strong>
            <br>
            <span style="color:#6A6A70;">{{ translate('Net') }}: <strong id="lh-pay-amount" style="font-variant-numeric:tabular-nums;">—</strong></span>
        </p>

        <label>{{ translate('Method') }}</label>
        <select name="paid_method" id="lh-pay-method" required onchange="lhPayModalSwitch(this.value)">
            <option value="cash">{{ translate('Cash') }}</option>
            <option value="bank">{{ translate('Bank transfer') }}</option>
            <option value="mobile">{{ translate('Mobile money') }}</option>
            <option value="cheque">{{ translate('Cheque') }}</option>
        </select>

        <div id="lh-pay-employee-detail" style="margin-top:8px; font-size:11px; color:#1A1A1A; background:#F4F6F8; padding:8px 12px; border-radius:6px; display:none;">
            <strong>{{ translate('On record') }}:</strong> <span id="lh-pay-detail-text">—</span>
        </div>

        <div id="lh-pay-ref-row" style="display:none;">
            <label style="display:block; margin-top:10px;">
                {{ translate('Reference') }} <span style="color:#C82626;">*</span>
                <small style="color:#6A6A70; font-weight:500;">({{ translate('bank txn / bKash trxID / cheque #') }})</small>
            </label>
            <input type="text" name="paid_reference" id="lh-pay-ref" maxlength="80" placeholder="e.g. TX1234567 / 9DK9G3X8 / 0001234">
        </div>

        <label style="display:block; margin-top:10px;">{{ translate('Paid at') }} <small style="color:#6A6A70; font-weight:500;">({{ translate('blank = now') }})</small></label>
        <input type="datetime-local" name="paid_at">

        <label style="display:block; margin-top:10px;">{{ translate('Notes') }} <small style="color:#6A6A70; font-weight:500;">({{ translate('optional') }})</small></label>
        <input type="text" name="notes" maxlength="500">

        <div class="actions" style="margin-top:16px; display:flex; gap:8px;">
            <button type="button" class="btn btn-light" style="flex:1;" onclick="document.getElementById('lh-pay-modal').classList.remove('open')">{{ translate('Cancel') }}</button>
            <button type="submit" class="btn btn-success" style="flex:1;">{{ translate('Confirm payment') }}</button>
        </div>
    </form>
</div>

<style>
    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1050; }
    .modal-overlay.open { display: flex; }
    .modal-card { background: #fff; border-radius: 14px; max-width: 480px; width: 92%; padding: 22px 24px; max-height: 90vh; overflow-y: auto; }
    .modal-card h2 { font-size: 18px; font-weight: 800; margin: 0 0 4px; color: #1A1A1A; }
    .modal-card label { font-size: 12px; font-weight: 700; color: #6A6A70; }
    .modal-card input, .modal-card select {
        width: 100%; border: 1px solid #E5E7EB; border-radius: 8px;
        padding: 9px 12px; font-size: 14px; margin-top: 4px;
    }
</style>

<script>
function lhPrepMarkPaid(p) {
    var form = document.getElementById('lh-pay-form');
    form.action = '{{ url('admin/payroll-runs/payslip') }}/' + p.id + '/mark-paid';
    document.getElementById('lh-pay-name').textContent   = p.name || '—';
    document.getElementById('lh-pay-amount').textContent = 'Tk ' + (p.amount ? Number(p.amount).toFixed(2) : '0.00');
    var methodEl = document.getElementById('lh-pay-method');
    methodEl.value = p.method || 'cash';
    // Surface employee bank/wallet info so HR doesn't have to flip tabs.
    var detail = '';
    if (p.method === 'bank' && p.bank)     detail = p.bank;
    if (p.method === 'mobile' && p.wallet) detail = p.wallet;
    if (detail) {
        document.getElementById('lh-pay-detail-text').textContent = detail;
        document.getElementById('lh-pay-employee-detail').style.display = 'block';
    } else {
        document.getElementById('lh-pay-employee-detail').style.display = 'none';
    }
    lhPayModalSwitch(methodEl.value);
    document.getElementById('lh-pay-ref').value = '';
    document.getElementById('lh-pay-modal').classList.add('open');
}
function lhPayModalSwitch(method) {
    var refRow = document.getElementById('lh-pay-ref-row');
    refRow.style.display = (method === 'cash') ? 'none' : 'block';
    var refInput = document.getElementById('lh-pay-ref');
    if (refInput) refInput.required = (method !== 'cash');
}
</script>
@endsection
