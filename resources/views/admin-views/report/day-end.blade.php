@extends('layouts.admin.app')

@section('title', translate('Day-End Report'))

@section('content')
<div class="content container-fluid">

    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <h2 class="h1 mb-0 d-flex align-items-center gap-2">
            <i class="tio-calculator" style="font-size:24px; color:#E67E22"></i>
            <span class="page-header-title">{{ translate('Day-End Report') }}</span>
        </h2>
        <span class="badge badge-soft-secondary ml-2">{{ $dateHuman }}</span>
        <div class="ml-auto d-flex align-items-center gap-2 lh-no-print">
            <button onclick="lhPrintDayEnd()" class="btn btn-outline-primary btn-sm">
                <i class="tio-print mr-1"></i> {{ translate('Print slip') }}
            </button>
        </div>
    </div>

    {{-- Filter strip --}}
    <form action="{{ route('admin.report.day-end') }}" method="GET" class="card mb-3 lh-no-print">
        <div class="card-body p-3">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label small text-muted mb-1">{{ translate('Date') }}</label>
                    <input type="date" name="date" value="{{ $date }}" class="form-control">
                </div>
                @if(!$forcedBranch)
                <div class="col-12 col-md-4">
                    <label class="form-label small text-muted mb-1">{{ translate('Branch') }}</label>
                    <select name="branch_id" class="form-control">
                        <option value="all" @selected($branchId === 'all')>{{ translate('All branches') }}</option>
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}" @selected((string) $branchId === (string) $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="col-12 col-md-4">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="tio-filter mr-1"></i> {{ translate('Apply') }}
                    </button>
                </div>
            </div>
        </div>
    </form>

    {{-- Summary tiles --}}
    <div class="row g-2 mb-3">
        @php
            $tiles = [
                ['label' => translate('Orders'),    'val' => $orderCount, 'sym' => false, 'color' => '#4794FF'],
                ['label' => translate('Gross sales'),'val' => $grossSales, 'sym' => true,  'color' => '#1E8E3E'],
                ['label' => translate('Tips'),      'val' => $totalTips,  'sym' => true,  'color' => '#E67E22'],
                ['label' => translate('Discounts'), 'val' => $totalDiscount,'sym' => true, 'color' => '#B45A0A'],
                ['label' => translate('Net (with tips)'),'val' => $netSales, 'sym' => true, 'color' => '#1a1a1a'],
            ];
        @endphp
        @foreach($tiles as $t)
            <div class="col-6 col-md-4 col-lg">
                <div class="card h-100">
                    <div class="card-body p-3">
                        <div class="text-muted small mb-1">{{ $t['label'] }}</div>
                        <div style="font-size: 22px; font-weight: 800; color: {{ $t['color'] }};">
                            {{ $t['sym'] ? \App\CentralLogics\Helpers::set_symbol($t['val']) : (int) $t['val'] }}
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3">

        {{-- Payment breakdown --}}
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header py-2">
                    <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                        <i class="tio-money" style="color:#1E8E3E"></i>
                        {{ translate('Payment breakdown') }}
                    </h5>
                </div>
                <div class="card-body p-0">
                    @if(empty($payments))
                        <div class="p-4 text-center text-muted">{{ translate('No payments captured for this date.') }}</div>
                    @else
                        <table class="table table-borderless mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>{{ translate('Method') }}</th>
                                    <th class="text-right">{{ translate('Total') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($payments as $method => $sum)
                                    <tr>
                                        <td>
                                            <strong>{{ ucwords(str_replace('_', ' ', $method)) }}</strong>
                                        </td>
                                        <td class="text-right" style="font-variant-numeric: tabular-nums; font-weight:700;">
                                            {{ \App\CentralLogics\Helpers::set_symbol($sum) }}
                                        </td>
                                    </tr>
                                @endforeach
                                <tr style="border-top:2px solid #1a1a1a;">
                                    <td><strong>{{ translate('Drawer total') }}</strong></td>
                                    <td class="text-right" style="font-variant-numeric: tabular-nums; font-weight:900; font-size:16px;">
                                        {{ \App\CentralLogics\Helpers::set_symbol(array_sum($payments)) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>

        {{-- Per-waiter sheet --}}
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header py-2">
                    <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                        <i class="tio-user-outlined" style="color:#E67E22"></i>
                        {{ translate('Waiter sheet') }} <small class="text-muted">— {{ translate('for tip distribution') }}</small>
                    </h5>
                </div>
                <div class="card-body p-0">
                    @if(empty($waiters))
                        <div class="p-4 text-center text-muted">{{ translate('No orders attributed to a waiter.') }}</div>
                    @else
                        <table class="table table-borderless mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>{{ translate('Waiter') }}</th>
                                    <th class="text-right">{{ translate('Orders') }}</th>
                                    <th class="text-right">{{ translate('Revenue') }}</th>
                                    <th class="text-right">{{ translate('Tips') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($waiters as $w)
                                    <tr>
                                        <td><strong>{{ $w['name'] }}</strong></td>
                                        <td class="text-right" style="font-variant-numeric: tabular-nums;">{{ (int) $w['orders'] }}</td>
                                        <td class="text-right" style="font-variant-numeric: tabular-nums;">{{ \App\CentralLogics\Helpers::set_symbol($w['revenue']) }}</td>
                                        <td class="text-right" style="font-variant-numeric: tabular-nums; color:#E67E22; font-weight:700;">
                                            {{ \App\CentralLogics\Helpers::set_symbol($w['tips']) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>

    </div>

    {{-- Handover reconciliation strip --}}
    <div class="card mt-3">
        <div class="card-header py-2 d-flex align-items-center gap-2">
            <i class="tio-money-vs" style="color:#1E8E3E"></i>
            <h5 class="card-title mb-0">{{ translate('Cash handovers') }}</h5>
            @php $diff = $hSubmitted - $hReceived; @endphp
            @if(count($hPendingRows) > 0)
                <span class="badge badge-soft-warning ml-2">
                    {{ count($hPendingRows) }} {{ translate('pending') }} · {{ \App\CentralLogics\Helpers::set_symbol($diff) }} {{ translate('in flight') }}
                </span>
            @elseif(count($hDisputedRows) > 0)
                <span class="badge badge-soft-danger ml-2">
                    {{ count($hDisputedRows) }} {{ translate('disputed') }}
                </span>
            @else
                <span class="badge badge-soft-success ml-2">{{ translate('Reconciled') }}</span>
            @endif
        </div>
        <div class="card-body p-0">
            <div class="row no-gutters">
                <div class="col-12 col-md-6 p-3 border-right">
                    <div class="text-muted small">{{ translate('Submitted by waiters') }}</div>
                    <div style="font-size:20px; font-weight:800;">{{ \App\CentralLogics\Helpers::set_symbol($hSubmitted) }}</div>
                </div>
                <div class="col-12 col-md-6 p-3">
                    <div class="text-muted small">{{ translate('Received by cashier') }}</div>
                    <div style="font-size:20px; font-weight:800; color:#1E8E3E;">{{ \App\CentralLogics\Helpers::set_symbol($hReceived) }}</div>
                </div>
            </div>

            @if(count($hPendingRows) > 0)
                <div class="border-top">
                    <div class="px-3 py-2 small text-muted bg-light">{{ translate('Pending — physical cash not yet handed over') }}</div>
                    <table class="table table-borderless table-sm mb-0">
                        <tbody>
                            @foreach($hPendingRows as $h)
                                <tr>
                                    <td>
                                        <strong>{{ trim(($h->waiter->f_name ?? '') . ' ' . ($h->waiter->l_name ?? '')) ?: 'Unknown' }}</strong>
                                        <small class="text-muted ml-2">{{ $h->submitted_at?->diffForHumans() }}</small>
                                    </td>
                                    <td class="text-right" style="font-variant-numeric: tabular-nums; font-weight:700; color:#B45A0A;">
                                        {{ \App\CentralLogics\Helpers::set_symbol($h->total) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if(count($hDisputedRows) > 0)
                <div class="border-top">
                    <div class="px-3 py-2 small text-danger bg-light">{{ translate('Disputed — needs HQ review') }}</div>
                    <table class="table table-borderless table-sm mb-0">
                        <tbody>
                            @foreach($hDisputedRows as $h)
                                <tr>
                                    <td>
                                        <strong>{{ trim(($h->waiter->f_name ?? '') . ' ' . ($h->waiter->l_name ?? '')) ?: '—' }}</strong>
                                        <small class="text-muted ml-2">vs {{ trim(($h->cashier->f_name ?? '') . ' ' . ($h->cashier->l_name ?? '')) ?: '—' }}</small>
                                        @if($h->notes)
                                            <div class="small text-muted">{{ $h->notes }}</div>
                                        @endif
                                    </td>
                                    <td class="text-right" style="font-variant-numeric: tabular-nums; font-weight:700; color:#E84D4F;">
                                        {{ \App\CentralLogics\Helpers::set_symbol($h->total) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="text-muted small mt-3">
        {{ translate('Note: this report aggregates all settled sales for the date. When per-shift reconciliation ships, this view will pivot to a single shift\'s drawer.') }}
    </div>

</div>

<script>
    /**
     * Print the day-end summary in a stripped popup window. We
     * `window.print()` against the admin layout used to come out blank
     * because the admin chrome (sidebar + navbar) overlaps the content
     * area in print media. Cloning the report into a self-contained
     * popup with its own layout sidesteps the entire admin CSS.
     */
    function lhPrintDayEnd() {
        const tiles    = document.querySelector('.row.g-2.mb-3').outerHTML;
        const sections = document.querySelector('.row.g-3').outerHTML;
        const title    = @json('Day-End Report — ' . $dateHuman);
        const branch   = @json($branchId === 'all' ? 'All branches' : ($branches->firstWhere('id', (int) $branchId)->name ?? ''));
        const html     = `<!doctype html>
<html><head><meta charset="utf-8"><title>${title}</title>
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
         margin: 0; padding: 24px; color: #1a1a1a; background: #fff; }
  h1 { font-size: 22px; margin: 0 0 4px; font-weight: 800; }
  .sub { color: #666; font-size: 13px; margin: 0 0 18px; }
  .row { display: grid; gap: 12px; }
  .row.tiles { grid-template-columns: repeat(5, 1fr); margin-bottom: 18px; }
  .row.split { grid-template-columns: 1fr 1fr; }
  .card { border: 1px solid #ddd; border-radius: 8px; padding: 14px; }
  .card-header { padding: 10px 14px; border-bottom: 1px solid #eee; font-weight: 700; }
  .card-body { padding: 10px 14px; }
  .card-body.p-0 { padding: 0; }
  table { width: 100%; border-collapse: collapse; }
  th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #eee; }
  th { background: #fafafa; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #666; }
  .text-right { text-align: right; }
  .text-success { color: #1E8E3E; }
  .text-muted { color: #666; }
  .text-orange { color: #E67E22; font-weight: 700; }
  .small { font-size: 12px; }
  .footer { margin-top: 18px; font-size: 11px; color: #888; }
  .badge-soft-secondary { background: #f0f0f0; padding: 4px 10px; border-radius: 999px; font-size: 12px; }
  /* Card body summary tiles */
  .col-6, .col-md-4, .col-lg, .col-12, .col-lg-6 { box-sizing: border-box; }
  .row .row { display: contents; }
  /* Hide control elements that aren't relevant in print */
  .lh-no-print, button, select, input, form, .badge { display: none !important; }
  .badge.badge-soft-secondary { display: inline-block !important; }
  @page { size: A4; margin: 12mm; }
</style></head>
<body>
<h1>${title}</h1>
<p class="sub">${branch ? 'Branch: ' + branch : 'All branches'}</p>
${tiles}
${sections}
<p class="footer">Generated ${new Date().toLocaleString()}</p>
<script>window.onload=()=>{setTimeout(()=>{window.print();},200);};</`+`script>
</body></html>`;
        const w = window.open('', '_blank', 'width=800,height=900');
        if (!w) { alert('Pop-up blocked — allow pop-ups to print.'); return; }
        w.document.open();
        w.document.write(html);
        w.document.close();
    }
</script>
@endsection
