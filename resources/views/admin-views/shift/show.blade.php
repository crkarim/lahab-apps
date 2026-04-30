@extends('layouts.admin.app')

@section('title', translate('Shift') . ' #' . $shift->id)

@section('content')
<style>
    .lh-shift-detail { max-width: 1100px; margin: 0 auto; }
    .lh-detail-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 14px;
    }
    @media (max-width: 800px) {
        .lh-detail-grid { grid-template-columns: 1fr; }
    }
    .lh-card {
        background: #fff;
        border: 1px solid #E5E7EB;
        border-radius: 12px;
        padding: 18px 20px;
        margin-bottom: 14px;
    }
    .lh-card h3 {
        font-size: 11px; font-weight: 800;
        letter-spacing: 1.4px;
        color: #6A6A70;
        text-transform: uppercase;
        margin: 0 0 12px;
    }
    .lh-status {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 1.2px;
    }
    .lh-status.open { background: #E8F7EE; color: #1E8E3E; }
    .lh-status.closed { background: #F0F2F5; color: #6A6A70; }
    .lh-meta-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px 18px;
        font-size: 13px;
    }
    .lh-meta-grid div { color: #6A6A70; }
    .lh-meta-grid div strong { color: #1A1A1A; font-weight: 700; }

    .lh-money-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid #F0F2F5;
        font-size: 13px;
    }
    .lh-money-row:last-child { border-bottom: 0; }
    .lh-money-row .amt {
        font-variant-numeric: tabular-nums;
        font-weight: 700;
        color: #1A1A1A;
    }
    .lh-money-row.expected {
        background: #FFF7EE;
        margin: 8px -20px 0;
        padding: 12px 20px;
        border: 1px solid #f1e3cf;
        border-radius: 0 0 12px 12px;
        font-size: 14px;
        font-weight: 800;
    }
    .lh-money-row.surplus { color: #1E8E3E; }
    .lh-money-row.shortage { color: #E84D4F; }

    .lh-row-list {
        max-height: 280px;
        overflow-y: auto;
    }
    .lh-row-list .row {
        padding: 8px 0;
        border-bottom: 1px solid #F0F2F5;
        font-size: 12px;
        display: flex;
        justify-content: space-between;
        gap: 10px;
    }
    .lh-row-list .row:last-child { border-bottom: 0; }
    .lh-row-list .row strong { color: #1A1A1A; font-weight: 700; }
    .lh-row-list .row .amt { font-variant-numeric: tabular-nums; font-weight: 700; }
    .lh-row-list .empty {
        padding: 18px 0;
        text-align: center;
        color: #6A6A70;
        font-size: 12px;
    }

    .lh-form input, .lh-form textarea {
        width: 100%; border: 1px solid #E5E7EB; border-radius: 8px;
        padding: 9px 12px; font-size: 14px; margin-top: 4px;
    }
    .lh-form label { font-size: 12px; font-weight: 700; color: #6A6A70; }
    .lh-form .submit-row {
        display: flex; gap: 8px; margin-top: 12px;
    }
</style>

<div class="lh-shift-detail">

    @if(session('error'))
        <div class="alert alert-soft-danger">{{ session('error') }}</div>
    @endif
    @if(session('success'))
        <div class="alert alert-soft-success">{{ session('success') }}</div>
    @endif

    <div style="display:flex; align-items:center; gap:12px; margin-bottom:14px; flex-wrap:wrap;">
        <a href="{{ route('admin.shifts.index') }}" class="btn btn-light btn-sm">← {{ translate('All shifts') }}</a>
        <h2 style="margin:0; font-weight:800; color:#1A1A1A;">
            {{ translate('Shift') }} #{{ $shift->id }}
        </h2>
        <span class="lh-status {{ $shift->status }}">
            {{ $shift->status === 'open' ? '● ' . translate('OPEN') : '✓ ' . translate('CLOSED') }}
        </span>
    </div>

    <div class="lh-detail-grid">

        {{-- LEFT: meta + reconciliation --}}
        <div>
            <div class="lh-card">
                <h3>{{ translate('Session') }}</h3>
                <div class="lh-meta-grid">
                    <div>{{ translate('Branch') }}: <strong>{{ $shift->branch?->name ?? '—' }}</strong></div>
                    <div>{{ translate('Cashier') }}: <strong>{{ trim(($shift->openedBy->f_name ?? '') . ' ' . ($shift->openedBy->l_name ?? '')) ?: '—' }}</strong></div>
                    <div>{{ translate('Opened') }}: <strong>{{ optional($shift->opened_at)->format('d M Y · H:i') }}</strong></div>
                    <div>{{ translate('Closed') }}: <strong>{{ $shift->closed_at ? $shift->closed_at->format('d M Y · H:i') : '—' }}</strong></div>
                    @if($shift->closedBy)
                    <div>{{ translate('Closed by') }}: <strong>{{ trim(($shift->closedBy->f_name ?? '') . ' ' . ($shift->closedBy->l_name ?? '')) }}</strong></div>
                    @endif
                </div>
            </div>

            <div class="lh-card">
                <h3>{{ translate('Cash reconciliation') }}</h3>
                <div class="lh-money-row">
                    <span>{{ translate('Opening cash') }}</span>
                    <span class="amt">{{ \App\CentralLogics\Helpers::set_symbol($shift->opening_cash) }}</span>
                </div>
                <div class="lh-money-row">
                    <span>{{ translate('POS cash sales') }} ({{ $orderCount }} {{ translate('orders') }})</span>
                    <span class="amt">+ {{ \App\CentralLogics\Helpers::set_symbol($cashSales) }}</span>
                </div>
                <div class="lh-money-row">
                    <span>{{ translate('Cash handovers received') }} ({{ $handovers->count() }})</span>
                    <span class="amt">+ {{ \App\CentralLogics\Helpers::set_symbol($handovers->sum('total_cash')) }}</span>
                </div>
                <div class="lh-money-row">
                    <span>{{ translate('Manual payouts') }} ({{ $payouts->count() }})</span>
                    <span class="amt">− {{ \App\CentralLogics\Helpers::set_symbol($payouts->sum('amount')) }}</span>
                </div>
                <div class="lh-money-row expected">
                    <span>{{ translate('Expected in drawer') }}</span>
                    <span class="amt">{{ \App\CentralLogics\Helpers::set_symbol($expectedCash) }}</span>
                </div>

                @if($shift->status === 'closed')
                    <div style="margin-top:14px; padding:12px; background:#F0F2F5; border-radius:8px;">
                        <div class="lh-money-row" style="border:0; padding:2px 0;">
                            <span>{{ translate('Counted (actual)') }}</span>
                            <span class="amt">{{ \App\CentralLogics\Helpers::set_symbol($shift->actual_cash) }}</span>
                        </div>
                        <div class="lh-money-row {{ $shift->variance == 0 ? '' : ($shift->variance > 0 ? 'surplus' : 'shortage') }}" style="border:0; padding:2px 0; font-weight:800;">
                            <span>{{ translate('Variance') }}</span>
                            <span class="amt">
                                @if($shift->variance == 0) {{ translate('On the dot') }}
                                @elseif($shift->variance > 0) + {{ \App\CentralLogics\Helpers::set_symbol($shift->variance) }} {{ translate('surplus') }}
                                @else − {{ \App\CentralLogics\Helpers::set_symbol(abs($shift->variance)) }} {{ translate('shortage') }}
                                @endif
                            </span>
                        </div>
                        @if($shift->notes)
                        <div style="margin-top:8px; padding-top:8px; border-top:1px dashed #d8d8d8; color:#6A6A70; font-size:12px;">
                            <strong>{{ translate('Notes') }}:</strong> {{ $shift->notes }}
                        </div>
                        @endif
                    </div>
                @endif
            </div>

            @if($shift->status === 'open')
            <div class="lh-card">
                <h3>{{ translate('Close shift') }}</h3>
                <p style="font-size:12px; color:#6A6A70; margin:0 0 10px;">
                    {{ translate('Count the cash in the drawer right now and enter it below. The system records the variance vs the expected total above.') }}
                </p>
                <form method="POST" action="{{ route('admin.shifts.close', ['id' => $shift->id]) }}" class="lh-form">
                    @csrf
                    <label>{{ translate('Counted cash (Tk)') }}</label>
                    <input type="number" name="actual_cash" step="0.01" min="0" required value="{{ number_format($expectedCash, 2, '.', '') }}" />
                    <label style="display:block; margin-top:10px;">{{ translate('Notes (optional)') }}</label>
                    <textarea name="notes" rows="2" placeholder="{{ translate('e.g. 200 short — refunded to customer at 22:00') }}"></textarea>
                    <div class="submit-row">
                        <button type="submit" class="btn btn-warning" onclick="return confirm('{{ translate('Close this shift? Cannot be reopened.') }}')">
                            {{ translate('Close shift') }}
                        </button>
                    </div>
                </form>
            </div>
            @endif
        </div>

        {{-- RIGHT: handovers + payouts --}}
        <div>
            <div class="lh-card">
                <h3>{{ translate('Handovers received') }}</h3>
                <div class="lh-row-list">
                    @forelse($handovers as $h)
                        <div class="row">
                            <div>
                                <strong>{{ trim(($h->waiter->f_name ?? '') . ' ' . ($h->waiter->l_name ?? '')) ?: translate('Waiter') }}</strong>
                                <div style="font-size:11px; color:#6A6A70;">{{ optional($h->received_at)->format('H:i') }}</div>
                            </div>
                            <div class="amt">{{ \App\CentralLogics\Helpers::set_symbol($h->total_cash) }}</div>
                        </div>
                    @empty
                        <div class="empty">{{ translate('No handovers received yet.') }}</div>
                    @endforelse
                </div>
            </div>

            <div class="lh-card">
                <h3>{{ translate('Cash payouts') }}</h3>
                <div class="lh-row-list" style="max-height:200px;">
                    @forelse($payouts as $p)
                        <div class="row">
                            <div>
                                <strong>{{ $p->reason }}</strong>
                                <div style="font-size:11px; color:#6A6A70;">{{ optional($p->created_at)->format('H:i') }}</div>
                            </div>
                            <div class="amt" style="color:#E84D4F;">− {{ \App\CentralLogics\Helpers::set_symbol($p->amount) }}</div>
                        </div>
                    @empty
                        <div class="empty">{{ translate('No payouts yet.') }}</div>
                    @endforelse
                </div>

                @if($shift->status === 'open')
                <form method="POST" action="{{ route('admin.shifts.payout', ['id' => $shift->id]) }}" class="lh-form" style="margin-top:12px;">
                    @csrf
                    <label>{{ translate('Amount (Tk)') }}</label>
                    <input type="number" name="amount" step="0.01" min="0.01" required placeholder="0.00" />
                    <label style="display:block; margin-top:8px;">{{ translate('Reason') }}</label>
                    <input type="text" name="reason" required placeholder="{{ translate('e.g. Refund · Petty cash for napkins') }}" maxlength="255" />
                    <div class="submit-row">
                        <button type="submit" class="btn btn-light" style="flex:1;">+ {{ translate('Record payout') }}</button>
                    </div>
                </form>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection
