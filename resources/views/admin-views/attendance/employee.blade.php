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
        grid-template-columns: 100px 90px 90px 110px 80px 100px;
        gap: 10px;
        padding: 9px 4px;
        border-bottom: 1px solid #F0F2F5;
        font-size: 13px;
        align-items: center;
    }
    .lh-att-row .row-actions { display: flex; gap: 4px; justify-content: flex-end; }
    .lh-att-row .row-actions .btn { padding: 2px 8px; font-size: 11px; font-weight: 700; }
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
    .lh-att-row .method-pill.bio   { background: #E6F2FF; color: #4794FF; }
    .lh-att-row .flag-pill {
        display: inline-block; padding: 1px 6px; border-radius: 4px;
        font-size: 9px; font-weight: 800; letter-spacing: 0.6px; margin-left: 4px;
    }
    .lh-att-row .flag-pill.late, .lh-att-row .flag-pill.early { background: #FFEEEE; color: #C8281A; }
    .lh-att-row .flag-pill.ot { background: #ECFFEF; color: #1E8E3E; }
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

    <div style="margin: 14px 0 8px;">
        <button type="button" class="btn btn-light btn-sm" onclick="document.getElementById('lh-backdate-modal').classList.add('open')">
            + {{ translate('Add past entry') }}
        </button>
    </div>

    <div class="lh-card">
        @forelse($rows as $r)
            @php
                $late  = $r->lateMinutes();
                $early = $r->earlyMinutes();
                $ot    = $r->overtimeMinutes();
            @endphp
            <div class="lh-att-row">
                <div class="date">{{ optional($r->clock_in_at)->format('d M (D)') }}</div>
                <div class="time">
                    {{ optional($r->clock_in_at)->format('H:i') }}
                    @if($late > 0)<span class="flag-pill late">+{{ $late }}m</span>@endif
                </div>
                <div class="time {{ $r->isOpen() ? 'muted' : '' }}">
                    {{ $r->clock_out_at ? $r->clock_out_at->format('H:i') : '— · ·' }}
                    @if($early > 0)<span class="flag-pill early">−{{ $early }}m</span>@endif
                </div>
                <div class="duration">
                    {{ $minutesToHHMM($r->workedMinutes()) }} {{ $r->isOpen() ? '(open)' : '' }}
                    @if($ot > 0)<span class="flag-pill ot">OT {{ $ot }}m</span>@endif
                </div>
                <div>
                    @php $methodClass = $r->method === 'shift_open' ? 'shift' : ($r->method === 'biometric' ? 'bio' : ''); @endphp
                    @php $methodLabel = ['shift_open' => 'SHIFT', 'biometric' => 'BIO'][$r->method] ?? 'MANUAL'; @endphp
                    <span class="method-pill {{ $methodClass }}">{{ $methodLabel }}</span>
                </div>
                <div class="row-actions">
                    <button type="button" class="btn btn-light"
                        onclick="lhAttEdit({{ $r->id }}, '{{ optional($r->clock_in_at)->format('Y-m-d\\TH:i') }}', '{{ optional($r->clock_out_at)->format('Y-m-d\\TH:i') }}')">
                        ✎
                    </button>
                    <form method="POST" action="{{ route('admin.attendance.destroy', ['id' => $r->id]) }}"
                          style="display:inline;" onsubmit="return confirm('{{ translate('Delete this attendance row? Cannot be undone.') }}')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-light" style="color:#E84D4F;">✕</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="lh-empty">{{ translate('No attendance entries in this date range.') }}</div>
        @endforelse
    </div>
</div>

{{-- Edit + backdate modals --}}
<style>
    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1050; }
    .modal-overlay.open { display: flex; }
    .modal-card { background: #fff; border-radius: 14px; max-width: 460px; width: 92%; padding: 22px 24px; }
    .modal-card h2 { font-size: 18px; font-weight: 800; margin: 0 0 4px; color: #1A1A1A; }
    .modal-card p  { color: #6A6A70; font-size: 13px; margin: 0 0 14px; }
    .modal-card label { font-size: 12px; font-weight: 700; color: #6A6A70; }
    .modal-card input { width: 100%; border: 1px solid #E5E7EB; border-radius: 8px; padding: 9px 12px; font-size: 14px; margin-top: 4px; }
    .modal-card .actions { display: flex; gap: 8px; margin-top: 16px; }
</style>

<div class="modal-overlay" id="lh-edit-modal" onclick="if(event.target===this) this.classList.remove('open')">
    <form method="POST" id="lh-edit-form" class="modal-card">
        @csrf
        <h2>{{ translate('Edit attendance') }}</h2>
        <p>{{ translate('Adjust the times for this row. Leave Clock-out blank to leave the row open.') }}</p>
        <label>{{ translate('Clock-in') }}</label>
        <input type="datetime-local" name="clock_in_at" id="lh-edit-in" required>
        <label style="display:block; margin-top:10px;">{{ translate('Clock-out') }}</label>
        <input type="datetime-local" name="clock_out_at" id="lh-edit-out">
        <label style="display:block; margin-top:10px;">{{ translate('Notes (optional)') }}</label>
        <input type="text" name="notes" maxlength="500" placeholder="{{ translate('Reason for the edit') }}">
        <div class="actions">
            <button type="button" class="btn btn-light" style="flex:1;" onclick="document.getElementById('lh-edit-modal').classList.remove('open')">{{ translate('Cancel') }}</button>
            <button type="submit" class="btn btn-primary" style="flex:1;">{{ translate('Save') }}</button>
        </div>
    </form>
</div>

<div class="modal-overlay" id="lh-backdate-modal" onclick="if(event.target===this) this.classList.remove('open')">
    <form method="POST" action="{{ route('admin.attendance.backdate') }}" class="modal-card">
        @csrf
        <input type="hidden" name="admin_id" value="{{ $employee->id }}">
        <h2>{{ translate('Add past attendance') }} · {{ trim(($employee->f_name ?? '') . ' ' . ($employee->l_name ?? '')) }}</h2>
        <p>{{ translate('Backfill an entry for a past day.') }}</p>
        <label>{{ translate('Clock-in') }}</label>
        <input type="datetime-local" name="clock_in_at" required>
        <label style="display:block; margin-top:10px;">{{ translate('Clock-out (optional)') }}</label>
        <input type="datetime-local" name="clock_out_at">
        <label style="display:block; margin-top:10px;">{{ translate('Notes (optional)') }}</label>
        <input type="text" name="notes" maxlength="500" placeholder="{{ translate('e.g. Device offline yesterday') }}">
        <div class="actions">
            <button type="button" class="btn btn-light" style="flex:1;" onclick="document.getElementById('lh-backdate-modal').classList.remove('open')">{{ translate('Cancel') }}</button>
            <button type="submit" class="btn btn-primary" style="flex:1;">{{ translate('Save') }}</button>
        </div>
    </form>
</div>

<script>
function lhAttEdit(rowId, inAt, outAt) {
    document.getElementById('lh-edit-form').action = '{{ url('admin/attendance') }}/' + rowId + '/update';
    document.getElementById('lh-edit-in').value = inAt || '';
    document.getElementById('lh-edit-out').value = outAt || '';
    document.getElementById('lh-edit-modal').classList.add('open');
}
</script>
@endsection
