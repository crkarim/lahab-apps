@extends('layouts.admin.app')

@section('title', translate('Payroll Runs'))

@section('content')
@php $sym = fn ($v) => \App\CentralLogics\Helpers::set_symbol($v); @endphp
<style>
    .lh-runs-page { max-width: 1100px; margin: 0 auto; }
    .lh-hero {
        background: linear-gradient(135deg, #fff 0%, #fff7ee 100%);
        border: 1px solid #f1e3cf; border-radius: 16px;
        padding: 22px 26px; margin-bottom: 18px;
        display: flex; gap: 18px; align-items: center; flex-wrap: wrap;
    }
    .lh-hero .icon {
        width: 56px; height: 56px; border-radius: 50%;
        background: rgba(30, 142, 62, 0.14); color: #1E8E3E;
        display: flex; align-items: center; justify-content: center;
        font-size: 26px; flex-shrink: 0;
    }
    .lh-hero h1 { margin: 0; font-size: 20px; font-weight: 800; color: #1A1A1A; }
    .lh-hero p { margin: 2px 0 0; color: #6A6A70; font-size: 13px; }
    .lh-hero .actions { margin-left: auto; }

    .lh-card { background: #fff; border: 1px solid #E5E7EB; border-radius: 12px; padding: 4px 0; overflow-x: auto; }
    .lh-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .lh-table th { font-size: 11px; font-weight: 700; color: #6A6A70; text-transform: uppercase; letter-spacing: 1px; padding: 10px 14px; border-bottom: 1px solid #F0F2F5; text-align: left; white-space: nowrap; }
    .lh-table td { padding: 11px 14px; border-bottom: 1px solid #F0F2F5; vertical-align: middle; }
    .lh-table tr:last-child td { border-bottom: 0; }
    .lh-table .num { font-variant-numeric: tabular-nums; font-weight: 700; color: #1A1A1A; text-align: right; }
    .lh-table .net { font-weight: 800; color: #1E8E3E; }
    .lh-status {
        display: inline-block; padding: 3px 10px;
        border-radius: 999px; font-size: 10px; font-weight: 800; letter-spacing: 1px;
    }
    .lh-status.draft  { background: #FFF4E5; color: #B45A0A; }
    .lh-status.locked { background: #E6F2FF; color: #4794FF; }
    .lh-status.paid   { background: #ECFFEF; color: #1E8E3E; }
    .lh-empty { padding: 28px; text-align: center; color: #6A6A70; font-size: 13px; }

    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1050; }
    .modal-overlay.open { display: flex; }
    .modal-card { background: #fff; border-radius: 14px; max-width: 460px; width: 92%; padding: 22px 24px; }
    .modal-card h2 { font-size: 18px; font-weight: 800; margin: 0 0 4px; color: #1A1A1A; }
    .modal-card p { color: #6A6A70; font-size: 13px; margin: 0 0 14px; }
    .modal-card label { font-size: 12px; font-weight: 700; color: #6A6A70; }
    .modal-card input, .modal-card textarea { width: 100%; border: 1px solid #E5E7EB; border-radius: 8px; padding: 9px 12px; font-size: 14px; margin-top: 4px; }
    .modal-card .actions { display: flex; gap: 8px; margin-top: 16px; }
</style>

<div class="lh-runs-page">

    @if(session('error'))<div class="alert alert-soft-danger">{{ session('error') }}</div>@endif
    @if(session('success'))<div class="alert alert-soft-success">{{ session('success') }}</div>@endif

    <div class="lh-hero">
        <div class="icon">📑</div>
        <div>
            <h1>{{ translate('Payroll Runs') }}</h1>
            <p>{{ translate('Locked records of every payroll cycle. Create a draft for a date range, review, then lock to commit + reduce advance balances.') }}</p>
        </div>
        <div class="actions">
            <button type="button" class="btn btn-primary" onclick="document.getElementById('lh-new-modal').classList.add('open')">
                + {{ translate('New run') }}
            </button>
        </div>
    </div>

    <div class="lh-card">
        <table class="lh-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ translate('Period') }}</th>
                    <th>{{ translate('Branch') }}</th>
                    <th>{{ translate('Status') }}</th>
                    <th class="num" style="text-align:right;">{{ translate('Gross') }}</th>
                    <th class="num" style="text-align:right;">{{ translate('Net') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($runs as $r)
                <tr>
                    <td><strong>#{{ $r->id }}</strong></td>
                    <td>
                        {{ optional($r->period_from)->format('d M') }} → {{ optional($r->period_to)->format('d M Y') }}
                        @if($r->locked_at)
                            <small style="display:block; color:#6A6A70; font-size:11px;">
                                {{ translate('Locked') }}: {{ $r->locked_at->format('d M, H:i') }}
                            </small>
                        @endif
                    </td>
                    <td>{{ $r->branch?->name ?? '—' }}</td>
                    <td><span class="lh-status {{ $r->status }}">{{ strtoupper($r->status) }}</span></td>
                    <td class="num">{{ $r->status === 'draft' ? '—' : $sym($r->total_gross) }}</td>
                    <td class="num net">{{ $r->status === 'draft' ? '—' : $sym($r->total_net) }}</td>
                    <td>
                        <a href="{{ route('admin.payroll-runs.show', ['id' => $r->id]) }}" class="btn btn-light btn-sm">
                            {{ $r->isDraft() ? translate('Review') : translate('View') }} →
                        </a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="lh-empty">{{ translate('No payroll runs yet — start one above.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:14px;">
        {{ $runs->links() }}
    </div>
</div>

{{-- New run modal --}}
<div class="modal-overlay" id="lh-new-modal" onclick="if(event.target===this) this.classList.remove('open')">
    <form method="POST" action="{{ route('admin.payroll-runs.store') }}" class="modal-card">
        @csrf
        <h2>{{ translate('New payroll run') }}</h2>
        <p>{{ translate('Pick the period the run covers. Defaults to last full month.') }}</p>
        <div class="form-row">
            <div class="col-md-6 form-group">
                <label>{{ translate('From') }}</label>
                <input type="date" name="period_from" required value="{{ now()->subMonth()->startOfMonth()->format('Y-m-d') }}">
            </div>
            <div class="col-md-6 form-group">
                <label>{{ translate('To') }}</label>
                <input type="date" name="period_to" required value="{{ now()->subMonth()->endOfMonth()->format('Y-m-d') }}">
            </div>
        </div>
        <label>{{ translate('Notes (optional)') }}</label>
        <textarea name="notes" rows="2" placeholder="{{ translate('e.g. April 2026 monthly payroll') }}"></textarea>
        <div class="actions">
            <button type="button" class="btn btn-light" style="flex:1;" onclick="document.getElementById('lh-new-modal').classList.remove('open')">{{ translate('Cancel') }}</button>
            <button type="submit" class="btn btn-primary" style="flex:1;">{{ translate('Create draft') }}</button>
        </div>
    </form>
</div>

@endsection
