@extends('layouts.admin.app')

@section('title', translate('Payroll') . ' · ' . trim(($employee->f_name ?? '') . ' ' . ($employee->l_name ?? '')))

@section('content')
@php
    $minToHours = function ($mins) {
        $mins = (int) $mins;
        $h = intdiv($mins, 60);
        $m = $mins % 60;
        return sprintf('%dh %02dm', $h, $m);
    };
    $sym = fn ($v) => \App\CentralLogics\Helpers::set_symbol($v);
@endphp

<style>
    .lh-emp-page { max-width: 1100px; margin: 0 auto; }
    .lh-emp-hero {
        background: #fff; border: 1px solid #E5E7EB; border-radius: 12px;
        padding: 18px 22px; margin-bottom: 14px;
        display: flex; gap: 14px; align-items: center;
    }
    .lh-emp-hero h1 { margin: 0; font-size: 18px; font-weight: 800; color: #1A1A1A; }
    .lh-emp-hero p  { margin: 2px 0 0; font-size: 13px; color: #6A6A70; }
    .lh-emp-hero .code-pill {
        display: inline-block; padding: 2px 8px;
        background: #FFF4E5; color: #E67E22;
        border-radius: 4px; font-size: 11px; font-weight: 700;
        font-family: monospace; margin-right: 6px;
    }

    .lh-grid {
        display: grid; grid-template-columns: 1fr 1fr; gap: 14px;
    }
    @media (max-width: 800px) { .lh-grid { grid-template-columns: 1fr; } }

    .lh-card { background: #fff; border: 1px solid #E5E7EB; border-radius: 12px; padding: 16px 18px; margin-bottom: 14px; }
    .lh-card h3 {
        font-size: 11px; font-weight: 800; letter-spacing: 1.4px;
        color: #6A6A70; text-transform: uppercase; margin: 0 0 12px;
    }
    .lh-money-row {
        display: flex; justify-content: space-between;
        padding: 8px 0; border-bottom: 1px solid #F0F2F5; font-size: 13px;
    }
    .lh-money-row:last-child { border-bottom: 0; }
    .lh-money-row .amt { font-variant-numeric: tabular-nums; font-weight: 700; color: #1A1A1A; }
    .lh-money-row.gross {
        background: #ECFFEF; margin: 8px -18px 0; padding: 12px 18px;
        border: 1px solid #c7eed2; border-radius: 0 0 12px 12px;
        font-size: 15px; font-weight: 800; color: #1E8E3E;
    }

    .lh-list {
        max-height: 280px; overflow-y: auto;
        font-size: 12px;
    }
    .lh-list .row {
        padding: 7px 0; border-bottom: 1px solid #F0F2F5;
        display: flex; justify-content: space-between; gap: 10px;
    }
    .lh-list .row:last-child { border-bottom: 0; }
    .lh-list .row strong { color: #1A1A1A; font-weight: 700; }
    .lh-list .row .amt { font-variant-numeric: tabular-nums; font-weight: 700; }
    .lh-list .empty { padding: 14px; text-align: center; color: #6A6A70; }

    .lh-range {
        display: flex; gap: 8px; align-items: center; margin-bottom: 14px;
        flex-wrap: wrap;
    }
    .lh-range input { padding: 6px 10px; border: 1px solid #E5E7EB; border-radius: 6px; }
</style>

<div class="lh-emp-page">

    <div style="display:flex; align-items:center; gap:10px; margin-bottom:14px;">
        <a href="{{ route('admin.payroll.index') }}?from={{ $from->format('Y-m-d') }}&to={{ $to->format('Y-m-d') }}" class="btn btn-light btn-sm">← {{ translate('Payroll preview') }}</a>
        <a href="{{ route('admin.attendance.employee', ['id' => $employee->id]) }}?from={{ $from->format('Y-m-d') }}&to={{ $to->format('Y-m-d') }}" class="btn btn-light btn-sm">{{ translate('Attendance history') }} →</a>
    </div>

    <div class="lh-emp-hero">
        <div style="width:54px; height:54px; border-radius:50%; background:#FFF4E5; color:#E67E22; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:22px;">
            {{ strtoupper(substr($employee->f_name ?? '?', 0, 1)) }}{{ strtoupper(substr($employee->l_name ?? '', 0, 1)) }}
        </div>
        <div>
            <h1>{{ trim(($employee->f_name ?? '') . ' ' . ($employee->l_name ?? '')) }}</h1>
            <p>
                @if($employee->employee_code)<span class="code-pill">{{ $employee->employee_code }}</span>@endif
                {{ $employee->designation ?: ($employee->role->name ?? translate('Staff')) }}
                @if($employee->employment_type) · {{ ucfirst(str_replace('_', ' ', $employee->employment_type)) }} @endif
                @if($employee->joining_date) · {{ translate('Joined') }} {{ optional($employee->joining_date)->format('d M Y') }} @endif
            </p>
        </div>
    </div>

    <form method="GET" class="lh-range">
        <label style="font-size:12px; font-weight:700; color:#6A6A70;">{{ translate('From') }}</label>
        <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" />
        <label style="font-size:12px; font-weight:700; color:#6A6A70;">{{ translate('To') }}</label>
        <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" />
        <button type="submit" class="btn btn-light">{{ translate('Apply') }}</button>
    </form>

    {{-- Computation summary --}}
    <div class="lh-card">
        <h3>{{ translate('Pay summary') }}</h3>
        <div class="lh-money-row">
            <span>
                {{ translate('Days clocked') }}
                <small style="display:block; color:#6A6A70;">{{ $summary['days_clocked'] }} of {{ $summary['calendar_days'] }} calendar days · {{ $minToHours($summary['attendance_minutes']) }} on duty</small>
            </span>
            <span class="amt">{{ number_format(($summary['days_clocked'] / max(1, $summary['calendar_days'])) * 100, 1) }}%</span>
        </div>
        <div class="lh-money-row">
            <span>
                {{ translate('Basic salary (prorated)') }}
                <small style="display:block; color:#6A6A70;">{{ translate('Full') }}: {{ $sym($summary['salary_basic_full']) }}</small>
            </span>
            <span class="amt">+ {{ $sym($summary['prorated_basic']) }}</span>
        </div>
        <div class="lh-money-row">
            <span>
                {{ translate('Allowance (prorated)') }}
                <small style="display:block; color:#6A6A70;">{{ translate('Full') }}: {{ $sym($summary['salary_allow_full']) }}</small>
            </span>
            <span class="amt">+ {{ $sym($summary['prorated_allowance']) }}</span>
        </div>
        <div class="lh-money-row">
            <span>
                {{ translate('Tip share') }}
                <small style="display:block; color:#6A6A70;">{{ translate('From') }} {{ $tipOrders->count() }} {{ translate('paid orders') }}</small>
            </span>
            <span class="amt">+ {{ $sym($summary['tip_share']) }}</span>
        </div>

        @if($summary['prorated_deduction'] > 0)
        <div class="lh-money-row" style="color:#E84D4F;">
            <span>
                {{ translate('Deductions (prorated)') }}
                <small style="display:block; color:#6A6A70;">{{ translate('Full') }}: {{ $sym($summary['salary_deduction_full']) }}</small>
            </span>
            <span class="amt" style="color:#E84D4F;">− {{ $sym($summary['prorated_deduction']) }}</span>
        </div>
        @endif

        @if($summary['advance_recovery'] > 0)
        <div class="lh-money-row" style="color:#E84D4F;">
            <span>
                {{ translate('Advance recovery (next run)') }}
                <small style="display:block; color:#6A6A70;">
                    @php
                        $activeAdv = \App\Models\SalaryAdvance::activeForAdmin($employee->id)->get();
                        $totalBal  = (float) $activeAdv->sum('balance');
                    @endphp
                    {{ $activeAdv->count() }} {{ translate('active advance(s)') }} · {{ translate('total outstanding') }}: {{ $sym($totalBal) }}
                </small>
            </span>
            <span class="amt" style="color:#E84D4F;">− {{ $sym($summary['advance_recovery']) }}</span>
        </div>
        @endif

        <div class="lh-money-row gross">
            <span>{{ translate('Net payable (estimated)') }}</span>
            <span class="amt">{{ $sym($summary['net']) }}</span>
        </div>
    </div>

    <div class="lh-grid">
        <div class="lh-card">
            <h3>{{ translate('Attendance in range') }} <small style="font-weight:600; color:#1A1A1A; margin-left:6px;">{{ $attendance->count() }}</small></h3>
            <div class="lh-list">
                @forelse($attendance as $r)
                    @php
                        $start = $r->clock_in_at;
                        $end   = $r->clock_out_at ?? now();
                        $worked = $start ? max(0, (int) $start->diffInMinutes($end)) : 0;
                    @endphp
                    <div class="row">
                        <div>
                            <strong>{{ optional($r->clock_in_at)->format('d M (D)') }}</strong>
                            <div style="color:#6A6A70;">
                                {{ optional($r->clock_in_at)->format('H:i') }} → {{ $r->clock_out_at ? $r->clock_out_at->format('H:i') : translate('still open') }}
                            </div>
                        </div>
                        <div class="amt">{{ $minToHours($worked) }}</div>
                    </div>
                @empty
                    <div class="empty">{{ translate('No attendance entries in this range.') }}</div>
                @endforelse
            </div>
        </div>

        <div class="lh-card">
            <h3>{{ translate('Tip orders in range') }} <small style="font-weight:600; color:#1A1A1A; margin-left:6px;">{{ $tipOrders->count() }}</small></h3>
            <div class="lh-list">
                @forelse($tipOrders as $o)
                    <div class="row">
                        <div>
                            <strong>KOT {{ $o->kot_number ?? '#' . $o->id }}</strong>
                            <div style="color:#6A6A70;">{{ optional($o->created_at)->format('d M · H:i') }}</div>
                        </div>
                        <div class="amt">{{ $sym($o->tip_amount) }}</div>
                    </div>
                @empty
                    <div class="empty">{{ translate('No paid orders with tips in this range.') }}</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
