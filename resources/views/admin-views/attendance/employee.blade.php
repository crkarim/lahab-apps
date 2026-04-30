@extends('layouts.admin.app')

@section('title', translate('Attendance') . ' · ' . trim(($employee->f_name ?? '') . ' ' . ($employee->l_name ?? '')))

@section('content')
@php
    $minutesToHHMM = function ($mins) {
        $mins = (int) $mins;
        $h = intdiv($mins, 60);
        $m = $mins % 60;
        return sprintf('%dh %02dm', $h, $m);
    };
@endphp

<style>
    .lh-emp-page { max-width: 1000px; margin: 0 auto; }
    .lh-emp-hero {
        background: #fff;
        border: 1px solid #E5E7EB;
        border-radius: 12px;
        padding: 18px 22px;
        margin-bottom: 14px;
        display: flex; gap: 14px; align-items: center;
    }
    .lh-emp-hero h1 { margin: 0; font-size: 18px; font-weight: 800; color: #1A1A1A; }
    .lh-emp-hero p  { margin: 2px 0 0; font-size: 13px; color: #6A6A70; }

    .lh-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin-bottom: 14px;
    }
    .lh-stat {
        background: #fff;
        border: 1px solid #E5E7EB;
        border-radius: 12px;
        padding: 16px 18px;
        text-align: center;
    }
    .lh-stat .label {
        font-size: 11px; font-weight: 700;
        color: #6A6A70;
        text-transform: uppercase;
        letter-spacing: 1.2px;
    }
    .lh-stat .value {
        font-size: 22px; font-weight: 800;
        color: #1A1A1A;
        font-variant-numeric: tabular-nums;
        margin-top: 4px;
    }

    .lh-range {
        display: flex; gap: 8px; align-items: center; margin-bottom: 14px;
        flex-wrap: wrap;
    }
    .lh-range input { padding: 6px 10px; border: 1px solid #E5E7EB; border-radius: 6px; }
    .lh-range button { padding: 6px 16px; }

    .lh-card { background: #fff; border: 1px solid #E5E7EB; border-radius: 12px; padding: 14px 16px; }
    .lh-att-row {
        display: grid;
        grid-template-columns: 110px 110px 110px 1fr 90px;
        gap: 10px;
        padding: 9px 4px;
        border-bottom: 1px solid #F0F2F5;
        font-size: 13px;
        align-items: center;
    }
    .lh-att-row:last-child { border-bottom: 0; }
    .lh-att-row .date { color: #6A6A70; font-weight: 600; }
    .lh-att-row .time { font-variant-numeric: tabular-nums; font-weight: 600; }
    .lh-att-row .time.muted { color: #A0A4AB; }
    .lh-att-row .duration { font-weight: 700; color: #1A1A1A; font-variant-numeric: tabular-nums; }
    .lh-att-row .method-pill {
        display: inline-block;
        padding: 2px 7px;
        border-radius: 4px;
        font-size: 10px;
        font-weight: 700;
        background: #F0F2F5; color: #6A6A70;
    }
    .lh-att-row .method-pill.shift { background: #FFF4E5; color: #E67E22; }
    .lh-empty { padding: 22px; text-align: center; color: #6A6A70; font-size: 13px; }
</style>

<div class="lh-emp-page">

    <div style="display:flex; align-items:center; gap:10px; margin-bottom:14px;">
        <a href="{{ route('admin.attendance.index') }}" class="btn btn-light btn-sm">← {{ translate('Today\'s attendance') }}</a>
    </div>

    <div class="lh-emp-hero">
        <div style="width:54px; height:54px; border-radius:50%; background:#FFF4E5; color:#E67E22; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:22px;">
            {{ strtoupper(substr($employee->f_name ?? '?', 0, 1)) }}{{ strtoupper(substr($employee->l_name ?? '', 0, 1)) }}
        </div>
        <div>
            <h1>{{ trim(($employee->f_name ?? '') . ' ' . ($employee->l_name ?? '')) }}</h1>
            <p>
                {{ $employee->designation ?: translate('Staff') }}
                @if($employee->joining_date) · {{ translate('Joined') }} {{ optional($employee->joining_date)->format('d M Y') }} @endif
                @if($employee->employment_type) · {{ ucfirst(str_replace('_', ' ', $employee->employment_type)) }} @endif
            </p>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.attendance.employee', ['id' => $employee->id]) }}" class="lh-range">
        <label style="font-size:12px; font-weight:700; color:#6A6A70;">{{ translate('From') }}</label>
        <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" />
        <label style="font-size:12px; font-weight:700; color:#6A6A70;">{{ translate('To') }}</label>
        <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" />
        <button type="submit" class="btn btn-light">{{ translate('Apply') }}</button>
    </form>

    <div class="lh-stats">
        <div class="lh-stat">
            <div class="label">{{ translate('Days clocked') }}</div>
            <div class="value">{{ $rows->groupBy(fn($r) => optional($r->clock_in_at)->format('Y-m-d'))->count() }}</div>
        </div>
        <div class="lh-stat">
            <div class="label">{{ translate('Total time') }}</div>
            <div class="value">{{ $minutesToHHMM($totalMinutes) }}</div>
        </div>
        <div class="lh-stat">
            <div class="label">{{ translate('Avg / day') }}</div>
            <div class="value">
                @php
                    $days = max(1, $rows->groupBy(fn($r) => optional($r->clock_in_at)->format('Y-m-d'))->count());
                @endphp
                {{ $minutesToHHMM(intdiv($totalMinutes, $days)) }}
            </div>
        </div>
    </div>

    <div class="lh-card">
        @forelse($rows as $r)
            <div class="lh-att-row">
                <div class="date">{{ optional($r->clock_in_at)->format('d M (D)') }}</div>
                <div class="time">{{ optional($r->clock_in_at)->format('H:i') }}</div>
                <div class="time {{ $r->isOpen() ? 'muted' : '' }}">{{ $r->clock_out_at ? $r->clock_out_at->format('H:i') : '— · ·' }}</div>
                <div class="duration">{{ $minutesToHHMM($r->workedMinutes()) }} {{ $r->isOpen() ? '(open)' : '' }}</div>
                <div>
                    <span class="method-pill {{ $r->method === 'shift_open' ? 'shift' : '' }}">
                        {{ $r->method === 'shift_open' ? 'SHIFT' : 'MANUAL' }}
                    </span>
                </div>
            </div>
        @empty
            <div class="lh-empty">{{ translate('No attendance entries in this date range.') }}</div>
        @endforelse
    </div>
</div>
@endsection
