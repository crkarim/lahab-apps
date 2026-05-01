@extends('layouts.admin.app')

@section('title', translate('Cash Accounts'))

@section('content')
@php
    $sym = fn ($v) => \App\CentralLogics\Helpers::set_symbol($v);
    $typeLabels = [
        'cash'   => '💵 Cash',
        'bank'   => '🏦 Bank',
        'mfs'    => '📱 Mobile money',
        'cheque' => '🧾 Cheque',
    ];
@endphp

<style>
    .lh-acc-page { max-width: 1200px; margin: 0 auto; }
    .lh-acc-hero {
        background: linear-gradient(135deg, #fff 0%, #eef9f0 100%);
        border: 1px solid #cfe6d3; border-radius: 16px;
        padding: 22px 26px; margin-bottom: 18px;
        display: flex; gap: 18px; align-items: center; flex-wrap: wrap;
    }
    .lh-acc-hero .icon {
        width: 56px; height: 56px; border-radius: 50%;
        background: rgba(30, 142, 62, 0.14); color: #1E8E3E;
        display: flex; align-items: center; justify-content: center;
        font-size: 26px; flex-shrink: 0;
    }
    .lh-acc-hero h1 { margin: 0; font-size: 20px; font-weight: 800; color: #1A1A1A; }
    .lh-acc-hero p  { margin: 2px 0 0; color: #6A6A70; font-size: 13px; max-width: 700px; }
    .lh-acc-hero .actions { margin-left: auto; }

    .lh-totals { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 18px; }
    @media (max-width: 800px) { .lh-totals { grid-template-columns: repeat(2, 1fr); } }
    .lh-tile {
        background: #fff; border: 1px solid #E5E7EB; border-radius: 12px;
        padding: 14px 16px; position: relative; overflow: hidden;
    }
    .lh-tile::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: var(--c, #6A6A70); }
    .lh-tile.cash::before   { background: #1E8E3E; }
    .lh-tile.bank::before   { background: #4794FF; }
    .lh-tile.mfs::before    { background: #E67E22; }
    .lh-tile.cheque::before { background: #9B59B6; }
    .lh-tile .label { font-size: 10px; font-weight: 800; color: #6A6A70; text-transform: uppercase; letter-spacing: 1.1px; }
    .lh-tile .value { font-size: 22px; font-weight: 800; color: #1A1A1A; font-variant-numeric: tabular-nums; margin-top: 2px; }
    .lh-tile .sub   { font-size: 11px; color: #6A6A70; margin-top: 2px; }

    .lh-card { background: #fff; border: 1px solid #E5E7EB; border-radius: 12px; padding: 4px 0; margin-bottom: 14px; overflow-x: auto; }
    .lh-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .lh-table th { font-size: 11px; font-weight: 700; color: #6A6A70; text-transform: uppercase; letter-spacing: 1px; padding: 10px 14px; border-bottom: 1px solid #F0F2F5; text-align: left; white-space: nowrap; }
    .lh-table td { padding: 10px 14px; border-bottom: 1px solid #F0F2F5; vertical-align: middle; }
    .lh-table tr:last-child td { border-bottom: 0; }
    .lh-table .num { font-variant-numeric: tabular-nums; font-weight: 700; color: #1A1A1A; text-align: right; }
    .lh-table .row-actions { display: flex; gap: 6px; justify-content: flex-end; flex-wrap: wrap; }
    .lh-table .row-actions .btn { padding: 3px 10px; font-size: 11px; font-weight: 700; }
    .lh-table .acc-pill { display: inline-block; padding: 2px 8px; font-size: 11px; font-weight: 800; color: #fff; border-radius: 999px; }
    .lh-table .code-mono { font-family: monospace; font-size: 11px; color: #6A6A70; background: #F0F2F5; padding: 1px 6px; border-radius: 3px; }
    .lh-table .status-pill { display: inline-block; padding: 2px 8px; font-size: 10px; font-weight: 800; letter-spacing: 1px; border-radius: 999px; }
    .lh-table .status-pill.on { background: #ECFFEF; color: #1E8E3E; }
    .lh-table .status-pill.off { background: #F0F2F5; color: #6A6A70; }
    .lh-empty { padding: 22px; text-align: center; color: #6A6A70; font-size: 13px; }

    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1050; }
    .modal-overlay.open { display: flex; }
    .modal-card { background: #fff; border-radius: 14px; max-width: 540px; width: 92%; padding: 22px 24px; max-height: 90vh; overflow-y: auto; }
    .modal-card h2 { font-size: 18px; font-weight: 800; margin: 0 0 4px; color: #1A1A1A; }
    .modal-card p { color: #6A6A70; font-size: 13px; margin: 0 0 14px; }
    .modal-card label { font-size: 12px; font-weight: 700; color: #6A6A70; }
    .modal-card input, .modal-card select, .modal-card textarea {
        width: 100%; border: 1px solid #E5E7EB; border-radius: 8px;
        padding: 9px 12px; font-size: 14px; margin-top: 4px;
    }
    .modal-card .actions { display: flex; gap: 8px; margin-top: 16px; }
</style>

<div class="lh-acc-page">

    @if(session('error'))<div class="alert alert-soft-danger">{{ session('error') }}</div>@endif
    @if(session('success'))<div class="alert alert-soft-success">{{ session('success') }}</div>@endif

    <div class="lh-acc-hero">
        <div class="icon">💼</div>
        <div>
            <h1>{{ translate('Cash accounts') }}</h1>
            <p>{{ translate('Every wallet the company holds money in — bKash accounts, banks, branch tills, the safe. Each transaction (sale, payment, transfer) updates the matching account\'s balance.') }}</p>
        </div>
        <div class="actions">
            <button type="button" class="btn btn-primary" onclick="document.getElementById('lh-new-acc').classList.add('open')">
                + {{ translate('New account') }}
            </button>
        </div>
    </div>

    <div class="lh-totals">
        @foreach(['cash', 'bank', 'mfs', 'cheque'] as $t)
            @php $info = $totalsByType[$t] ?? ['count' => 0, 'balance' => 0]; @endphp
            <div class="lh-tile {{ $t }}">
                <div class="label">{{ $typeLabels[$t] ?? ucfirst($t) }}</div>
                <div class="value">{{ $sym($info['balance']) }}</div>
                <div class="sub">{{ $info['count'] }} {{ translate('account(s)') }}</div>
            </div>
        @endforeach
    </div>

    <div class="lh-card">
        <table class="lh-table">
            <thead>
                <tr>
                    <th>{{ translate('Account') }}</th>
                    <th>{{ translate('Type') }}</th>
                    <th>{{ translate('Branch') }}</th>
                    <th class="num">{{ translate('Opening') }}</th>
                    <th class="num">{{ translate('Current') }}</th>
                    <th class="num">{{ translate('Txns') }}</th>
                    <th>{{ translate('Status') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($accounts as $a)
                <tr>
                    <td>
                        <strong>{{ $a->name }}</strong>
                        @if($a->account_number)
                            <small style="display:block; color:#6A6A70; font-family:monospace;">{{ $a->account_number }}</small>
                        @endif
                        @if($a->provider)
                            <small style="display:block; color:#6A6A70; text-transform:uppercase;">{{ $a->provider }}</small>
                        @endif
                    </td>
                    <td><span class="acc-pill" style="background: {{ $a->color }};">{{ strtoupper($a->type) }}</span> <span class="code-mono">{{ $a->code }}</span></td>
                    <td style="font-size:12px;">{{ $a->branch?->name ?: '— ' . translate('HQ-wide') . ' —' }}</td>
                    <td class="num">{{ $sym($a->opening_balance) }}</td>
                    <td class="num" style="font-size:14px;">{{ $sym($a->current_balance) }}</td>
                    <td class="num">{{ $a->transactions_count }}</td>
                    <td>
                        @if($a->is_active)
                            <span class="status-pill on">ACTIVE</span>
                        @else
                            <span class="status-pill off">INACTIVE</span>
                        @endif
                    </td>
                    <td>
                        <div class="row-actions">
                            @php
                                $editPayload = json_encode([
                                    'id'              => $a->id,
                                    'name'            => $a->name,
                                    'provider'        => $a->provider,
                                    'account_number'  => $a->account_number,
                                    'opening_balance' => (float) $a->opening_balance,
                                    'opening_date'    => optional($a->opening_date)->format('Y-m-d'),
                                    'color'           => $a->color,
                                    'sort_order'      => $a->sort_order,
                                    'notes'           => $a->notes,
                                    'is_active'       => (bool) $a->is_active,
                                ], JSON_HEX_APOS | JSON_HEX_QUOT);
                            @endphp
                            <button type="button" class="btn btn-light" onclick='lhEditAcc({!! $editPayload !!})'>{{ translate('Edit') }}</button>
                            <form method="POST" action="{{ route('admin.cash-accounts.recompute', ['id' => $a->id]) }}"
                                  style="display:inline;"
                                  title="{{ translate('Recompute balance from ledger — drift safety') }}">
                                @csrf
                                <button type="submit" class="btn btn-light">↻</button>
                            </form>
                            <form method="POST" action="{{ route('admin.cash-accounts.destroy', ['id' => $a->id]) }}"
                                  onsubmit="return confirm('{{ translate('Delete this account? Deactivates if any transactions have posted.') }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-light" style="color:#C82626;">✕</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="lh-empty">{{ translate('No cash accounts yet — click "+ New account" to create your first one.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

</div>

{{-- New account modal --}}
<div class="modal-overlay" id="lh-new-acc" onclick="if(event.target===this) this.classList.remove('open')">
    <form method="POST" action="{{ route('admin.cash-accounts.store') }}" class="modal-card">
        @csrf
        <h2>{{ translate('New cash account') }}</h2>
        <p>{{ translate('Pick the wallet type, give it a clear name (e.g. "bKash Owner Personal" or "DBBL Current 8721"), set the opening balance and date.') }}</p>

        <label>{{ translate('Name') }}</label>
        <input type="text" name="name" required maxlength="80" placeholder="bKash Owner / DBBL Current 8721 / Branch-1 Till">

        <div class="form-row" style="margin-top:10px;">
            <div class="col-md-6 form-group">
                <label>{{ translate('Code') }}</label>
                <input type="text" name="code" required maxlength="32" placeholder="bkash_owner">
            </div>
            <div class="col-md-6 form-group">
                <label>{{ translate('Type') }}</label>
                <select name="type" required>
                    <option value="cash">{{ translate('Cash') }}</option>
                    <option value="bank">{{ translate('Bank') }}</option>
                    <option value="mfs">{{ translate('Mobile money (bKash / Nagad / Rocket / Upay)') }}</option>
                    <option value="cheque">{{ translate('Cheque clearing') }}</option>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="col-md-6 form-group">
                <label>{{ translate('Provider') }} <small style="color:#6A6A70; font-weight:500;">({{ translate('bank/MFS name') }})</small></label>
                <input type="text" name="provider" maxlength="60" placeholder="DBBL / bKash / BRAC ...">
            </div>
            <div class="col-md-6 form-group">
                <label>{{ translate('Account number') }}</label>
                <input type="text" name="account_number" maxlength="40">
            </div>
        </div>

        @if($isMaster)
        <label style="display:block; margin-top:10px;">{{ translate('Branch') }} <small style="color:#6A6A70; font-weight:500;">({{ translate('blank = HQ-wide') }})</small></label>
        <select name="branch_id">
            <option value="">— {{ translate('HQ-wide') }} —</option>
            @foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
        </select>
        @endif

        <div class="form-row" style="margin-top:10px;">
            <div class="col-md-6 form-group">
                <label>{{ translate('Opening balance (Tk)') }}</label>
                <input type="number" name="opening_balance" step="0.01" value="0">
            </div>
            <div class="col-md-6 form-group">
                <label>{{ translate('Opening date') }}</label>
                <input type="date" name="opening_date" value="{{ now()->toDateString() }}">
            </div>
        </div>

        <div class="form-row">
            <div class="col-md-6 form-group">
                <label>{{ translate('Color') }}</label>
                <input type="color" name="color" value="#6A6A70" style="height:38px;">
            </div>
            <div class="col-md-6 form-group">
                <label>{{ translate('Sort order') }}</label>
                <input type="number" name="sort_order" value="100">
            </div>
        </div>

        <label style="display:block; margin-top:10px;">{{ translate('Notes') }}</label>
        <textarea name="notes" rows="2" maxlength="1000"></textarea>

        <div class="actions">
            <button type="button" class="btn btn-light" style="flex:1;" onclick="document.getElementById('lh-new-acc').classList.remove('open')">{{ translate('Cancel') }}</button>
            <button type="submit" class="btn btn-primary" style="flex:1;">{{ translate('Create') }}</button>
        </div>
    </form>
</div>

{{-- Edit modal --}}
<div class="modal-overlay" id="lh-edit-acc" onclick="if(event.target===this) this.classList.remove('open')">
    <form method="POST" id="lh-edit-acc-form" class="modal-card">
        @csrf
        <h2>{{ translate('Edit account') }}</h2>
        <p style="font-size:11px; color:#C82626;">{{ translate('Code + type cannot change after creation. Adjusting opening balance recomputes current balance from the ledger.') }}</p>

        <label>{{ translate('Name') }}</label>
        <input type="text" name="name" id="ea_name" required maxlength="80">

        <div class="form-row" style="margin-top:10px;">
            <div class="col-md-6 form-group">
                <label>{{ translate('Provider') }}</label>
                <input type="text" name="provider" id="ea_provider" maxlength="60">
            </div>
            <div class="col-md-6 form-group">
                <label>{{ translate('Account number') }}</label>
                <input type="text" name="account_number" id="ea_acct" maxlength="40">
            </div>
        </div>

        <div class="form-row">
            <div class="col-md-6 form-group">
                <label>{{ translate('Opening balance') }}</label>
                <input type="number" name="opening_balance" id="ea_opening" step="0.01">
            </div>
            <div class="col-md-6 form-group">
                <label>{{ translate('Opening date') }}</label>
                <input type="date" name="opening_date" id="ea_opening_date">
            </div>
        </div>

        <div class="form-row">
            <div class="col-md-6 form-group">
                <label>{{ translate('Color') }}</label>
                <input type="color" name="color" id="ea_color" style="height:38px;">
            </div>
            <div class="col-md-6 form-group">
                <label>{{ translate('Sort') }}</label>
                <input type="number" name="sort_order" id="ea_sort">
            </div>
        </div>

        <label style="display:block; margin-top:10px;">
            <input type="checkbox" name="is_active" id="ea_active" value="1" style="width:auto; margin-right:6px;">
            {{ translate('Active') }}
        </label>

        <label style="display:block; margin-top:10px;">{{ translate('Notes') }}</label>
        <textarea name="notes" id="ea_notes" rows="2" maxlength="1000"></textarea>

        <div class="actions">
            <button type="button" class="btn btn-light" style="flex:1;" onclick="document.getElementById('lh-edit-acc').classList.remove('open')">{{ translate('Cancel') }}</button>
            <button type="submit" class="btn btn-primary" style="flex:1;">{{ translate('Save') }}</button>
        </div>
    </form>
</div>

<script>
function lhEditAcc(a) {
    var form = document.getElementById('lh-edit-acc-form');
    form.action = '{{ url('admin/cash-accounts') }}/' + a.id;
    document.getElementById('ea_name').value          = a.name || '';
    document.getElementById('ea_provider').value      = a.provider || '';
    document.getElementById('ea_acct').value          = a.account_number || '';
    document.getElementById('ea_opening').value       = a.opening_balance != null ? a.opening_balance : 0;
    document.getElementById('ea_opening_date').value  = a.opening_date || '';
    document.getElementById('ea_color').value         = a.color || '#6A6A70';
    document.getElementById('ea_sort').value          = a.sort_order || 0;
    document.getElementById('ea_active').checked      = !!a.is_active;
    document.getElementById('ea_notes').value         = a.notes || '';
    document.getElementById('lh-edit-acc').classList.add('open');
}
</script>
@endsection
