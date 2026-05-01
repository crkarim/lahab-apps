@extends('layouts.admin.app')

@section('title', translate('Work Schedule') . ' · ' . trim(($employee->f_name ?? '') . ' ' . ($employee->l_name ?? '')))

@section('content')
<style>
    .lh-sched-page { max-width: 980px; margin: 0 auto; }
    .lh-sched-hero {
        background: linear-gradient(135deg, #fff 0%, #fff7ee 100%);
        border: 1px solid #f1e3cf; border-radius: 16px;
        padding: 18px 22px; margin-bottom: 14px;
        display: flex; gap: 14px; align-items: center; flex-wrap: wrap;
    }
    .lh-sched-hero h1 { margin: 0; font-size: 18px; font-weight: 800; color: #1A1A1A; }
    .lh-sched-hero p { margin: 2px 0 0; color: #6A6A70; font-size: 13px; }
    .lh-sched-hero .actions { margin-left: auto; }
    .lh-sched-hero .icon {
        width: 54px; height: 54px; border-radius: 50%; flex-shrink: 0;
        background: rgba(230, 126, 34, 0.14); color: #E67E22;
        display: flex; align-items: center; justify-content: center; font-size: 22px;
    }

    .lh-card { background: #fff; border: 1px solid #E5E7EB; border-radius: 12px; padding: 4px 0; overflow-x: auto; }
    .lh-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .lh-table th {
        font-size: 11px; font-weight: 700; color: #6A6A70;
        text-transform: uppercase; letter-spacing: 1px;
        padding: 10px 14px; border-bottom: 1px solid #F0F2F5;
        text-align: left; white-space: nowrap;
    }
    .lh-table td { padding: 10px 14px; border-bottom: 1px solid #F0F2F5; vertical-align: middle; }
    .lh-table tr:last-child td { border-bottom: 0; }
    .lh-table input[type=time] {
        width: 110px; border: 1px solid #E5E7EB; border-radius: 6px;
        padding: 5px 8px; font-size: 13px;
    }
    .lh-table input[type=number] {
        width: 70px; border: 1px solid #E5E7EB; border-radius: 6px;
        padding: 5px 8px; font-size: 13px;
    }
    .lh-table .day-name { font-weight: 700; color: #1A1A1A; }
    .lh-table tr.off { background: #fafbfd; }
    .lh-table tr.off input[type=time] { opacity: 0.5; }

    .lh-laws {
        background: #F0F8FF;
        border: 1px solid #d0e7fb;
        border-radius: 10px;
        padding: 12px 16px;
        margin-bottom: 14px;
        font-size: 12px;
        color: #345776;
        line-height: 1.7;
    }
    .lh-laws strong { color: #1A1A1A; }
</style>

<div class="lh-sched-page">

    <div style="display:flex; align-items:center; gap:10px; margin-bottom:14px; flex-wrap:wrap;">
        <a href="{{ route('admin.employee.update', ['id' => $employee->id]) }}" class="btn btn-light btn-sm">← {{ translate('Employee') }}</a>
        <a href="{{ route('admin.attendance.employee', ['id' => $employee->id]) }}" class="btn btn-light btn-sm">{{ translate('Attendance history') }} →</a>
    </div>

    @if(session('error'))<div class="alert alert-soft-danger">{{ session('error') }}</div>@endif
    @if(session('success'))<div class="alert alert-soft-success">{{ session('success') }}</div>@endif

    <div class="lh-sched-hero">
        <div class="icon">📅</div>
        <div>
            <h1>{{ translate('Work schedule') }} · {{ trim(($employee->f_name ?? '') . ' ' . ($employee->l_name ?? '')) }}</h1>
            <p>{{ translate('Set the expected shift for each day of the week. Drives late / early / overtime classification on attendance rows.') }}</p>
        </div>
        <div class="actions">
            <form method="POST" action="{{ route('admin.employee.schedule.apply-default', ['id' => $employee->id]) }}"
                  onsubmit="return confirm('{{ translate('Overwrite the entire schedule with the BD Labour default?') }}')">
                @csrf
                <button type="submit" class="btn btn-light">{{ translate('Apply BD default') }}</button>
            </form>
        </div>
    </div>

    <div class="lh-laws">
        <strong>BD Labour Act 2006 framing:</strong>
        Sec 100 — max 8 paid hours / day · Sec 102 — max 48 paid hours / week · Sec 103 — at least one weekly day off · Sec 108 — overtime pays at 2× ordinary wage. The BD default seeds Sun-Thu + Sat 09:00→18:00 (1h break) with Friday off.
    </div>

    <form method="POST" action="{{ route('admin.employee.schedule', ['id' => $employee->id]) }}">
        @csrf
        <div class="lh-card">
            <table class="lh-table">
                <thead>
                    <tr>
                        <th>{{ translate('Day') }}</th>
                        <th>{{ translate('Off day') }}</th>
                        <th>{{ translate('Shift start') }}</th>
                        <th>{{ translate('Shift end') }}</th>
                        <th>{{ translate('Break (min)') }}</th>
                        <th>{{ translate('Grace (min)') }}</th>
                    </tr>
                </thead>
                <tbody>
                @foreach(\App\Models\WorkSchedule::DAYS as $dow => $name)
                    @php $r = $rows[$dow] ?? null; @endphp
                    <tr class="{{ $r && $r->is_off_day ? 'off' : '' }}">
                        <td class="day-name">{{ $name }}</td>
                        <td>
                            <input type="hidden" name="days[{{ $dow }}][is_off_day]" value="0">
                            <input type="checkbox" name="days[{{ $dow }}][is_off_day]" value="1"
                                   {{ $r && $r->is_off_day ? 'checked' : '' }}
                                   onchange="this.closest('tr').classList.toggle('off', this.checked);">
                        </td>
                        <td>
                            <input type="time" name="days[{{ $dow }}][shift_start]"
                                   value="{{ $r ? \Illuminate\Support\Str::of($r->shift_start)->limit(5, '') : '' }}">
                        </td>
                        <td>
                            <input type="time" name="days[{{ $dow }}][shift_end]"
                                   value="{{ $r ? \Illuminate\Support\Str::of($r->shift_end)->limit(5, '') : '' }}">
                        </td>
                        <td>
                            <input type="number" name="days[{{ $dow }}][break_minutes]"
                                   min="0" max="480" value="{{ $r ? $r->break_minutes : 60 }}">
                        </td>
                        <td>
                            <input type="number" name="days[{{ $dow }}][grace_minutes]"
                                   min="0" max="60" value="{{ $r ? $r->grace_minutes : 10 }}">
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div style="margin-top:14px; display:flex; justify-content:flex-end;">
            <button type="submit" class="btn btn-primary">{{ translate('Save schedule') }}</button>
        </div>
    </form>

    <div style="margin-top:16px; font-size:11px; color:#6A6A70; line-height:1.6;">
        <strong>{{ translate('How this affects attendance') }}:</strong>
        {{ translate('A clock-in past the shift_start + grace_minutes is flagged LATE with the late minutes count. A clock-out before shift_end - grace is flagged EARLY. Time worked past shift_end is OT (or all worked time if the day is marked Off — BD Sec 108).') }}
    </div>
</div>
@endsection
