@extends('layouts.admin.app')

@section('title', translate('Salary Advances'))

@section('content')
@php
    $sym = fn ($v) => \App\CentralLogics\Helpers::set_symbol($v);
@endphp

<style>
    .lh-adv-page { max-width: 1100px; margin: 0 auto; }
    .lh-adv-hero {
        background: linear-gradient(135deg, #fff 0%, #fff7ee 100%);
        border: 1px solid #f1e3cf; border-radius: 16px;
        padding: 22px 26px; margin-bottom: 18px;
        display: flex; gap: 18px; align-items: center; flex-wrap: wrap;
    }
    .lh-adv-hero .icon {
        width: 56px; height: 56px; border-radius: 50%;
        background: rgba(232, 77, 79, 0.14); color: #E84D4F;
        display: flex; align-items: center; justify-content: center;
        font-size: 26px; flex-shrink: 0;
    }
    .lh-adv-hero h1 { margin: 0; font-size: 20px; font-weight: 800; color: #1A1A1A; }
    .lh-adv-hero p  { margin: 2px 0 0; color: #6A6A70; font-size: 13px; }
    .lh-adv-hero .actions { margin-left: auto; }

    .lh-totals { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 18px; }
    @media (max-width: 600px) { .lh-totals { grid-template-columns: 1fr; } }
    .lh-tile {
        background: #fff; border: 1px solid #E5E7EB; border-radius: 12px;
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

    .lh-card { background: #fff; border: 1px solid #E5E7EB; border-radius: 12px; padding: 4px 0; margin-bottom: 14px; overflow-x: auto; }
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
    .lh-table .pill-status {
        display: inline-block; padding: 2px 8px;
        font-size: 10px; font-weight: 800; letter-spacing: 1px;
        border-radius: 999px;
    }
    .lh-table .pill-status.active   { background: #FFF4E5; color: #B45A0A; }
    .lh-table .pill-status.recov    { background: #ECFFEF; color: #1E8E3E; }
    .lh-table .pill-status.cancel   { background: #F0F2F5; color: #6A6A70; }
    .lh-table .row-actions { display: flex; gap: 6px; justify-content: flex-end; }
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
        max-width: 480px; width: 92%; padding: 22px 24px;
    }
    .modal-card h2 { font-size: 18px; font-weight: 800; margin: 0 0 4px; color: #1A1A1A; }
    .modal-card p  { color: #6A6A70; font-size: 13px; margin: 0 0 14px; }
    .modal-card label { font-size: 12px; font-weight: 700; color: #6A6A70; }
    .modal-card input, .modal-card select, .modal-card textarea {
        width: 100%; border: 1px solid #E5E7EB; border-radius: 8px;
        padding: 9px 12px; font-size: 14px; margin-top: 4px;
    }
    .modal-card .actions { display: flex; gap: 8px; margin-top: 16px; }
</style>

<div class="lh-adv-page">

    @if(session('error'))<div class="alert alert-soft-danger">{{ session('error') }}</div>@endif
    @if(session('success'))<div class="alert alert-soft-success">{{ session('success') }}</div>@endif

    <div class="lh-adv-hero">
        <div class="icon">💵</div>
        <div>
            <h1>{{ translate('Salary advances') }}</h1>
            <p>{{ translate('Cash you\'ve advanced to staff. Each advance has a per-run recovery; the next payroll run deducts the recovery amount until the balance hits zero.') }}</p>
        </div>
        <div class="actions">
            <button type="button" class="btn btn-primary" onclick="document.getElementById('lh-new-modal').classList.add('open')">
                + {{ translate('Record advance') }}
            </button>
        </div>
    </div>

    <div class="lh-totals">
        <div class="lh-tile">
            <div class="label">{{ translate('Active advances') }}</div>
            <div class="value">{{ $totalsActive['count'] }}</div>
        </div>
        <div class="lh-tile">
            <div class="label">{{ translate('Original total') }}</div>
            <div class="value">{{ $sym($totalsActive['amount']) }}</div>
        </div>
        <div class="lh-tile">
            <div class="label">{{ translate('Outstanding balance') }}</div>
            <div class="value" style="color:#E84D4F;">{{ $sym($totalsActive['balance']) }}</div>
        </div>
    </div>

    <div class="lh-card">
        <h3>{{ translate('Active') }} <small style="font-weight:600; color:#1A1A1A; margin-left:6px;">{{ $active->count() }}</small></h3>
        <table class="lh-table">
            <thead>
                <tr>
                    <th>{{ translate('Employee') }}</th>
                    <th>{{ translate('Taken') }}</th>
                    <th>{{ translate('Reason') }}</th>
                    <th class="num" style="text-align:right;">{{ translate('Amount') }}</th>
                    <th class="num" style="text-align:right;">{{ translate('Per run') }}</th>
                    <th class="num" style="text-align:right;">{{ translate('Balance') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($active as $a)
                <tr>
                    <td class="who">
                        <strong>{{ trim(($a->employee->f_name ?? '') . ' ' . ($a->employee->l_name ?? '')) ?: '—' }}</strong>
                        <small>
                            @if($a->employee?->employee_code)<span class="code-pill">{{ $a->employee->employee_code }}</span>@endif
                            {{ $a->employee?->designation }}
                        </small>
                    </td>
                    <td>{{ optional($a->taken_at)->format('d M, y') }}</td>
                    <td style="max-width:220px; color:#6A6A70; font-size:12px;">{{ $a->reason ?: '—' }}</td>
                    <td class="num">{{ $sym($a->amount) }}</td>
                    <td class="num">{{ $sym($a->recovery_per_run) }}</td>
                    <td class="num" style="color:#E84D4F;">{{ $sym($a->balance) }}</td>
                    <td>
                        <div class="row-actions">
                            <button type="button" class="btn btn-light"
                                onclick="lhPrepRecover({{ $a->id }}, {{ $a->balance }}, '{{ addslashes(trim(($a->employee->f_name ?? '') . ' ' . ($a->employee->l_name ?? ''))) }}')">
                                {{ translate('Recover') }}
                            </button>
                            <form method="POST" action="{{ route('admin.salary-advances.cancel', ['id' => $a->id]) }}"
                                  onsubmit="return confirm('{{ translate('Cancel this advance? Cannot be reversed.') }}')">
                                @csrf
                                <button type="submit" class="btn btn-light" style="color:#E84D4F;">✕</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="lh-empty">{{ translate('No active advances.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="lh-card">
        <h3>{{ translate('Recently closed') }}</h3>
        <table class="lh-table">
            <thead>
                <tr>
                    <th>{{ translate('Employee') }}</th>
                    <th>{{ translate('Taken') }}</th>
                    <th>{{ translate('Status') }}</th>
                    <th class="num" style="text-align:right;">{{ translate('Amount') }}</th>
                    <th>{{ translate('Notes') }}</th>
                </tr>
            </thead>
            <tbody>
            @forelse($closed as $a)
                <tr>
                    <td class="who"><strong>{{ trim(($a->employee->f_name ?? '') . ' ' . ($a->employee->l_name ?? '')) }}</strong></td>
                    <td>{{ optional($a->taken_at)->format('d M, y') }}</td>
                    <td>
                        @if($a->status === 'recovered')
                            <span class="pill-status recov">RECOVERED</span>
                        @else
                            <span class="pill-status cancel">CANCELLED</span>
                        @endif
                    </td>
                    <td class="num">{{ $sym($a->amount) }}</td>
                    <td style="max-width:300px; font-size:11px; color:#6A6A70;">{{ \Illuminate\Support\Str::limit($a->notes, 80) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="lh-empty">{{ translate('No closed advances yet.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

</div>

{{-- New advance modal --}}
<div class="modal-overlay" id="lh-new-modal" onclick="if(event.target===this) this.classList.remove('open')">
    <form method="POST" action="{{ route('admin.salary-advances.store') }}" class="modal-card">
        @csrf
        <h2>{{ translate('Record advance') }}</h2>
        <p>{{ translate('Pick the employee, total amount given, and recovery per payroll run.') }}</p>
        <label>{{ translate('Employee') }}</label>
        <select name="admin_id" required>
            <option value="" disabled selected>— {{ translate('select') }} —</option>
            @foreach($eligibleStaff as $s)
                <option value="{{ $s->id }}">
                    {{ trim(($s->f_name ?? '') . ' ' . ($s->l_name ?? '')) }}
                    @if($s->employee_code) · {{ $s->employee_code }}@endif
                    @if($s->designation) · {{ $s->designation }}@endif
                </option>
            @endforeach
        </select>

        <div class="form-row" style="margin-top:10px;">
            <div class="col-md-6 form-group">
                <label>{{ translate('Amount (Tk)') }}</label>
                <input type="number" name="amount" step="0.01" min="0.01" required placeholder="20000.00">
            </div>
            <div class="col-md-6 form-group">
                <label>{{ translate('Recovery / run (Tk)') }}</label>
                <input type="number" name="recovery_per_run" step="0.01" min="0.01" required placeholder="5000.00">
            </div>
        </div>

        <label>{{ translate('Date taken') }}</label>
        <input type="date" name="taken_at" value="{{ now()->toDateString() }}">

        <label style="display:block; margin-top:10px;">{{ translate('Reason') }}</label>
        <input type="text" name="reason" maxlength="255" placeholder="{{ translate('e.g. Medical emergency, festival') }}">

        <label style="display:block; margin-top:10px;">{{ translate('Notes (optional)') }}</label>
        <textarea name="notes" rows="2"></textarea>

        <div class="actions">
            <button type="button" class="btn btn-light" style="flex:1;" onclick="document.getElementById('lh-new-modal').classList.remove('open')">{{ translate('Cancel') }}</button>
            <button type="submit" class="btn btn-primary" style="flex:1;">{{ translate('Record') }}</button>
        </div>
    </form>
</div>

{{-- Manual recover modal --}}
<div class="modal-overlay" id="lh-recover-modal" onclick="if(event.target===this) this.classList.remove('open')">
    <form method="POST" id="lh-recover-form" class="modal-card">
        @csrf
        <h2>{{ translate('Manual recovery') }}</h2>
        <p>{{ translate('For when the employee returns the cash directly outside of a payroll run. The advance balance reduces by this amount.') }}</p>
        <p style="font-size:13px; color:#1A1A1A;">
            {{ translate('Employee') }}: <strong id="lh-recover-name"></strong><br>
            {{ translate('Remaining balance') }}: <strong id="lh-recover-balance"></strong>
        </p>
        <label>{{ translate('Amount returned (Tk)') }}</label>
        <input type="number" name="amount" step="0.01" min="0.01" required>
        <label style="display:block; margin-top:10px;">{{ translate('Notes (optional)') }}</label>
        <input type="text" name="notes" maxlength="255">
        <div class="actions">
            <button type="button" class="btn btn-light" style="flex:1;" onclick="document.getElementById('lh-recover-modal').classList.remove('open')">{{ translate('Cancel') }}</button>
            <button type="submit" class="btn btn-warning" style="flex:1;">{{ translate('Recover') }}</button>
        </div>
    </form>
</div>

<script>
function lhPrepRecover(advanceId, balance, name) {
    var form = document.getElementById('lh-recover-form');
    form.action = '{{ url('admin/salary-advances') }}/' + advanceId + '/recover';
    document.getElementById('lh-recover-name').textContent = name || '—';
    document.getElementById('lh-recover-balance').textContent = 'Tk ' + (balance ? Number(balance).toFixed(2) : '0.00');
    document.querySelector('#lh-recover-form input[name=amount]').value = balance;
    document.getElementById('lh-recover-modal').classList.add('open');
}
</script>
@endsection
