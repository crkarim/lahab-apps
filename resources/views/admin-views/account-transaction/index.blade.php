@extends('layouts.admin.app')

@section('title', translate('Transactions'))

@section('content')
@php
    $sym = fn ($v) => \App\CentralLogics\Helpers::set_symbol($v);
@endphp

<style>
    .lh-tx-page { max-width: 1280px; margin: 0 auto; }
    .lh-tx-hero {
        background: linear-gradient(135deg, #fff 0%, #eef7ff 100%);
        border: 1px solid #d6e7f7; border-radius: 16px;
        padding: 22px 26px; margin-bottom: 18px;
        display: flex; gap: 18px; align-items: center; flex-wrap: wrap;
    }
    .lh-tx-hero .icon {
        width: 56px; height: 56px; border-radius: 50%;
        background: rgba(71, 148, 255, 0.14); color: #4794FF;
        display: flex; align-items: center; justify-content: center;
        font-size: 26px; flex-shrink: 0;
    }
    .lh-tx-hero h1 { margin: 0; font-size: 20px; font-weight: 800; color: #1A1A1A; }
    .lh-tx-hero p { margin: 2px 0 0; color: #6A6A70; font-size: 13px; }
    .lh-tx-hero .actions { margin-left: auto; display: flex; gap: 6px; }

    .lh-totals { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-bottom: 18px; }
    @media (max-width: 900px) { .lh-totals { grid-template-columns: repeat(2, 1fr); } }
    .lh-tile { background: #fff; border: 1px solid #E5E7EB; border-radius: 12px; padding: 12px 14px; }
    .lh-tile .label { font-size: 10px; font-weight: 800; color: #6A6A70; text-transform: uppercase; letter-spacing: 1.1px; }
    .lh-tile .value { font-size: 20px; font-weight: 800; color: #1A1A1A; font-variant-numeric: tabular-nums; margin-top: 2px; }
    .lh-tile.in .value  { color: #1E8E3E; }
    .lh-tile.out .value { color: #C82626; }

    .lh-filter {
        background: #fff; border: 1px solid #E5E7EB; border-radius: 12px;
        padding: 14px 16px; margin-bottom: 14px;
        display: flex; gap: 10px; flex-wrap: wrap; align-items: center;
    }
    .lh-filter label { font-size: 11px; font-weight: 700; color: #6A6A70; }
    .lh-filter input, .lh-filter select {
        border: 1px solid #E5E7EB; border-radius: 8px;
        padding: 6px 10px; font-size: 13px;
    }

    .lh-card { background: #fff; border: 1px solid #E5E7EB; border-radius: 12px; padding: 4px 0; margin-bottom: 14px; overflow-x: auto; }
    .lh-table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .lh-table th { font-size: 10px; font-weight: 700; color: #6A6A70; text-transform: uppercase; letter-spacing: 1px; padding: 9px 12px; border-bottom: 1px solid #F0F2F5; text-align: left; white-space: nowrap; }
    .lh-table td { padding: 9px 12px; border-bottom: 1px solid #F0F2F5; vertical-align: middle; }
    .lh-table tr:last-child td { border-bottom: 0; }
    .lh-table .num { font-variant-numeric: tabular-nums; font-weight: 700; text-align: right; }
    .lh-table .num.in  { color: #1E8E3E; }
    .lh-table .num.out { color: #C82626; }
    .lh-table .acc-pill { display: inline-block; padding: 2px 8px; font-size: 10px; font-weight: 800; color: #fff; border-radius: 999px; }
    .lh-table .dir-pill { display: inline-block; padding: 1px 7px; font-size: 9px; font-weight: 800; letter-spacing: 1px; border-radius: 999px; }
    .lh-table .dir-pill.in  { background: #ECFFEF; color: #1E8E3E; }
    .lh-table .dir-pill.out { background: #FFEFEF; color: #C82626; }
    .lh-table .dir-pill.transfer { background: #EEF7FF; color: #4794FF; }
    .lh-table .txn-mono { font-family: monospace; font-size: 10px; color: #6A6A70; }
    .lh-empty { padding: 22px; text-align: center; color: #6A6A70; font-size: 13px; }
</style>

<div class="lh-tx-page">

    @if(session('error'))<div class="alert alert-soft-danger">{{ session('error') }}</div>@endif
    @if(session('success'))<div class="alert alert-soft-success">{{ session('success') }}</div>@endif

    <div class="lh-tx-hero">
        <div class="icon">📒</div>
        <div>
            <h1>{{ translate('Transactions ledger') }}</h1>
            <p>{{ translate('Every Taka movement against any account. Filter by date / account / direction. Charges and VAT are split out for reports.') }}</p>
        </div>
        <div class="actions">
            <a href="{{ route('admin.account-transactions.create', ['type' => 'in']) }}" class="btn btn-success" style="font-weight:700;">+ {{ translate('Cash in') }}</a>
            <a href="{{ route('admin.account-transactions.create', ['type' => 'out']) }}" class="btn btn-light" style="font-weight:700; color:#C82626;">− {{ translate('Cash out') }}</a>
            <a href="{{ route('admin.account-transactions.create', ['type' => 'transfer']) }}" class="btn btn-light" style="font-weight:700;">⇄ {{ translate('Transfer') }}</a>
        </div>
    </div>

    <div class="lh-totals">
        <div class="lh-tile in">
            <div class="label">{{ translate('Total in') }}</div>
            <div class="value">{{ $sym($totals['in']) }}</div>
        </div>
        <div class="lh-tile out">
            <div class="label">{{ translate('Total out') }}</div>
            <div class="value">{{ $sym($totals['out']) }}</div>
        </div>
        <div class="lh-tile">
            <div class="label">{{ translate('Charges') }}</div>
            <div class="value">{{ $sym($totals['charges']) }}</div>
        </div>
        <div class="lh-tile">
            <div class="label">{{ translate('VAT in (paid)') }}</div>
            <div class="value">{{ $sym($totals['vat_in']) }}</div>
        </div>
        <div class="lh-tile">
            <div class="label">{{ translate('VAT out (collected)') }}</div>
            <div class="value">{{ $sym($totals['vat_out']) }}</div>
        </div>
    </div>

    <form method="GET" class="lh-filter">
        <div>
            <label>{{ translate('From') }}</label>
            <input type="date" name="from" value="{{ $from->format('Y-m-d') }}">
        </div>
        <div>
            <label>{{ translate('To') }}</label>
            <input type="date" name="to" value="{{ $to->format('Y-m-d') }}">
        </div>
        <div>
            <label>{{ translate('Account') }}</label>
            <select name="account_id">
                <option value="">{{ translate('All') }}</option>
                @foreach($accounts as $a)
                    <option value="{{ $a->id }}" {{ (string) $accountFilter === (string) $a->id ? 'selected' : '' }}>{{ $a->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>{{ translate('Direction') }}</label>
            <select name="direction">
                <option value="">{{ translate('Both') }}</option>
                <option value="in"  {{ $directionFilter === 'in'  ? 'selected' : '' }}>{{ translate('In') }}</option>
                <option value="out" {{ $directionFilter === 'out' ? 'selected' : '' }}>{{ translate('Out') }}</option>
            </select>
        </div>
        <div style="margin-left:auto;">
            <button type="submit" class="btn btn-primary btn-sm" style="font-weight:700;">{{ translate('Apply') }}</button>
            <a href="{{ route('admin.account-transactions.index') }}" class="btn btn-light btn-sm" style="font-weight:700;">{{ translate('Reset') }}</a>
        </div>
    </form>

    <div class="lh-card">
        <table class="lh-table">
            <thead>
                <tr>
                    <th>{{ translate('When') }}</th>
                    <th>{{ translate('Txn #') }}</th>
                    <th>{{ translate('Account') }}</th>
                    <th>{{ translate('Dir') }}</th>
                    <th>{{ translate('Description') }}</th>
                    <th class="num">{{ translate('Amount') }}</th>
                    <th class="num">{{ translate('Charge') }}</th>
                    <th class="num">{{ translate('VAT in/out') }}</th>
                    <th class="num">{{ translate('Tax') }}</th>
                    <th>{{ translate('By') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($rows as $r)
                <tr>
                    <td style="font-size:11px;">{{ optional($r->transacted_at)->format('d M, H:i') }}</td>
                    <td><span class="txn-mono">{{ $r->txn_no }}</span></td>
                    <td>
                        <span class="acc-pill" style="background: {{ $r->account?->color ?: '#6A6A70' }};">{{ strtoupper($r->account?->type ?? '') }}</span>
                        {{ $r->account?->name }}
                    </td>
                    <td>
                        @if($r->paired_txn_id)
                            <span class="dir-pill transfer">⇄ XFER</span>
                        @elseif($r->direction === 'in')
                            <span class="dir-pill in">+ IN</span>
                        @else
                            <span class="dir-pill out">− OUT</span>
                        @endif
                    </td>
                    <td style="font-size:11px; max-width:280px;">
                        {{ $r->description }}
                        @if($r->ref_type)
                            <br><small style="color:#6A6A70;">{{ $r->ref_type }}#{{ $r->ref_id }}</small>
                        @endif
                    </td>
                    <td class="num {{ $r->direction }}">
                        {{ $r->direction === 'in' ? '+' : '−' }} {{ $sym($r->amount) }}
                    </td>
                    <td class="num" style="color:{{ $r->charge > 0 ? '#C82626' : '#A0A4AB' }};">
                        {{ $r->charge > 0 ? '− ' . $sym($r->charge) : '—' }}
                    </td>
                    <td class="num" style="color:#6A6A70; font-weight:600;">
                        @if($r->vat_input > 0)in {{ $sym($r->vat_input) }}@endif
                        @if($r->vat_output > 0)out {{ $sym($r->vat_output) }}@endif
                        @if($r->vat_input == 0 && $r->vat_output == 0)—@endif
                    </td>
                    <td class="num" style="color:#6A6A70;">{{ $r->tax_amount > 0 ? $sym($r->tax_amount) : '—' }}</td>
                    <td style="font-size:11px; color:#6A6A70;">{{ $r->recordedBy ? trim(($r->recordedBy->f_name ?? '') . ' ' . ($r->recordedBy->l_name ?? '')) : '—' }}</td>
                    <td>
                        @if(empty($r->ref_type))
                            <form method="POST" action="{{ route('admin.account-transactions.destroy', ['id' => $r->id]) }}"
                                  onsubmit="return confirm('{{ translate('Delete this transaction? Pair will also be removed if it\'s a transfer.') }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-light btn-sm" style="color:#C82626; padding:2px 8px; font-size:10px;">✕</button>
                            </form>
                        @else
                            <small style="color:#9095A0;" title="Auto-posted; revert at source">🔒</small>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="11" class="lh-empty">{{ translate('No transactions in this window.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="font-size:11px; color:#6A6A70;">
        {{ translate('Showing up to 200 most recent matching rows. Narrow the filter to see older entries.') }}
    </div>

</div>
@endsection
