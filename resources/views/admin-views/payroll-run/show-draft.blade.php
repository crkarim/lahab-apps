@extends('layouts.admin.app')

@section('title', translate('Payroll Run') . ' #' . $run->id . ' (' . translate('draft') . ')')

@section('content')
@php $sym = fn ($v) => \App\CentralLogics\Helpers::set_symbol($v); @endphp

<style>
    .lh-run-page { max-width: 1200px; margin: 0 auto; }
    .lh-banner {
        background: linear-gradient(135deg, #FFF4E5 0%, #fff 100%);
        border: 1px solid #f1e3cf; border-radius: 16px;
        padding: 18px 22px; margin-bottom: 16px;
        display: flex; gap: 14px; align-items: center; flex-wrap: wrap;
    }
    .lh-banner h1 { margin: 0; font-size: 18px; font-weight: 800; color: #1A1A1A; }
    .lh-banner p  { margin: 2px 0 0; color: #6A6A70; font-size: 13px; }
    .lh-banner .lh-badge { padding: 3px 10px; background: #FFF4E5; color: #B45A0A; border-radius: 999px; font-size: 11px; font-weight: 800; letter-spacing: 1px; }
    .lh-banner .actions { margin-left: auto; display: flex; gap: 8px; }

    .lh-totals { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-bottom: 14px; }
    @media (max-width: 800px) { .lh-totals { grid-template-columns: repeat(2, 1fr); } }
    .lh-tile { background: #fff; border: 1px solid #E5E7EB; border-radius: 12px; padding: 12px 14px; }
    .lh-tile .label { font-size: 10px; font-weight: 700; color: #6A6A70; text-transform: uppercase; letter-spacing: 1px; }
    .lh-tile .value { font-size: 18px; font-weight: 800; color: #1A1A1A; font-variant-numeric: tabular-nums; margin-top: 2px; }
    .lh-tile.gross .value { color: #1E8E3E; }

    .lh-card { background: #fff; border: 1px solid #E5E7EB; border-radius: 12px; padding: 4px 0; overflow-x: auto; }
    .lh-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .lh-table th { font-size: 11px; font-weight: 700; color: #6A6A70; text-transform: uppercase; letter-spacing: 1px; padding: 10px 14px; border-bottom: 1px solid #F0F2F5; text-align: left; white-space: nowrap; }
    .lh-table td { padding: 10px 14px; border-bottom: 1px solid #F0F2F5; vertical-align: middle; }
    .lh-table tr:last-child td { border-bottom: 0; }
    .lh-table .num { font-variant-numeric: tabular-nums; font-weight: 600; color: #1A1A1A; text-align: right; }
    .lh-table .net { font-weight: 800; color: #1E8E3E; }
    .lh-table .who strong { color: #1A1A1A; }
    .lh-table .who small  { display: block; color: #6A6A70; font-size: 11px; }
    .lh-table .code-pill { display: inline-block; padding: 1px 6px; background: #FFF4E5; color: #E67E22; border-radius: 3px; font-family: monospace; font-size: 11px; font-weight: 700; }

    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1050; }
    .modal-overlay.open { display: flex; }
    .modal-card { background: #fff; border-radius: 14px; max-width: 480px; width: 92%; padding: 22px 24px; }
    .modal-card h2 { font-size: 18px; font-weight: 800; margin: 0 0 4px; color: #1A1A1A; }
    .modal-card p  { color: #6A6A70; font-size: 13px; margin: 0 0 14px; }
    .modal-card .actions { display: flex; gap: 8px; margin-top: 16px; }
</style>

<div class="lh-run-page">

    <div style="margin-bottom:14px;">
        <a href="{{ route('admin.payroll-runs.index') }}" class="btn btn-light btn-sm">← {{ translate('All runs') }}</a>
    </div>

    @if(session('error'))<div class="alert alert-soft-danger">{{ session('error') }}</div>@endif
    @if(session('success'))<div class="alert alert-soft-success">{{ session('success') }}</div>@endif

    <div class="lh-banner">
        <span class="lh-badge">DRAFT</span>
        <div>
            <h1>{{ translate('Run') }} #{{ $run->id }} · {{ optional($run->period_from)->format('d M') }} → {{ optional($run->period_to)->format('d M Y') }}</h1>
            <p>
                {{ translate('Live preview — figures recompute from current attendance, salary structure, tip flow, and advance balances every time you load this page. Lock to commit.') }}
                @if($run->branch)· {{ $run->branch->name }}@endif
            </p>
        </div>
        <div class="actions">
            <form method="POST" action="{{ route('admin.payroll-runs.destroy', ['id' => $run->id]) }}"
                onsubmit="return confirm('{{ translate('Delete this draft run?') }}')">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-light" style="color:#E84D4F;">{{ translate('Delete draft') }}</button>
            </form>
            <button type="button" class="btn btn-warning" onclick="document.getElementById('lh-lock-modal').classList.add('open')">
                🔒 {{ translate('Lock run') }}
            </button>
        </div>
    </div>

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
            <div class="label">{{ translate('Advances') }}</div>
            <div class="value">{{ $sym($totals['advances']) }}</div>
        </div>
        <div class="lh-tile">
            <div class="label">{{ translate('Tips') }}</div>
            <div class="value">{{ $sym($totals['tips']) }}</div>
        </div>
    </div>

    <div class="lh-card">
        <table class="lh-table">
            <thead>
                <tr>
                    <th>{{ translate('Employee') }}</th>
                    <th>{{ translate('Days') }}</th>
                    <th class="num" style="text-align:right;">{{ translate('Basic') }}</th>
                    <th class="num" style="text-align:right;">{{ translate('Allow.') }}</th>
                    <th class="num" style="text-align:right;">{{ translate('Tips') }}</th>
                    <th class="num" style="text-align:right;">{{ translate('Deduct.') }}</th>
                    <th class="num" style="text-align:right;">{{ translate('Advance') }}</th>
                    <th class="num" style="text-align:right;">{{ translate('Net') }}</th>
                </tr>
            </thead>
            <tbody>
            @foreach($rows as $r)
                @php $emp = $r['employee']; @endphp
                <tr>
                    <td class="who">
                        <strong>{{ trim(($emp->f_name ?? '') . ' ' . ($emp->l_name ?? '')) }}</strong>
                        <small>
                            @if($emp->employee_code)<span class="code-pill">{{ $emp->employee_code }}</span>@endif
                            {{ $emp->designation ?: ($emp->role->name ?? translate('Staff')) }}
                        </small>
                    </td>
                    <td>{{ $r['days_clocked'] }}/{{ $r['calendar_days'] }}</td>
                    <td class="num">{{ $sym($r['prorated_basic']) }}</td>
                    <td class="num">{{ $sym($r['prorated_allowance']) }}</td>
                    <td class="num">{{ $sym($r['tip_share']) }}</td>
                    <td class="num" style="color:{{ $r['prorated_deduction'] > 0 ? '#E84D4F' : '#A0A4AB' }};">
                        {{ $r['prorated_deduction'] > 0 ? '− ' . $sym($r['prorated_deduction']) : '—' }}
                    </td>
                    <td class="num" style="color:{{ $r['advance_recovery'] > 0 ? '#E84D4F' : '#A0A4AB' }};">
                        {{ $r['advance_recovery'] > 0 ? '− ' . $sym($r['advance_recovery']) : '—' }}
                    </td>
                    <td class="num net">{{ $sym($r['net']) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div style="margin-top:14px; font-size:11px; color:#6A6A70; line-height:1.6;">
        <strong>{{ translate('Locking does:') }}</strong>
        {{ translate('snapshots all payslips above, deducts each active advance\'s recovery_per_run from its balance (marks recovered if balance hits zero), and links the recovery to this run. Locked runs cannot be edited or deleted.') }}
    </div>
</div>

<div class="modal-overlay" id="lh-lock-modal" onclick="if(event.target===this) this.classList.remove('open')">
    <form method="POST" action="{{ route('admin.payroll-runs.lock', ['id' => $run->id]) }}" class="modal-card">
        @csrf
        <h2>🔒 {{ translate('Lock run #') }}{{ $run->id }}</h2>
        <p>
            {{ translate('You\'re about to commit') }} <strong>{{ $totals['count'] }}</strong> {{ translate('payslips totaling') }}
            <strong>{{ $sym($totals['net']) }}</strong> {{ translate('net.') }}
            <br>
            {{ translate('Active advance balances will be reduced by') }} <strong>{{ $sym($totals['advances']) }}</strong>.
            <br><br>
            <em>{{ translate('Cannot be reversed.') }}</em>
        </p>
        <div class="actions">
            <button type="button" class="btn btn-light" style="flex:1;" onclick="document.getElementById('lh-lock-modal').classList.remove('open')">{{ translate('Cancel') }}</button>
            <button type="submit" class="btn btn-warning" style="flex:1;">{{ translate('Lock & commit') }}</button>
        </div>
    </form>
</div>

@endsection
