@extends('layouts.admin.app')

@section('title', translate('Shifts'))

@section('content')
<style>
    .lh-shift-page { max-width: 1100px; margin: 0 auto; }
    .lh-shift-hero {
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
    .lh-shift-hero .icon {
        width: 56px; height: 56px;
        border-radius: 50%;
        background: rgba(230, 126, 34, 0.14);
        color: #E67E22;
        display: flex; align-items: center; justify-content: center;
        font-size: 26px;
        flex-shrink: 0;
    }
    .lh-shift-hero h1 { margin: 0; font-size: 20px; font-weight: 800; color: #1A1A1A; }
    .lh-shift-hero p  { margin: 2px 0 0; color: #6A6A70; font-size: 13px; }
    .lh-shift-hero .actions { margin-left: auto; }
    .lh-shift-hero .btn { font-weight: 700; }

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
    .lh-shift-row {
        display: grid;
        grid-template-columns: 60px 1fr 140px 140px 140px;
        gap: 10px;
        padding: 10px 4px;
        border-bottom: 1px solid #F0F2F5;
        align-items: center;
    }
    .lh-shift-row:last-child { border-bottom: 0; }
    .lh-shift-row .id-pill {
        background: #FFF4E5; color: #E67E22;
        font-weight: 800; font-size: 12px;
        padding: 4px 8px; border-radius: 6px;
        text-align: center;
        font-variant-numeric: tabular-nums;
    }
    .lh-shift-row .who {
        font-size: 13px; color: #1A1A1A; font-weight: 600;
    }
    .lh-shift-row .who small { display: block; color: #6A6A70; font-weight: 500; font-size: 11px; }
    .lh-shift-row .amount {
        font-variant-numeric: tabular-nums;
        font-weight: 700;
        color: #1A1A1A;
    }
    .lh-shift-row .amount.muted { color: #6A6A70; font-weight: 500; }
    .lh-shift-row .var-pos { color: #1E8E3E; font-weight: 800; }
    .lh-shift-row .var-neg { color: #E84D4F; font-weight: 800; }
    .lh-shift-row .var-zero { color: #6A6A70; font-weight: 600; }
    .lh-shift-row a.open-link {
        color: #E67E22; font-weight: 700; font-size: 12px;
    }
    .lh-empty {
        padding: 22px; text-align: center;
        color: #6A6A70; font-size: 13px;
    }

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
    .modal-card input, .modal-card textarea {
        width: 100%; border: 1px solid #E5E7EB; border-radius: 8px;
        padding: 9px 12px; font-size: 14px; margin-top: 4px;
    }
    .modal-card .actions { display: flex; gap: 8px; margin-top: 16px; }
    .modal-card .actions .btn { flex: 1; font-weight: 700; }
</style>

<div class="lh-shift-page">

    @if(session('error'))
        <div class="alert alert-soft-danger" role="alert">{{ session('error') }}</div>
    @endif
    @if(session('success'))
        <div class="alert alert-soft-success" role="alert">{{ session('success') }}</div>
    @endif

    <div class="lh-shift-hero">
        <div class="icon">⏱</div>
        <div>
            <h1>{{ translate('Shifts') }}</h1>
            <p>{{ translate('Open the drawer at the start of your shift, close it at the end. Variance is tracked automatically.') }}</p>
        </div>
        <div class="actions">
            @if($mine)
                <a href="{{ route('admin.shifts.show', ['id' => $mine->id]) }}" class="btn btn-warning">
                    {{ translate('My open shift') }} · #{{ $mine->id }} →
                </a>
            @else
                <button type="button" class="btn btn-primary" onclick="document.getElementById('lh-open-modal').classList.add('open')">
                    + {{ translate('Open shift') }}
                </button>
            @endif
        </div>
    </div>

    @if($openShifts->count() > 0)
    <div class="lh-card">
        <h3>{{ translate('Open shifts') }} <span style="color:#1E8E3E">●</span></h3>
        @foreach($openShifts as $s)
            <div class="lh-shift-row">
                <div class="id-pill">#{{ $s->id }}</div>
                <div class="who">
                    {{ trim(($s->openedBy->f_name ?? '') . ' ' . ($s->openedBy->l_name ?? '')) ?: translate('Unknown') }}
                    <small>{{ optional($s->opened_at)->format('d M, H:i') }} · {{ $s->branch?->name ?? '—' }}</small>
                </div>
                <div class="amount muted">{{ translate('Opening') }}: {{ \App\CentralLogics\Helpers::set_symbol($s->opening_cash) }}</div>
                <div></div>
                <div><a href="{{ route('admin.shifts.show', ['id' => $s->id]) }}" class="open-link">{{ translate('Manage') }} →</a></div>
            </div>
        @endforeach
    </div>
    @endif

    <div class="lh-card">
        <h3>{{ translate('Recently closed') }}</h3>
        @forelse($recent as $s)
            <div class="lh-shift-row">
                <div class="id-pill">#{{ $s->id }}</div>
                <div class="who">
                    {{ trim(($s->openedBy->f_name ?? '') . ' ' . ($s->openedBy->l_name ?? '')) ?: translate('Unknown') }}
                    <small>{{ optional($s->opened_at)->format('d M, H:i') }} → {{ optional($s->closed_at)->format('H:i') }}</small>
                </div>
                <div class="amount muted">{{ translate('Expected') }}: {{ \App\CentralLogics\Helpers::set_symbol($s->expected_cash) }}</div>
                <div class="amount">{{ translate('Actual') }}: {{ \App\CentralLogics\Helpers::set_symbol($s->actual_cash) }}</div>
                <div class="amount {{ $s->variance == 0 ? 'var-zero' : ($s->variance > 0 ? 'var-pos' : 'var-neg') }}">
                    @if($s->variance == 0)
                        {{ translate('On the dot') }}
                    @elseif($s->variance > 0)
                        + {{ \App\CentralLogics\Helpers::set_symbol($s->variance) }}
                    @else
                        − {{ \App\CentralLogics\Helpers::set_symbol(abs($s->variance)) }}
                    @endif
                    <a href="{{ route('admin.shifts.show', ['id' => $s->id]) }}" style="display:block; font-size:11px; font-weight:600; color:#6A6A70;">{{ translate('View') }} →</a>
                </div>
            </div>
        @empty
            <div class="lh-empty">{{ translate('No closed shifts yet.') }}</div>
        @endforelse
    </div>

</div>

{{-- Open shift modal --}}
<div class="modal-overlay" id="lh-open-modal" onclick="if(event.target===this) this.classList.remove('open')">
    <form method="POST" action="{{ route('admin.shifts.open') }}" class="modal-card">
        @csrf
        <h2>{{ translate('Open shift') }}</h2>
        <p>{{ translate('Declare the opening cash you brought to the till. Leave blank if starting empty.') }}</p>
        <label>{{ translate('Opening cash (Tk)') }}</label>
        <input type="number" name="opening_cash" value="0" step="0.01" min="0" />
        <label style="display:block; margin-top:10px;">{{ translate('Notes (optional)') }}</label>
        <textarea name="notes" rows="2" placeholder="{{ translate('e.g. Brought 500 from previous shift') }}"></textarea>
        <div class="actions">
            <button type="button" class="btn btn-light" onclick="document.getElementById('lh-open-modal').classList.remove('open')">{{ translate('Cancel') }}</button>
            <button type="submit" class="btn btn-primary">{{ translate('Open shift') }}</button>
        </div>
    </form>
</div>

@endsection
