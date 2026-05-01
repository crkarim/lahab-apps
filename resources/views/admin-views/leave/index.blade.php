@extends('layouts.admin.app')

@section('title', translate('Leave Management'))

@section('content')
@php
    $viewerId   = auth('admin')->user()?->id;
    $statusPill = function (string $s) {
        return match ($s) {
            'pending'   => '<span class="pill-status pending">PENDING</span>',
            'approved'  => '<span class="pill-status approved">APPROVED</span>',
            'rejected'  => '<span class="pill-status rejected">REJECTED</span>',
            'cancelled' => '<span class="pill-status cancelled">CANCELLED</span>',
            default     => '<span class="pill-status">'. strtoupper($s) .'</span>',
        };
    };
@endphp

<style>
    .lh-lv-page { max-width: 1180px; margin: 0 auto; }
    .lh-lv-hero {
        background: linear-gradient(135deg, #fff 0%, #eef7ff 100%);
        border: 1px solid #d6e7f7; border-radius: 16px;
        padding: 22px 26px; margin-bottom: 18px;
        display: flex; gap: 18px; align-items: center; flex-wrap: wrap;
    }
    .lh-lv-hero .icon {
        width: 56px; height: 56px; border-radius: 50%;
        background: rgba(71, 148, 255, 0.14); color: #4794FF;
        display: flex; align-items: center; justify-content: center;
        font-size: 26px; flex-shrink: 0;
    }
    .lh-lv-hero h1 { margin: 0; font-size: 20px; font-weight: 800; color: #1A1A1A; }
    .lh-lv-hero p  { margin: 2px 0 0; color: #6A6A70; font-size: 13px; max-width: 640px; }
    .lh-lv-hero .actions { margin-left: auto; }

    .lh-bal-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px; margin-bottom: 18px; }
    @media (max-width: 1000px) { .lh-bal-grid { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 600px)  { .lh-bal-grid { grid-template-columns: repeat(2, 1fr); } }
    .lh-bal {
        background: #fff; border: 1px solid #E5E7EB; border-radius: 12px;
        padding: 12px 14px; position: relative; overflow: hidden;
    }
    .lh-bal::before {
        content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px;
        background: var(--lh-bal-color, #6A6A70);
    }
    .lh-bal .label {
        font-size: 10px; font-weight: 800; color: #6A6A70;
        text-transform: uppercase; letter-spacing: 1.1px;
    }
    .lh-bal .value {
        font-size: 22px; font-weight: 800; color: #1A1A1A;
        font-variant-numeric: tabular-nums; margin-top: 2px;
    }
    .lh-bal .sub {
        font-size: 11px; color: #6A6A70; margin-top: 2px;
    }

    .lh-card {
        background: #fff; border: 1px solid #E5E7EB; border-radius: 12px;
        padding: 4px 0; margin-bottom: 14px; overflow-x: auto;
    }
    .lh-card h3 {
        font-size: 11px; font-weight: 800; letter-spacing: 1.4px;
        color: #6A6A70; text-transform: uppercase; margin: 14px 16px 10px;
    }
    .lh-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .lh-table th {
        font-size: 11px; font-weight: 700; color: #6A6A70;
        text-transform: uppercase; letter-spacing: 1px;
        padding: 10px 14px; border-bottom: 1px solid #F0F2F5;
        text-align: left; white-space: nowrap;
    }
    .lh-table td { padding: 10px 14px; border-bottom: 1px solid #F0F2F5; vertical-align: middle; }
    .lh-table tr:last-child td { border-bottom: 0; }
    .lh-table .num { font-variant-numeric: tabular-nums; font-weight: 700; color: #1A1A1A; text-align: right; }
    .lh-table .who strong { color: #1A1A1A; }
    .lh-table .who small  { display: block; color: #6A6A70; font-size: 11px; }
    .lh-table .code-pill {
        display: inline-block; padding: 1px 6px;
        background: #FFF4E5; color: #E67E22;
        border-radius: 3px; font-family: monospace;
        font-size: 11px; font-weight: 700;
    }
    .lh-table .type-chip {
        display: inline-block; padding: 2px 8px;
        font-size: 11px; font-weight: 700;
        border-radius: 999px; color: #fff;
    }
    .lh-table .pill-status {
        display: inline-block; padding: 2px 8px;
        font-size: 10px; font-weight: 800; letter-spacing: 1px;
        border-radius: 999px;
    }
    .lh-table .pill-status.pending   { background: #FFF4E5; color: #B45A0A; }
    .lh-table .pill-status.approved  { background: #ECFFEF; color: #1E8E3E; }
    .lh-table .pill-status.rejected  { background: #FFE9EA; color: #C82626; }
    .lh-table .pill-status.cancelled { background: #F0F2F5; color: #6A6A70; }
    .lh-table .row-actions { display: flex; gap: 6px; justify-content: flex-end; flex-wrap: wrap; }
    .lh-table .row-actions .btn { padding: 3px 10px; font-size: 11px; font-weight: 700; }
    .lh-empty { padding: 22px; text-align: center; color: #6A6A70; font-size: 13px; }

    .modal-overlay {
        position: fixed; inset: 0; background: rgba(0,0,0,0.5);
        display: none; align-items: center; justify-content: center;
        z-index: 1050;
    }
    .modal-overlay.open { display: flex; }
    .modal-card {
        background: #fff; border-radius: 14px;
        max-width: 520px; width: 92%; padding: 22px 24px;
        max-height: 90vh; overflow-y: auto;
    }
    .modal-card h2 { font-size: 18px; font-weight: 800; margin: 0 0 4px; color: #1A1A1A; }
    .modal-card p  { color: #6A6A70; font-size: 13px; margin: 0 0 14px; }
    .modal-card label { font-size: 12px; font-weight: 700; color: #6A6A70; }
    .modal-card input, .modal-card select, .modal-card textarea {
        width: 100%; border: 1px solid #E5E7EB; border-radius: 8px;
        padding: 9px 12px; font-size: 14px; margin-top: 4px;
    }
    .modal-card .actions { display: flex; gap: 8px; margin-top: 16px; }

    .lh-bd-note {
        background: #FFF8E1; border: 1px solid #F4DDA1; border-radius: 10px;
        padding: 10px 14px; margin-bottom: 14px;
        font-size: 12px; color: #6A4A0A;
    }
</style>

<div class="lh-lv-page">

    @if(session('error'))<div class="alert alert-soft-danger">{{ session('error') }}</div>@endif
    @if(session('success'))<div class="alert alert-soft-success">{{ session('success') }}</div>@endif

    @if(!empty($needsManager))
        <div class="alert alert-soft-warning" style="font-size:13px;">
            <strong>{{ translate('Heads up') }}:</strong>
            {{ translate('You don\'t have a "Reports to" manager set. Until that\'s done, only Master Admin can approve your leave requests. Ask HR to set your direct manager on your employee record.') }}
        </div>
    @endif

    <div class="lh-lv-hero">
        <div class="icon">🏖️</div>
        <div>
            <h1>{{ translate('Leave management') }}</h1>
            <p>{{ translate('File requests, see your remaining balance, and review your team\'s pending approvals. Defaults follow the BD Labour Act 2006 — Casual 10d, Sick 14d, Annual 20d, Festival 11d, Maternity 112d.') }}</p>
        </div>
        <div class="actions">
            <button type="button" class="btn btn-primary" onclick="document.getElementById('lh-lv-new-modal').classList.add('open')">
                + {{ translate('Request leave') }}
            </button>
        </div>
    </div>

    {{-- Balance tiles for the viewer (skipped for Master Admin) --}}
    @if($myBalances->count() > 0)
    <div class="lh-bal-grid">
        @foreach($myBalances as $bal)
            @php $t = $bal['type']; @endphp
            <div class="lh-bal" style="--lh-bal-color: {{ $t->color }};">
                <div class="label">{{ $t->name }}</div>
                <div class="value">{{ $bal['remaining'] }}<span style="font-size:13px; font-weight:600; color:#6A6A70;"> / {{ (int) $t->days_per_year }}</span></div>
                <div class="sub">{{ $bal['taken'] }} {{ translate('taken in') }} {{ now()->format('Y') }}</div>
            </div>
        @endforeach
    </div>
    @endif

    {{-- Pending approvals queue (managers / Master Admin) --}}
    @if($isManager)
    <div class="lh-card">
        <h3>
            {{ translate('Pending approvals') }}
            <small style="font-weight:600; color:#1A1A1A; margin-left:6px;">{{ $pendingApprovals->count() }}</small>
        </h3>
        <table class="lh-table">
            <thead>
                <tr>
                    <th>{{ translate('Employee') }}</th>
                    <th>{{ translate('Type') }}</th>
                    <th>{{ translate('Dates') }}</th>
                    <th class="num" style="text-align:right;">{{ translate('Days') }}</th>
                    <th>{{ translate('Reason') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($pendingApprovals as $r)
                <tr>
                    <td class="who">
                        <strong>{{ trim(($r->employee->f_name ?? '') . ' ' . ($r->employee->l_name ?? '')) ?: '—' }}</strong>
                        <small>
                            @if($r->employee?->employee_code)<span class="code-pill">{{ $r->employee->employee_code }}</span>@endif
                            {{ $r->employee?->designation }}
                            @if($r->branch) · {{ $r->branch->name }}@endif
                        </small>
                    </td>
                    <td>
                        <span class="type-chip" style="background: {{ $r->type?->color ?: '#6A6A70' }};">
                            {{ $r->type?->name ?: '—' }}
                        </span>
                    </td>
                    <td style="font-size:12px; color:#1A1A1A;">
                        {{ optional($r->from_date)->format('d M, y') }}
                        @if(!$r->from_date->equalTo($r->to_date))
                            <span style="color:#6A6A70;">→</span> {{ optional($r->to_date)->format('d M, y') }}
                        @endif
                    </td>
                    <td class="num">{{ $r->days }}</td>
                    <td style="max-width:240px; color:#6A6A70; font-size:12px;">{{ $r->reason ?: '—' }}</td>
                    <td>
                        <div class="row-actions">
                            <form method="POST" action="{{ route('admin.leaves.approve', ['id' => $r->id]) }}"
                                  onsubmit="return confirm('{{ translate('Approve this leave request?') }}')">
                                @csrf
                                <button type="submit" class="btn btn-success">{{ translate('Approve') }}</button>
                            </form>
                            <form method="POST" action="{{ route('admin.leaves.reject', ['id' => $r->id]) }}"
                                  onsubmit="return confirm('{{ translate('Reject this leave request?') }}')">
                                @csrf
                                <button type="submit" class="btn btn-light" style="color:#C82626;">{{ translate('Reject') }}</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="lh-empty">{{ translate('No pending approvals — your team is square.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @endif

    {{-- My own requests --}}
    <div class="lh-card">
        <h3>
            {{ translate('My requests') }}
            <small style="font-weight:600; color:#1A1A1A; margin-left:6px;">{{ $myRequests->count() }}</small>
        </h3>
        <table class="lh-table">
            <thead>
                <tr>
                    <th>{{ translate('Type') }}</th>
                    <th>{{ translate('Dates') }}</th>
                    <th class="num" style="text-align:right;">{{ translate('Days') }}</th>
                    <th>{{ translate('Reason') }}</th>
                    <th>{{ translate('Status') }}</th>
                    <th>{{ translate('Reviewed by') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($myRequests as $r)
                <tr>
                    <td>
                        <span class="type-chip" style="background: {{ $r->type?->color ?: '#6A6A70' }};">
                            {{ $r->type?->name ?: '—' }}
                        </span>
                    </td>
                    <td style="font-size:12px; color:#1A1A1A;">
                        {{ optional($r->from_date)->format('d M, y') }}
                        @if(!$r->from_date->equalTo($r->to_date))
                            <span style="color:#6A6A70;">→</span> {{ optional($r->to_date)->format('d M, y') }}
                        @endif
                    </td>
                    <td class="num">{{ $r->days }}</td>
                    <td style="max-width:220px; color:#6A6A70; font-size:12px;">{{ $r->reason ?: '—' }}</td>
                    <td>{!! $statusPill($r->status) !!}</td>
                    <td style="font-size:12px; color:#6A6A70;">
                        @if($r->reviewedBy)
                            {{ trim(($r->reviewedBy->f_name ?? '') . ' ' . ($r->reviewedBy->l_name ?? '')) }}<br>
                            <small>{{ optional($r->reviewed_at)->format('d M, H:i') }}</small>
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        <div class="row-actions">
                            @if($r->status === 'pending')
                                <form method="POST" action="{{ route('admin.leaves.cancel', ['id' => $r->id]) }}"
                                      onsubmit="return confirm('{{ translate('Withdraw this request?') }}')">
                                    @csrf
                                    <button type="submit" class="btn btn-light">{{ translate('Withdraw') }}</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="lh-empty">{{ translate('You haven\'t filed any leave requests yet.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{-- Recent decisions across the branch --}}
    <div class="lh-card">
        <h3>{{ translate('Recent decisions') }}</h3>
        <table class="lh-table">
            <thead>
                <tr>
                    <th>{{ translate('Employee') }}</th>
                    <th>{{ translate('Type') }}</th>
                    <th>{{ translate('Dates') }}</th>
                    <th class="num" style="text-align:right;">{{ translate('Days') }}</th>
                    <th>{{ translate('Status') }}</th>
                    <th>{{ translate('Reviewed by') }}</th>
                </tr>
            </thead>
            <tbody>
            @forelse($recentDecisions as $r)
                <tr>
                    <td class="who">
                        <strong>{{ trim(($r->employee->f_name ?? '') . ' ' . ($r->employee->l_name ?? '')) ?: '—' }}</strong>
                        @if($r->employee?->employee_code)<small><span class="code-pill">{{ $r->employee->employee_code }}</span></small>@endif
                    </td>
                    <td>
                        <span class="type-chip" style="background: {{ $r->type?->color ?: '#6A6A70' }};">
                            {{ $r->type?->name ?: '—' }}
                        </span>
                    </td>
                    <td style="font-size:12px; color:#1A1A1A;">
                        {{ optional($r->from_date)->format('d M, y') }}
                        @if(!$r->from_date->equalTo($r->to_date))
                            <span style="color:#6A6A70;">→</span> {{ optional($r->to_date)->format('d M, y') }}
                        @endif
                    </td>
                    <td class="num">{{ $r->days }}</td>
                    <td>{!! $statusPill($r->status) !!}</td>
                    <td style="font-size:12px; color:#6A6A70;">
                        @if($r->reviewedBy)
                            {{ trim(($r->reviewedBy->f_name ?? '') . ' ' . ($r->reviewedBy->l_name ?? '')) }}<br>
                            <small>{{ optional($r->reviewed_at)->format('d M, H:i') }}</small>
                        @elseif($r->status === 'cancelled')
                            <em>{{ translate('self') }}</em>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="lh-empty">{{ translate('No decisions yet.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="lh-bd-note">
        <strong>{{ translate('BD Labour Act 2006') }}:</strong>
        Sec 115 — Casual 10d/yr · Sec 116 — Sick 14d/yr · Sec 117 — Annual ~20d/yr (1 day per 18 worked days) · Sec 118 — Festival 11d/yr · Sec 46–50 — Maternity 16 weeks (112d).
    </div>

</div>

{{-- New leave request modal --}}
<div class="modal-overlay" id="lh-lv-new-modal" onclick="if(event.target===this) this.classList.remove('open')">
    <form method="POST" action="{{ route('admin.leaves.store') }}" class="modal-card">
        @csrf
        <h2>{{ translate('Request leave') }}</h2>
        <p>{{ translate('Pick the leave type, the date range, and tell your manager why. Approval is needed before it counts against your balance.') }}</p>

        @if($isManager && $eligibleStaff->count() > 0)
            <label>{{ translate('Employee') }} <span style="color:#6A6A70; font-weight:500;">({{ translate('blank = me') }})</span></label>
            <select name="admin_id">
                <option value="">— {{ translate('myself') }} ({{ trim((auth('admin')->user()?->f_name ?? '') . ' ' . (auth('admin')->user()?->l_name ?? '')) }}) —</option>
                @foreach($eligibleStaff as $s)
                    <option value="{{ $s->id }}">
                        {{ trim(($s->f_name ?? '') . ' ' . ($s->l_name ?? '')) }}
                        @if($s->employee_code) · {{ $s->employee_code }}@endif
                        @if($s->designation) · {{ $s->designation }}@endif
                    </option>
                @endforeach
            </select>
        @endif

        <label style="display:block; margin-top:10px;">{{ translate('Leave type') }}</label>
        <select name="leave_type_id" required>
            <option value="" disabled selected>— {{ translate('select') }} —</option>
            @foreach($types as $t)
                <option value="{{ $t->id }}">
                    {{ $t->name }}
                    @if($t->days_per_year > 0) · {{ $t->days_per_year }}{{ translate('d/yr') }}@endif
                    @if(!$t->is_paid) · {{ translate('unpaid') }}@endif
                </option>
            @endforeach
        </select>

        <div class="form-row" style="margin-top:10px;">
            <div class="col-md-6 form-group">
                <label>{{ translate('From') }}</label>
                <input type="date" name="from_date" required value="{{ now()->toDateString() }}" id="lh-lv-from">
            </div>
            <div class="col-md-6 form-group">
                <label>{{ translate('To') }}</label>
                <input type="date" name="to_date" required value="{{ now()->toDateString() }}" id="lh-lv-to">
            </div>
        </div>

        <label style="display:block; margin-top:10px;">
            {{ translate('Days') }}
            <span style="color:#6A6A70; font-weight:500;">({{ translate('auto from dates, override if needed') }})</span>
        </label>
        <input type="number" name="days" min="1" id="lh-lv-days" value="1">

        <label style="display:block; margin-top:10px;">{{ translate('Reason') }}</label>
        <textarea name="reason" rows="3" maxlength="1000" placeholder="{{ translate('Why are you requesting leave?') }}"></textarea>

        <div class="actions">
            <button type="button" class="btn btn-light" style="flex:1;" onclick="document.getElementById('lh-lv-new-modal').classList.remove('open')">{{ translate('Cancel') }}</button>
            <button type="submit" class="btn btn-primary" style="flex:1;">{{ translate('File request') }}</button>
        </div>
    </form>
</div>

<script>
(function () {
    var fromEl = document.getElementById('lh-lv-from');
    var toEl   = document.getElementById('lh-lv-to');
    var daysEl = document.getElementById('lh-lv-days');
    function recompute() {
        if (!fromEl.value || !toEl.value) return;
        var f = new Date(fromEl.value), t = new Date(toEl.value);
        if (isNaN(f) || isNaN(t) || t < f) return;
        var diff = Math.round((t - f) / 86400000) + 1;
        daysEl.value = diff > 0 ? diff : 1;
    }
    fromEl && fromEl.addEventListener('change', recompute);
    toEl   && toEl.addEventListener('change', recompute);
})();
</script>
@endsection
