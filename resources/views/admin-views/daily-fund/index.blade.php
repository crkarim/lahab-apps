@extends('layouts.admin.app')

@section('title', translate('Daily Fund Report'))

@section('content')
@php
    $sym = fn ($v) => \App\CentralLogics\Helpers::set_symbol($v);
@endphp

<style>
    .lh-df-page { max-width: 1280px; margin: 0 auto; }
    .lh-df-hero {
        background: linear-gradient(135deg, #fff 0%, #fff7ee 100%);
        border: 1px solid #f1e3cf; border-radius: 16px;
        padding: 22px 26px; margin-bottom: 18px;
        display: flex; gap: 18px; align-items: center; flex-wrap: wrap;
    }
    .lh-df-hero .icon {
        width: 56px; height: 56px; border-radius: 50%;
        background: rgba(232, 126, 34, 0.14); color: #E67E22;
        display: flex; align-items: center; justify-content: center;
        font-size: 26px; flex-shrink: 0;
    }
    .lh-df-hero h1 { margin: 0; font-size: 20px; font-weight: 800; color: #1A1A1A; }
    .lh-df-hero p  { margin: 2px 0 0; color: #6A6A70; font-size: 13px; }
    .lh-df-hero .actions { margin-left: auto; }

    .lh-grand { display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px; margin-bottom: 18px; }
    @media (max-width: 1000px) { .lh-grand { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 600px)  { .lh-grand { grid-template-columns: repeat(2, 1fr); } }
    .lh-tile { background: #fff; border: 1px solid #E5E7EB; border-radius: 12px; padding: 12px 14px; }
    .lh-tile .label { font-size: 10px; font-weight: 800; color: #6A6A70; text-transform: uppercase; letter-spacing: 1.1px; }
    .lh-tile .value { font-size: 20px; font-weight: 800; color: #1A1A1A; font-variant-numeric: tabular-nums; margin-top: 2px; }
    .lh-tile.in .value      { color: #1E8E3E; }
    .lh-tile.out .value     { color: #C82626; }
    .lh-tile.closing .value { color: #1A1A1A; }
    .lh-tile.closing { background: #1A1A1A; color: #fff; border-color: #1A1A1A; }
    .lh-tile.closing .label { color: #FFD58A; }
    .lh-tile.closing .value { color: #fff; }

    .lh-card { background: #fff; border: 1px solid #E5E7EB; border-radius: 12px; padding: 4px 0; margin-bottom: 14px; overflow-x: auto; }
    .lh-card h3 { font-size: 11px; font-weight: 800; letter-spacing: 1.4px; color: #6A6A70; text-transform: uppercase; margin: 14px 16px 10px; }
    .lh-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .lh-table th { font-size: 11px; font-weight: 700; color: #6A6A70; text-transform: uppercase; letter-spacing: 1px; padding: 10px 14px; border-bottom: 1px solid #F0F2F5; text-align: left; white-space: nowrap; }
    .lh-table td { padding: 10px 14px; border-bottom: 1px solid #F0F2F5; vertical-align: middle; font-variant-numeric: tabular-nums; }
    .lh-table tr:last-child td { border-bottom: 0; }
    .lh-table .num { text-align: right; font-weight: 700; }
    .lh-table .num.opening { color: #6A6A70; }
    .lh-table .num.in      { color: #1E8E3E; }
    .lh-table .num.out     { color: #C82626; }
    .lh-table .num.closing { color: #1A1A1A; font-size: 14px; }
    .lh-table .acc-pill { display: inline-block; padding: 2px 8px; font-size: 10px; font-weight: 800; color: #fff; border-radius: 999px; }
    .lh-table tr.totals td { background: #FAFBFC; font-weight: 800; }
    .lh-empty { padding: 22px; text-align: center; color: #6A6A70; font-size: 13px; }

    .lh-date-form {
        background: #fff; border: 1px solid #E5E7EB; border-radius: 12px;
        padding: 10px 14px; display: inline-flex; gap: 8px; align-items: center;
    }
    .lh-date-form input { border: 1px solid #E5E7EB; border-radius: 6px; padding: 6px 10px; font-size: 13px; }

    details.lh-detail { padding: 4px 0; }
    details.lh-detail summary {
        cursor: pointer; padding: 4px 0;
        font-size: 11px; color: #6A6A70; font-weight: 700;
        list-style: none;
    }
    details.lh-detail summary::-webkit-details-marker { display: none; }
    details.lh-detail summary::before { content: '▸ '; color: #6A6A70; }
    details.lh-detail[open] summary::before { content: '▾ '; color: #1A1A1A; }
    details.lh-detail .moves { margin-top: 6px; padding-left: 14px; }
    details.lh-detail .move-row {
        font-size: 11px; padding: 3px 0;
        border-bottom: 1px dashed #F0F2F5;
        display: flex; gap: 10px; align-items: center;
    }
    details.lh-detail .move-row:last-child { border-bottom: 0; }
    details.lh-detail .move-row .when { color: #6A6A70; min-width: 50px; }
    details.lh-detail .move-row .desc { flex: 1; color: #1A1A1A; }
    details.lh-detail .move-row .amt  { font-variant-numeric: tabular-nums; font-weight: 700; min-width: 100px; text-align: right; }
    details.lh-detail .move-row .amt.in  { color: #1E8E3E; }
    details.lh-detail .move-row .amt.out { color: #C82626; }
</style>

<div class="lh-df-page">

    @if(session('error'))<div class="alert alert-soft-danger">{{ session('error') }}</div>@endif

    <div class="lh-df-hero">
        <div class="icon">📊</div>
        <div>
            <h1>{{ translate('Daily fund report') }}</h1>
            <p>{{ translate('Per-account opening / movements / closing for the chosen date. Reconcile against your bank app and counted cash.') }}</p>
        </div>
        <div class="actions">
            <form method="GET" class="lh-date-form">
                <label style="font-weight:600; font-size:12px; color:#6A6A70;">{{ translate('Date') }}</label>
                <input type="date" name="date" value="{{ $date->format('Y-m-d') }}" onchange="this.form.submit()">
                <a href="{{ route('admin.daily-fund.index', ['date' => now()->subDay()->toDateString()]) }}" class="btn btn-light btn-sm" style="font-size:11px;">← {{ translate('Yesterday') }}</a>
                <a href="{{ route('admin.daily-fund.index') }}" class="btn btn-light btn-sm" style="font-size:11px;">{{ translate('Today') }}</a>
            </form>
        </div>
    </div>

    {{-- Grand totals across all accounts --}}
    <div class="lh-grand">
        <div class="lh-tile">
            <div class="label">{{ translate('Total opening') }}</div>
            <div class="value">{{ $sym($grand['opening']) }}</div>
        </div>
        <div class="lh-tile in">
            <div class="label">{{ translate('In') }}</div>
            <div class="value">+ {{ $sym($grand['in']) }}</div>
        </div>
        <div class="lh-tile out">
            <div class="label">{{ translate('Out') }}</div>
            <div class="value">− {{ $sym($grand['out']) }}</div>
        </div>
        <div class="lh-tile">
            <div class="label">{{ translate('Charges') }}</div>
            <div class="value" style="color:#C82626;">− {{ $sym($grand['charge']) }}</div>
        </div>
        <div class="lh-tile">
            <div class="label">{{ translate('VAT (in / out)') }}</div>
            <div class="value" style="font-size:14px;">
                {{ $sym($grand['vat_in']) }} / {{ $sym($grand['vat_out']) }}
            </div>
        </div>
        <div class="lh-tile closing">
            <div class="label">{{ translate('Total closing') }}</div>
            <div class="value">{{ $sym($grand['closing']) }}</div>
        </div>
    </div>

    <div class="lh-card">
        <h3>{{ translate('Per-account') }} <small style="font-weight:600; color:#1A1A1A;">{{ $date->format('l, d M Y') }}</small> · <small style="font-weight:600; color:#6A6A70;">{{ $txnsCount }} {{ translate('movement(s)') }}</small></h3>
        <table class="lh-table">
            <thead>
                <tr>
                    <th>{{ translate('Account') }}</th>
                    <th>{{ translate('Branch') }}</th>
                    <th class="num">{{ translate('Opening') }}</th>
                    <th class="num">{{ translate('In') }}</th>
                    <th class="num">{{ translate('Out') }}</th>
                    <th class="num">{{ translate('Charges') }}</th>
                    <th class="num">{{ translate('Closing') }}</th>
                </tr>
            </thead>
            <tbody>
            @forelse($rows as $r)
                @php $acc = $r['account']; @endphp
                <tr>
                    <td>
                        <span class="acc-pill" style="background: {{ $acc->color }};">{{ strtoupper($acc->type) }}</span>
                        <strong>{{ $acc->name }}</strong>
                        @if($acc->account_number)
                            <small style="color:#6A6A70; font-family:monospace;"> · {{ $acc->account_number }}</small>
                        @endif
                        @if($r['movements']->count() > 0)
                            <details class="lh-detail">
                                <summary>{{ $r['movements']->count() }} {{ translate('movement(s) this day') }}</summary>
                                <div class="moves">
                                    @foreach($r['movements'] as $m)
                                        <div class="move-row">
                                            <span class="when">{{ optional($m->transacted_at)->format('H:i') }}</span>
                                            <span class="desc">{{ $m->description }}</span>
                                            @if($m->charge > 0)
                                                <span style="font-size:10px; color:#C82626;">[charge {{ $sym($m->charge) }}]</span>
                                            @endif
                                            <span class="amt {{ $m->direction }}">
                                                {{ $m->direction === 'in' ? '+' : '−' }} {{ $sym($m->amount) }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </details>
                        @endif
                    </td>
                    <td style="font-size:12px; color:#6A6A70;">{{ $acc->branch?->name ?: 'HQ' }}</td>
                    <td class="num opening">{{ $sym($r['opening']) }}</td>
                    <td class="num in">{{ $r['in'] > 0 ? '+ ' . $sym($r['in']) : '—' }}</td>
                    <td class="num out">{{ $r['out'] > 0 ? '− ' . $sym($r['out']) : '—' }}</td>
                    <td class="num out">{{ $r['charge'] > 0 ? '− ' . $sym($r['charge']) : '—' }}</td>
                    <td class="num closing">{{ $sym($r['closing']) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="lh-empty">{{ translate('No active accounts in scope. Create one at') }} <a href="{{ route('admin.cash-accounts.index') }}">/admin/cash-accounts</a>.</td></tr>
            @endforelse

            @if(count($rows) > 0)
            <tr class="totals">
                <td colspan="2">{{ translate('Totals') }}</td>
                <td class="num opening">{{ $sym($grand['opening']) }}</td>
                <td class="num in">{{ $grand['in'] > 0 ? '+ ' . $sym($grand['in']) : '—' }}</td>
                <td class="num out">{{ $grand['out'] > 0 ? '− ' . $sym($grand['out']) : '—' }}</td>
                <td class="num out">{{ $grand['charge'] > 0 ? '− ' . $sym($grand['charge']) : '—' }}</td>
                <td class="num closing">{{ $sym($grand['closing']) }}</td>
            </tr>
            @endif
            </tbody>
        </table>
    </div>

    <div style="font-size:11px; color:#6A6A70; margin-top:10px;">
        <strong>{{ translate('Tip') }}:</strong>
        {{ translate('Closing = opening + in − out − charges. If closing differs from your counted cash / bank statement, post an adjustment transaction with a clear reason — never edit balances directly.') }}
    </div>

</div>
@endsection
