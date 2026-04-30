@extends('layouts.admin.app')

@section('title', translate('Cash Collect'))

@section('content')
<div class="content container-fluid">

    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <h2 class="h1 mb-0 d-flex align-items-center gap-2">
            <i class="tio-money-vs" style="font-size:24px; color:#1E8E3E"></i>
            <span class="page-header-title">{{ translate('Cash Collect') }}</span>
        </h2>
        <span class="badge badge-soft-primary ml-2">{{ count($pending) }} {{ translate('pending') }}</span>
        <div class="ml-auto small text-muted">
            {{ translate('Cash floating on the floor + waiter submissions awaiting your acknowledgement.') }}
        </div>
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="alert alert-success py-2">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger py-2">{{ session('error') }}</div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning py-2">{{ session('warning') }}</div>
    @endif

    {{-- Live drawers — what each waiter is holding RIGHT NOW that
         hasn't been submitted yet. Helps the cashier walk the floor
         and ask "hey, you've got Tk 1,200 in cash, want to hand it
         over?" without waiting for the waiter to come to them. --}}
    <div class="card mb-3">
        <div class="card-header py-2 d-flex align-items-center gap-2">
            <i class="tio-wallet" style="color:#1E8E3E"></i>
            <h5 class="card-title mb-0">{{ translate('Live drawers') }}</h5>
            <small class="text-muted ml-2">{{ translate('cash held by waiters, not yet submitted') }}</small>
            @php $liveTotal = $liveDrawers->sum('cash_total'); @endphp
            <span class="badge badge-soft-success ml-auto">
                {{ \App\CentralLogics\Helpers::set_symbol($liveTotal) }} {{ translate('on the floor') }}
            </span>
        </div>
        <div class="card-body p-0">
            @if(count($liveDrawers) === 0)
                <div class="p-3 text-center text-muted small">
                    {{ translate('No unsubmitted cash on the floor.') }}
                </div>
            @else
                <table class="table table-borderless mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ translate('Waiter') }}</th>
                            <th class="text-right">{{ translate('Orders') }}</th>
                            <th class="text-right">{{ translate('Cash held') }}</th>
                            <th class="text-right">{{ translate('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($liveDrawers as $d)
                            @php $waiterName = trim(($d->f_name ?? '') . ' ' . ($d->l_name ?? '')) ?: 'Unknown'; @endphp
                            <tr>
                                <td>
                                    <strong>{{ $waiterName }}</strong>
                                </td>
                                <td class="text-right" style="font-variant-numeric: tabular-nums;">
                                    {{ (int) $d->order_count }}
                                </td>
                                <td class="text-right" style="font-variant-numeric: tabular-nums; font-weight:700;">
                                    {{ \App\CentralLogics\Helpers::set_symbol($d->cash_total) }}
                                </td>
                                <td class="text-right">
                                    {{-- Cashier-initiated collection: skip the
                                         "wait for waiter to submit" handshake
                                         and pull the money straight into the
                                         drawer in one click. --}}
                                    <form action="{{ route('admin.cash-handovers.collect', $d->waiter_id) }}"
                                          method="POST" class="d-inline"
                                          onsubmit="return confirm('Collect {{ \App\CentralLogics\Helpers::set_symbol($d->cash_total) }} from {{ $waiterName }} now?');">
                                        @csrf
                                        <button type="submit" class="btn btn-success btn-sm">
                                            <i class="tio-add-to-checked-list"></i>
                                            {{ translate('Collect') }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    {{-- Pending list --}}
    <div class="card mb-3">
        <div class="card-header py-2">
            <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                <i class="tio-time" style="color:#E84D4F"></i>
                {{ translate('Pending') }}
            </h5>
        </div>
        <div class="card-body p-0">
            @if(count($pending) === 0)
                <div class="p-4 text-center text-muted">
                    <i class="tio-checkmark-circle-outlined" style="font-size:32px; color:#1E8E3E"></i>
                    <div class="mt-2">{{ translate('No pending handovers — drawer is reconciled.') }}</div>
                </div>
            @else
                <table class="table table-borderless mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ translate('Waiter') }}</th>
                            <th class="text-right">{{ translate('Cash to receive') }}</th>
                            <th class="text-right" title="{{ translate('Slice of cash that\'s tip income — already included in the cash figure') }}">
                                {{ translate('of which tips') }}
                            </th>
                            <th>{{ translate('Submitted') }}</th>
                            <th class="text-right">{{ translate('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pending as $h)
                            <tr>
                                <td>
                                    <strong>
                                        {{ trim(($h->waiter->f_name ?? '') . ' ' . ($h->waiter->l_name ?? '')) ?: 'Unknown' }}
                                    </strong>
                                </td>
                                <td class="text-right" style="font-variant-numeric: tabular-nums; font-weight:800;">
                                    {{ \App\CentralLogics\Helpers::set_symbol($h->total_cash) }}
                                </td>
                                <td class="text-right" style="font-variant-numeric: tabular-nums; color:#E67E22;">
                                    {{ \App\CentralLogics\Helpers::set_symbol($h->total_tips) }}
                                </td>
                                <td>
                                    <small class="text-muted" title="{{ $h->submitted_at?->format('d M Y H:i') }}">
                                        {{ $h->submitted_at?->diffForHumans() }}
                                    </small>
                                </td>
                                <td class="text-right">
                                    <form action="{{ route('admin.cash-handovers.receive', $h->id) }}"
                                          method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-success btn-sm">
                                            <i class="tio-add-to-checked-list"></i>
                                            {{ translate('Receive') }}
                                            {{ \App\CentralLogics\Helpers::set_symbol($h->total_cash) }}
                                        </button>
                                    </form>
                                    <button type="button"
                                            class="btn btn-outline-danger btn-sm ml-1"
                                            onclick="lhDispute({{ $h->id }})">
                                        <i class="tio-error-outlined"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    {{-- Recent history --}}
    <div class="card">
        <div class="card-header py-2">
            <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                <i class="tio-history" style="color:#999"></i>
                {{ translate('Recent') }}
            </h5>
        </div>
        <div class="card-body p-0">
            @if(count($recent) === 0)
                <div class="p-3 text-center text-muted">{{ translate('No history yet.') }}</div>
            @else
                <table class="table table-borderless mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ translate('Waiter') }}</th>
                            <th>{{ translate('Cashier') }}</th>
                            <th class="text-right">{{ translate('Total') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th>{{ translate('Received') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recent as $h)
                            <tr>
                                <td>{{ trim(($h->waiter->f_name ?? '') . ' ' . ($h->waiter->l_name ?? '')) ?: '—' }}</td>
                                <td>{{ trim(($h->cashier->f_name ?? '') . ' ' . ($h->cashier->l_name ?? '')) ?: '—' }}</td>
                                <td class="text-right" style="font-variant-numeric: tabular-nums;">
                                    {{ \App\CentralLogics\Helpers::set_symbol($h->total_cash) }}
                                </td>
                                <td>
                                    @if($h->status === 'received')
                                        <span class="badge badge-soft-success">{{ translate('Received') }}</span>
                                    @elseif($h->status === 'disputed')
                                        <span class="badge badge-soft-danger">{{ translate('Disputed') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <small class="text-muted">{{ $h->received_at?->format('d M H:i') }}</small>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

</div>

{{-- Dispute modal --}}
<div class="modal fade" id="lhDisputeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form id="lhDisputeForm" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('Mark as disputed') }}</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2">
                        {{ translate('Use this when the cash count doesn\'t match what the waiter submitted. The handover is moved out of pending and flagged for HQ review.') }}
                    </p>
                    <textarea name="notes" class="form-control" rows="3"
                              placeholder="{{ translate('What didn\'t match? (optional)') }}"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-link" data-dismiss="modal">{{ translate('Cancel') }}</button>
                    <button type="submit" class="btn btn-danger">{{ translate('Mark disputed') }}</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    function lhDispute(id) {
        const form = document.getElementById('lhDisputeForm');
        form.action = '{{ url('admin/cash-handovers') }}/' + id + '/dispute';
        $('#lhDisputeModal').modal('show');
    }
</script>
@endsection
