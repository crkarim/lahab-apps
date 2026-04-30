@extends('layouts.admin.app')

@section('title', translate('Attendance'))

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
    .lh-att-page { max-width: 1100px; margin: 0 auto; }
    .lh-att-hero {
        background: linear-gradient(135deg, #fff 0%, #fff7ee 100%);
        border: 1px solid #f1e3cf;
        border-radius: 16px;
        padding: 22px 26px;
        margin-bottom: 18px;
        display: flex;
        align-items: center;
        gap: 18px;
        flex-wrap: wrap;
    }
    .lh-att-hero .icon {
        width: 56px; height: 56px;
        border-radius: 50%;
        background: rgba(30, 142, 62, 0.14);
        color: #1E8E3E;
        display: flex; align-items: center; justify-content: center;
        font-size: 26px;
        flex-shrink: 0;
    }
    .lh-att-hero h1 { margin: 0; font-size: 20px; font-weight: 800; color: #1A1A1A; }
    .lh-att-hero p  { margin: 2px 0 0; color: #6A6A70; font-size: 13px; }
    .lh-att-hero .actions { margin-left: auto; display: flex; gap: 8px; }

    .lh-card {
        background: #fff;
        border: 1px solid #E5E7EB;
        border-radius: 12px;
        padding: 16px 18px;
        margin-bottom: 14px;
    }
    .lh-card h3 {
        font-size: 11px; font-weight: 800;
        letter-spacing: 1.4px;
        color: #6A6A70;
        text-transform: uppercase;
        margin: 0 0 10px;
    }
    .lh-card h3 .count {
        font-weight: 700;
        color: #1A1A1A;
        margin-left: 6px;
    }

    .lh-att-row {
        display: grid;
        grid-template-columns: 1fr 110px 110px 130px 100px;
        gap: 10px;
        padding: 10px 4px;
        border-bottom: 1px solid #F0F2F5;
        align-items: center;
        font-size: 13px;
    }
    .lh-att-row:last-child { border-bottom: 0; }
    .lh-att-row .who strong {
        color: #1A1A1A; font-weight: 700;
    }
    .lh-att-row .who small {
        color: #6A6A70; display: block; font-size: 11px;
    }
    .lh-att-row .time {
        font-variant-numeric: tabular-nums;
        color: #1A1A1A;
        font-weight: 600;
    }
    .lh-att-row .duration {
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }
    .lh-att-row .duration.live { color: #1E8E3E; }
    .lh-att-row .method-pill {
        display: inline-block;
        padding: 2px 7px;
        border-radius: 4px;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.6px;
        background: #F0F2F5;
        color: #6A6A70;
    }
    .lh-att-row .method-pill.shift { background: #FFF4E5; color: #E67E22; }

    .modal-overlay {
        position: fixed; inset: 0;
        background: rgba(0, 0, 0, 0.5);
        display: none;
        align-items: center; justify-content: center;
        z-index: 1050;
    }
    .modal-overlay.open { display: flex; }
    .modal-card {
        background: #fff;
        border-radius: 14px;
        max-width: 420px; width: 92%;
        padding: 20px 22px;
    }
    .modal-card h2 { font-size: 18px; font-weight: 800; margin: 0 0 4px; color: #1A1A1A; }
    .modal-card p  { color: #6A6A70; font-size: 13px; margin: 0 0 14px; }
    .modal-card label { font-size: 12px; font-weight: 700; color: #6A6A70; }
    .modal-card select, .modal-card input {
        width: 100%; border: 1px solid #E5E7EB; border-radius: 8px;
        padding: 9px 12px; font-size: 14px; margin-top: 4px;
    }
    .modal-card .actions { display: flex; gap: 8px; margin-top: 16px; }

    .lh-empty {
        padding: 22px;
        text-align: center;
        color: #6A6A70;
        font-size: 13px;
    }

    .lh-absent-row {
        display: inline-block;
        background: #FFEEEE;
        color: #C8281A;
        border-radius: 999px;
        padding: 5px 10px;
        margin: 3px 4px 3px 0;
        font-size: 12px;
        font-weight: 600;
    }
</style>

<div class="lh-att-page">

    @if(session('error'))
        <div class="alert alert-soft-danger" role="alert">{{ session('error') }}</div>
    @endif
    @if(session('success'))
        <div class="alert alert-soft-success" role="alert">{{ session('success') }}</div>
    @endif

    <div class="lh-att-hero">
        <div class="icon">👥</div>
        <div>
            <h1>{{ translate('Attendance') }} · {{ now()->format('d M Y') }}</h1>
            <p>{{ translate('Today\'s roster — opening a cashier shift auto-clocks-in. Waiters and chefs use the manual clock below.') }}</p>
        </div>
        <div class="actions">
            @if($mineOpen)
                <form method="POST" action="{{ route('admin.attendance.clock-out') }}">
                    @csrf
                    <button type="submit" class="btn btn-warning">
                        {{ translate('Clock me out') }}
                        ({{ $minutesToHHMM($mineOpen->workedMinutes()) }})
                    </button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.attendance.clock-in') }}">
                    @csrf
                    <button type="submit" class="btn btn-success">+ {{ translate('Clock me in') }}</button>
                </form>
            @endif
            <button type="button" class="btn btn-light" onclick="document.getElementById('lh-manual-modal').classList.add('open')">
                {{ translate('Clock someone else') }}
            </button>
        </div>
    </div>

    {{-- ON DUTY --}}
    <div class="lh-card">
        <h3>{{ translate('On duty') }} <span class="count">· {{ $openRows->count() }}</span></h3>
        @forelse($openRows as $r)
            <div class="lh-att-row">
                <div class="who">
                    <strong>{{ trim(($r->employee->f_name ?? '') . ' ' . ($r->employee->l_name ?? '')) ?: translate('Unknown') }}</strong>
                    <small>{{ $r->employee->designation ?: translate('Staff') }}</small>
                </div>
                <div class="time">{{ optional($r->clock_in_at)->format('H:i') }}</div>
                <div class="time" style="color:#6A6A70;">— · ·</div>
                <div class="duration live">{{ $minutesToHHMM($r->workedMinutes()) }}</div>
                <div>
                    <span class="method-pill {{ $r->method === 'shift_open' ? 'shift' : '' }}">
                        {{ $r->method === 'shift_open' ? 'SHIFT' : 'MANUAL' }}
                    </span>
                </div>
            </div>
        @empty
            <div class="lh-empty">{{ translate('No one is clocked in right now.') }}</div>
        @endforelse
    </div>

    {{-- CLOSED TODAY --}}
    <div class="lh-card">
        <h3>{{ translate('Closed today') }} <span class="count">· {{ $closedToday->count() }}</span></h3>
        @forelse($closedToday as $r)
            <div class="lh-att-row">
                <div class="who">
                    <strong>{{ trim(($r->employee->f_name ?? '') . ' ' . ($r->employee->l_name ?? '')) ?: translate('Unknown') }}</strong>
                    <small>{{ $r->employee->designation ?: translate('Staff') }}</small>
                </div>
                <div class="time">{{ optional($r->clock_in_at)->format('H:i') }}</div>
                <div class="time">{{ optional($r->clock_out_at)->format('H:i') }}</div>
                <div class="duration">{{ $minutesToHHMM($r->workedMinutes()) }}</div>
                <div>
                    <a href="{{ route('admin.attendance.employee', ['id' => $r->admin_id]) }}" style="font-size:11px; color:#6A6A70;">{{ translate('History') }} →</a>
                </div>
            </div>
        @empty
            <div class="lh-empty">{{ translate('No closed shifts yet today.') }}</div>
        @endforelse
    </div>

    @if($absent->count() > 0)
    <div class="lh-card">
        <h3>{{ translate('Not yet clocked in') }} <span class="count">· {{ $absent->count() }}</span></h3>
        @foreach($absent as $a)
            <span class="lh-absent-row">
                {{ trim(($a->f_name ?? '') . ' ' . ($a->l_name ?? '')) ?: translate('Staff') }}
                @if($a->designation)<small style="color:#A0524F;"> · {{ $a->designation }}</small>@endif
            </span>
        @endforeach
    </div>
    @endif

</div>

{{-- Manual clock-in modal --}}
<div class="modal-overlay" id="lh-manual-modal" onclick="if(event.target===this) this.classList.remove('open')">
    <div class="modal-card">
        <h2>{{ translate('Clock another employee') }}</h2>
        <p>{{ translate('Pick a staff member, then choose Clock-in or Clock-out. Use this for waiters/chefs and to correct forgotten clocks.') }}</p>
        <label>{{ translate('Employee') }}</label>
        <select id="lh-manual-target" name="admin_id">
            @foreach($eligibleStaff as $s)
                <option value="{{ $s->id }}">{{ trim(($s->f_name ?? '') . ' ' . ($s->l_name ?? '')) }}{{ $s->designation ? ' · ' . $s->designation : '' }}</option>
            @endforeach
        </select>
        <label style="display:block; margin-top:10px;">{{ translate('Notes (optional)') }}</label>
        <input type="text" id="lh-manual-notes" maxlength="255" placeholder="{{ translate('e.g. Forgot to clock at 09:00') }}" />

        <div class="actions">
            <form method="POST" action="{{ route('admin.attendance.clock-in') }}" style="flex:1;">
                @csrf
                <input type="hidden" name="admin_id" id="lh-mi-id" />
                <input type="hidden" name="notes" id="lh-mi-notes" />
                <button type="submit" class="btn btn-success" style="width:100%;"
                        onclick="document.getElementById('lh-mi-id').value=document.getElementById('lh-manual-target').value;document.getElementById('lh-mi-notes').value=document.getElementById('lh-manual-notes').value;">
                    {{ translate('Clock-in') }}
                </button>
            </form>
            <form method="POST" action="{{ route('admin.attendance.clock-out') }}" style="flex:1;">
                @csrf
                <input type="hidden" name="admin_id" id="lh-mo-id" />
                <input type="hidden" name="notes" id="lh-mo-notes" />
                <button type="submit" class="btn btn-warning" style="width:100%;"
                        onclick="document.getElementById('lh-mo-id').value=document.getElementById('lh-manual-target').value;document.getElementById('lh-mo-notes').value=document.getElementById('lh-manual-notes').value;">
                    {{ translate('Clock-out') }}
                </button>
            </form>
        </div>
        <div style="text-align:center; margin-top:8px;">
            <button type="button" class="btn btn-light btn-sm" onclick="document.getElementById('lh-manual-modal').classList.remove('open')">{{ translate('Cancel') }}</button>
        </div>
    </div>
</div>
@endsection
