@extends('layouts.admin.app')

@section('title', translate('Bills'))

@section('content')
@php $sym = fn ($v) => \App\CentralLogics\Helpers::set_symbol($v); @endphp

<style>
    .lh-bil-page { max-width: 1300px; margin: 0 auto; }
    .lh-bil-hero {
        background: linear-gradient(135deg, #fff 0%, #fff7ee 100%);
        border: 1px solid #f1e3cf; border-radius: 16px;
        padding: 22px 26px; margin-bottom: 18px;
        display: flex; gap: 18px; align-items: center; flex-wrap: wrap;
    }
    .lh-bil-hero .icon { width:56px; height:56px; border-radius:50%; background:rgba(232,126,34,0.14); color:#E67E22; display:flex; align-items:center; justify-content:center; font-size:26px; flex-shrink:0; }
    .lh-bil-hero h1 { margin:0; font-size:20px; font-weight:800; }
    .lh-bil-hero p  { margin:2px 0 0; color:#6A6A70; font-size:13px; }
    .lh-bil-hero .actions { margin-left:auto; }

    .lh-totals { display:grid; grid-template-columns:repeat(4, 1fr); gap:10px; margin-bottom:18px; }
    @media (max-width:800px) { .lh-totals { grid-template-columns:repeat(2,1fr); } }
    .lh-tile { background:#fff; border:1px solid #E5E7EB; border-radius:12px; padding:12px 14px; }
    .lh-tile .label { font-size:10px; font-weight:800; color:#6A6A70; text-transform:uppercase; letter-spacing:1.1px; }
    .lh-tile .value { font-size:20px; font-weight:800; color:#1A1A1A; font-variant-numeric:tabular-nums; margin-top:2px; }
    .lh-tile.outstanding .value { color:#C82626; }

    .lh-filter { background:#fff; border:1px solid #E5E7EB; border-radius:12px; padding:14px 16px; margin-bottom:14px; display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
    .lh-filter label { font-size:11px; font-weight:700; color:#6A6A70; }
    .lh-filter input, .lh-filter select { border:1px solid #E5E7EB; border-radius:8px; padding:6px 10px; font-size:13px; }

    .lh-card { background:#fff; border:1px solid #E5E7EB; border-radius:12px; padding:4px 0; margin-bottom:14px; overflow-x:auto; }
    .lh-table { width:100%; border-collapse:collapse; font-size:13px; }
    .lh-table th { font-size:11px; font-weight:700; color:#6A6A70; text-transform:uppercase; letter-spacing:1px; padding:10px 12px; border-bottom:1px solid #F0F2F5; text-align:left; white-space:nowrap; }
    .lh-table td { padding:10px 12px; border-bottom:1px solid #F0F2F5; vertical-align:middle; }
    .lh-table tr:last-child td { border-bottom:0; }
    .lh-table .num { font-variant-numeric:tabular-nums; font-weight:700; text-align:right; }
    .lh-table .num.outstanding { color:#C82626; }
    .lh-table .exp-mono { font-family:monospace; font-size:11px; color:#6A6A70; }
    .lh-table .cat-pill { display:inline-block; padding:1px 8px; font-size:10px; font-weight:800; color:#fff; border-radius:999px; }
    .lh-table .status-pill { display:inline-block; padding:2px 8px; font-size:10px; font-weight:800; letter-spacing:1px; border-radius:999px; }
    .lh-table .status-pill.pending   { background:#FFF4E5; color:#B45A0A; }
    .lh-table .status-pill.partial   { background:#EEF7FF; color:#4794FF; }
    .lh-table .status-pill.paid      { background:#ECFFEF; color:#1E8E3E; }
    .lh-table .status-pill.cancelled { background:#F0F2F5; color:#6A6A70; }
    .lh-table .due-warn { color:#C82626; font-weight:700; }
    .lh-empty { padding:22px; text-align:center; color:#6A6A70; font-size:13px; }
</style>

<div class="lh-bil-page">

    @if(session('error'))<div class="alert alert-soft-danger">{{ session('error') }}</div>@endif
    @if(session('success'))<div class="alert alert-soft-success">{{ session('success') }}</div>@endif

    <div class="lh-bil-hero">
        <div class="icon">🧾</div>
        <div>
            <h1>{{ translate('Supplier bills') }}</h1>
            <p>{{ translate('Every bill from a supplier — meat, fish, gas, fuel, utilities, rent. Each payment auto-posts to the chosen cash account.') }}</p>
        </div>
        <div class="actions">
            <a href="{{ route('admin.expenses.create') }}" class="btn btn-primary" style="font-weight:700;">+ {{ translate('Record bill') }}</a>
        </div>
    </div>

    <div class="lh-totals">
        <div class="lh-tile"><div class="label">{{ translate('Bills') }}</div><div class="value">{{ $totals['count'] }}</div></div>
        <div class="lh-tile"><div class="label">{{ translate('Total billed') }}</div><div class="value">{{ $sym($totals['billed']) }}</div></div>
        <div class="lh-tile"><div class="label">{{ translate('Total paid') }}</div><div class="value" style="color:#1E8E3E;">{{ $sym($totals['paid']) }}</div></div>
        <div class="lh-tile outstanding"><div class="label">{{ translate('Outstanding') }}</div><div class="value">{{ $sym($totals['outstanding']) }}</div></div>
    </div>

    <form method="GET" class="lh-filter">
        <div><label>{{ translate('From') }}</label><input type="date" name="from" value="{{ $from->format('Y-m-d') }}"></div>
        <div><label>{{ translate('To') }}</label><input type="date" name="to" value="{{ $to->format('Y-m-d') }}"></div>
        <div>
            <label>{{ translate('Status') }}</label>
            <select name="status">
                <option value="">{{ translate('All') }}</option>
                <option value="pending"   {{ $statusFilter === 'pending'   ? 'selected' : '' }}>{{ translate('Pending') }}</option>
                <option value="partial"   {{ $statusFilter === 'partial'   ? 'selected' : '' }}>{{ translate('Partial') }}</option>
                <option value="paid"      {{ $statusFilter === 'paid'      ? 'selected' : '' }}>{{ translate('Paid') }}</option>
                <option value="cancelled" {{ $statusFilter === 'cancelled' ? 'selected' : '' }}>{{ translate('Cancelled') }}</option>
            </select>
        </div>
        <div>
            <label>{{ translate('Supplier') }}</label>
            <select name="supplier_id">
                <option value="">{{ translate('All') }}</option>
                @foreach($suppliers as $s)
                    <option value="{{ $s->id }}" {{ (string) $supplierFilter === (string) $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div style="margin-left:auto;">
            <button type="submit" class="btn btn-primary btn-sm" style="font-weight:700;">{{ translate('Apply') }}</button>
            <a href="{{ route('admin.expenses.index') }}" class="btn btn-light btn-sm" style="font-weight:700;">{{ translate('Reset') }}</a>
        </div>
    </form>

    <div class="lh-card">
        <table class="lh-table">
            <thead>
                <tr>
                    <th>{{ translate('Bill #') }}</th>
                    <th>{{ translate('Date') }}</th>
                    <th>{{ translate('Supplier') }}</th>
                    <th>{{ translate('Category') }}</th>
                    <th>{{ translate('Branch') }}</th>
                    <th class="num">{{ translate('Total') }}</th>
                    <th class="num">{{ translate('Paid') }}</th>
                    <th class="num">{{ translate('Outstanding') }}</th>
                    <th>{{ translate('Due') }}</th>
                    <th>{{ translate('Status') }}</th>
                </tr>
            </thead>
            <tbody>
            @forelse($expenses as $e)
                @php
                    $outstanding = $e->balanceDue();
                    $isOverdue = $e->due_date && $e->due_date->isPast() && in_array($e->status, ['pending', 'partial'], true);
                    $catLabel = $e->category ? ($e->category->parent ? $e->category->parent->name . ' → ' . $e->category->name : $e->category->name) : '—';
                @endphp
                <tr style="cursor:pointer;" onclick="window.location='{{ route('admin.expenses.show', ['id' => $e->id]) }}'">
                    <td><span class="exp-mono">{{ $e->expense_no }}</span>@if($e->bill_no)<br><small style="color:#6A6A70;">{{ $e->bill_no }}</small>@endif</td>
                    <td style="font-size:12px;">{{ $e->bill_date->format('d M, y') }}</td>
                    <td>{{ $e->supplier?->name ?: '— ' . translate('direct') . ' —' }}</td>
                    <td>@if($e->category)<span class="cat-pill" style="background: {{ $e->category->color }};">{{ $catLabel }}</span>@else —@endif</td>
                    <td style="font-size:12px; color:#6A6A70;">{{ $e->branch?->name ?: 'HQ' }}</td>
                    <td class="num">{{ $sym($e->total) }}</td>
                    <td class="num" style="color:#1E8E3E;">{{ $e->paid_amount > 0 ? $sym($e->paid_amount) : '—' }}</td>
                    <td class="num outstanding">{{ $outstanding > 0 ? $sym($outstanding) : '—' }}</td>
                    <td style="font-size:11px;" class="{{ $isOverdue ? 'due-warn' : '' }}">
                        @if($e->due_date)
                            {{ $e->due_date->format('d M') }}
                            @if($isOverdue)<br><small>OVERDUE</small>@endif
                        @else — @endif
                    </td>
                    <td><span class="status-pill {{ $e->status }}">{{ strtoupper($e->status) }}</span></td>
                </tr>
            @empty
                <tr><td colspan="10" class="lh-empty">{{ translate('No bills in this window.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

</div>
@endsection
