@extends('layouts.admin.app')

@section('title', translate('Payroll preview'))

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
    .lh-payroll-page { max-width: 1200px; margin: 0 auto; }
    .lh-payroll-hero {
        background: linear-gradient(135deg, #fff 0%, #fff7ee 100%);
        border: 1px solid #f1e3cf;
        border-radius: 16px;
        padding: 22px 26px;
        margin-bottom: 18px;
        display: flex; align-items: center; gap: 18px; flex-wrap: wrap;
    }
    .lh-payroll-hero .icon {
        width: 56px; height: 56px; border-radius: 50%;
        background: rgba(230, 126, 34, 0.14); color: #E67E22;
        display: flex; align-items: center; justify-content: center;
        font-size: 26px; flex-shrink: 0;
    }
    .lh-payroll-hero h1 { margin: 0; font-size: 20px; font-weight: 800; color: #1A1A1A; }
    .lh-payroll-hero p  { margin: 2px 0 0; color: #6A6A70; font-size: 13px; }

    .lh-range {
        display: flex; gap: 8px; align-items: center; margin-bottom: 14px;
        flex-wrap: wrap;
    }
    .lh-range input { padding: 6px 10px; border: 1px solid #E5E7EB; border-radius: 6px; }
    .lh-range button { padding: 6px 16px; }

    .lh-totals {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
        margin-bottom: 18px;
    }
    @media (max-width: 800px) { .lh-totals { grid-template-columns: repeat(2, 1fr); } }
    .lh-tile {
        background: #fff;
        border: 1px solid #E5E7EB;
        border-radius: 12px;
        padding: 14px 16px;
    }
    .lh-tile .label {
        font-size: 11px; font-weight: 700; color: #6A6A70;
        text-transform: uppercase; letter-spacing: 1.2px;
    }
    .lh-tile .value {
        font-size: 22px; font-weight: 800; color: #1A1A1A;
        font-variant-numeric: tabular-nums; margin-top: 4px;
    }
    .lh-tile.gross .value { color: #1E8E3E; }

    .lh-card { background: #fff; border: 1px solid #E5E7EB; border-radius: 12px; padding: 6px 0; overflow-x: auto; }
    .lh-table {
        width: 100%; border-collapse: collapse;
        font-size: 13px;
    }
    .lh-table th {
        font-size: 11px; font-weight: 700; color: #6A6A70;
        text-transform: uppercase; letter-spacing: 1px;
        padding: 10px 14px; border-bottom: 1px solid #F0F2F5;
        text-align: left; white-space: nowrap;
    }
    .lh-table td {
        padding: 11px 14px; border-bottom: 1px solid #F0F2F5;
        vertical-align: middle;
    }
    .lh-table tr:last-child td { border-bottom: 0; }
    .lh-table tr:hover { background: #fafbfd; }
    .lh-table .num {
        font-variant-numeric: tabular-nums;
        font-weight: 600; color: #1A1A1A;
    }
    .lh-table .gross { font-weight: 800; color: #1E8E3E; }
    .lh-table .who strong { color: #1A1A1A; font-weight: 700; }
    .lh-table .who small { color: #6A6A70; display: block; font-size: 11px; }
    .lh-table .code-pill {
        display: inline-block; padding: 2px 7px;
        background: #FFF4E5; color: #E67E22;
        border-radius: 4px; font-size: 11px; font-weight: 700;
        font-family: monospace;
    }
    .lh-table .days {
        font-size: 12px;
        color: #6A6A70;
    }
    .lh-table .days strong { color: #1A1A1A; }
    .lh-empty { padding: 28px; text-align: center; color: #6A6A70; font-size: 13px; }
</style>

<div class="lh-payroll-page">

    <div class="lh-payroll-hero">
        <div class="icon">💰</div>
        <div>
            <h1>{{ translate('Payroll preview') }} · {{ $from->format('d M') }} → {{ $to->format('d M Y') }}</h1>
            <p>{{ translate('Read-only projection of what each employee would be paid if you ran payroll right now. Salary is prorated by attendance days; tip share comes from settled orders this employee placed in the range.') }}</p>
        </div>
    </div>

    @if(session('error'))<div class="alert alert-soft-danger">{{ session('error') }}</div>@endif
    @if(session('success'))<div class="alert alert-soft-success">{{ session('success') }}</div>@endif

    <form method="GET" action="{{ route('admin.payroll.index') }}" class="lh-range">
        <label style="font-size:12px; font-weight:700; color:#6A6A70;">{{ translate('From') }}</label>
        <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" />
        <label style="font-size:12px; font-weight:700; color:#6A6A70;">{{ translate('To') }}</label>
        <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" />
        <button type="submit" class="btn btn-light">{{ translate('Apply') }}</button>
    </form>

    <div class="lh-totals">
        <div class="lh-tile gross">
            <div class="label">{{ translate('Net payable') }}</div>
            <div class="value">{{ $sym($totals['net']) }}</div>
        </div>
        <div class="lh-tile">
            <div class="label">{{ translate('Gross') }}</div>
            <div class="value">{{ $sym($totals['gross']) }}</div>
        </div>
        <div class="lh-tile">
            <div class="label">{{ translate('Deductions') }}</div>
            <div class="value">{{ $sym($totals['deductions']) }}</div>
        </div>
        <div class="lh-tile">
            <div class="label">{{ translate('Tip share') }}</div>
            <div class="value">{{ $sym($totals['tips']) }}</div>
        </div>
    </div>

    <div class="lh-card">
        <table class="lh-table">
            <thead>
                <tr>
                    <th>{{ translate('Employee') }}</th>
                    <th>{{ translate('Days') }}</th>
                    <th>{{ translate('Hours') }}</th>
                    <th class="text-right" style="text-align:right;">{{ translate('Basic') }}</th>
                    <th class="text-right" style="text-align:right;">{{ translate('Allow.') }}</th>
                    <th class="text-right" style="text-align:right;">{{ translate('Tips') }}</th>
                    <th class="text-right" style="text-align:right;">{{ translate('Deduct.') }}</th>
                    <th class="text-right" style="text-align:right;">{{ translate('Net') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $r)
                    @php $emp = $r['employee']; @endphp
                    <tr>
                        <td class="who">
                            <strong>
                                <a href="{{ route('admin.payroll.employee', ['id' => $emp->id]) }}?from={{ $from->format('Y-m-d') }}&to={{ $to->format('Y-m-d') }}" style="color:inherit;">
                                    {{ trim(($emp->f_name ?? '') . ' ' . ($emp->l_name ?? '')) }}
                                </a>
                            </strong>
                            <small>
                                @if($emp->employee_code)<span class="code-pill">{{ $emp->employee_code }}</span>@endif
                                {{ $emp->designation ?: ($emp->role->name ?? translate('Staff')) }}
                                @if($emp->employment_type && $emp->employment_type !== 'full_time')
                                    · {{ ucfirst(str_replace('_', ' ', $emp->employment_type)) }}
                                @endif
                            </small>
                        </td>
                        <td class="days">
                            <strong>{{ $r['days_clocked'] }}</strong> / {{ $r['calendar_days'] }}
                        </td>
                        <td class="num">{{ $minToHours($r['attendance_minutes']) }}</td>
                        <td class="num" style="text-align:right;">
                            {{ $sym($r['prorated_basic']) }}
                            @if($r['salary_basic_full'] > $r['prorated_basic'])
                                <small style="display:block; color:#A0A4AB; font-size:10px;">{{ translate('full') }} {{ $sym($r['salary_basic_full']) }}</small>
                            @endif
                        </td>
                        <td class="num" style="text-align:right;">
                            {{ $sym($r['prorated_allowance']) }}
                            @if($r['salary_allow_full'] > $r['prorated_allowance'])
                                <small style="display:block; color:#A0A4AB; font-size:10px;">{{ translate('full') }} {{ $sym($r['salary_allow_full']) }}</small>
                            @endif
                        </td>
                        <td class="num" style="text-align:right;">{{ $sym($r['tip_share']) }}</td>
                        <td class="num" style="text-align:right; color:#E84D4F;">
                            @if($r['prorated_deduction'] > 0)
                                − {{ $sym($r['prorated_deduction']) }}
                            @else
                                <span style="color:#A0A4AB;">—</span>
                            @endif
                        </td>
                        <td class="num gross" style="text-align:right;">{{ $sym($r['net']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="lh-empty">{{ translate('No staff found in your branch.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:14px; font-size:11px; color:#A0A4AB; line-height:1.6;">
        <strong>{{ translate('How it\'s calculated') }}:</strong>
        {{ translate('Basic + Allowance prorated by (days clocked / calendar days in range). Tips = sum of tip_amount on PAID orders this employee placed in the range. Master Admin row is hidden. Master-Admin-only view.') }}
    </div>
</div>
@endsection
