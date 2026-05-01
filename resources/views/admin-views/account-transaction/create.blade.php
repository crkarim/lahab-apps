@extends('layouts.admin.app')

@section('title', translate('New transaction'))

@section('content')
@php
    $titleByType = [
        'in'       => '+ Cash in',
        'out'      => '− Cash out',
        'transfer' => '⇄ Transfer between accounts',
    ];
    $heroColor = [
        'in'       => '#1E8E3E',
        'out'      => '#C82626',
        'transfer' => '#4794FF',
    ];
@endphp

<style>
    .lh-tx-form-page { max-width: 720px; margin: 0 auto; }
    .lh-tx-form-hero {
        background: #fff; border: 1px solid #E5E7EB; border-radius: 16px;
        padding: 18px 22px; margin-bottom: 14px;
        display: flex; gap: 14px; align-items: center;
        border-left: 6px solid {{ $heroColor[$type] }};
    }
    .lh-tx-form-hero h1 { margin: 0; font-size: 18px; font-weight: 800; color: #1A1A1A; }
    .lh-tx-form-hero p  { margin: 2px 0 0; color: #6A6A70; font-size: 12px; }

    .lh-type-tabs {
        display: flex; gap: 4px; padding: 4px;
        background: #fff; border: 1px solid #E5E7EB; border-radius: 12px;
        margin-bottom: 14px;
    }
    .lh-type-tab {
        flex: 1; text-align: center; padding: 9px 14px;
        font-size: 13px; font-weight: 700; color: #6A6A70;
        border-radius: 8px; text-decoration: none;
    }
    .lh-type-tab:hover { background: #F4F6F8; color: #1A1A1A; text-decoration: none; }
    .lh-type-tab.active { background: #1A1A1A; color: #fff; }

    .lh-tx-card {
        background: #fff; border: 1px solid #E5E7EB; border-radius: 12px;
        padding: 22px 24px; margin-bottom: 14px;
    }
    .lh-tx-card label { font-size: 12px; font-weight: 700; color: #6A6A70; }
    .lh-tx-card input, .lh-tx-card select, .lh-tx-card textarea {
        width: 100%; border: 1px solid #E5E7EB; border-radius: 8px;
        padding: 10px 12px; font-size: 14px; margin-top: 4px;
        font-variant-numeric: tabular-nums;
    }
    .lh-tx-card .form-group { margin-bottom: 12px; }
    .lh-tx-card .form-row { display: flex; gap: 12px; }
    .lh-tx-card .form-row > div { flex: 1; }
    .lh-tx-card .actions { display: flex; gap: 8px; margin-top: 18px; }
    .lh-tx-card .small-help { font-size: 11px; color: #6A6A70; margin-top: 4px; }

    .lh-vat-block {
        background: #FFF8E1; border: 1px solid #F4DDA1;
        border-radius: 8px; padding: 10px 14px; margin-top: 8px;
    }
</style>

<div class="lh-tx-form-page">

    @if(session('error'))<div class="alert alert-soft-danger">{{ session('error') }}</div>@endif

    <div class="lh-type-tabs">
        @foreach(['in', 'out', 'transfer'] as $t)
            <a href="{{ route('admin.account-transactions.create', ['type' => $t]) }}"
               class="lh-type-tab {{ $type === $t ? 'active' : '' }}">
                {{ $titleByType[$t] }}
            </a>
        @endforeach
    </div>

    <div class="lh-tx-form-hero">
        <div>
            <h1>{{ $titleByType[$type] }}</h1>
            <p>
                @switch($type)
                    @case('in')
                        {{ translate('Money received into one account — a sale collected, owner deposit, customer refund returned, etc.') }}
                        @break
                    @case('out')
                        {{ translate('Money paid out from one account — supplier bill, utility, fuel, gas refill, repair, withdrawal.') }}
                        @break
                    @case('transfer')
                        {{ translate('Move money between two accounts you own — Bank → bKash, Till → Safe, etc. Creates two paired ledger rows.') }}
                @endswitch
            </p>
        </div>
    </div>

    {{-- IN / OUT form --}}
    @if($type === 'in' || $type === 'out')
    <form method="POST" action="{{ route('admin.account-transactions.store') }}" class="lh-tx-card">
        @csrf
        <input type="hidden" name="direction" value="{{ $type }}">

        <div class="form-group">
            <label>{{ translate('Account') }}</label>
            <select name="account_id" required>
                <option value="" disabled selected>— {{ translate('select') }} —</option>
                @foreach($accounts as $a)
                    <option value="{{ $a->id }}">
                        {{ strtoupper($a->type) }} · {{ $a->name }}
                        @if($a->account_number) · {{ $a->account_number }}@endif
                    </option>
                @endforeach
            </select>
            <div class="small-help">{{ translate('Which wallet this Taka movement hits.') }}</div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>{{ translate('Amount (Tk)') }}</label>
                <input type="number" name="amount" step="0.01" min="0.01" required placeholder="0.00">
                <div class="small-help">{{ translate('Gross principal — VAT and tax stay inside this number; track them in the fields below for reporting.') }}</div>
            </div>
            <div class="form-group">
                <label>{{ translate('Charge / fee (Tk)') }}</label>
                <input type="number" name="charge" step="0.01" min="0" value="0" placeholder="0.00">
                <div class="small-help">{{ translate('bKash cashout fee, bank wire fee, etc. Reduces balance separately from amount.') }}</div>
            </div>
        </div>

        <div class="lh-vat-block">
            <div style="font-size:11px; font-weight:800; color:#6A4A0A; letter-spacing:.5px; text-transform:uppercase; margin-bottom:6px;">{{ translate('VAT & tax') }} <small style="text-transform:none; font-weight:600;">({{ translate('optional, for reporting') }})</small></div>
            <div class="form-row">
                <div class="form-group" style="margin-bottom:0;">
                    <label>{{ $type === 'in' ? translate('VAT collected (output)') : translate('VAT paid (input)') }}</label>
                    <input type="number"
                           name="{{ $type === 'in' ? 'vat_output' : 'vat_input' }}"
                           step="0.01" min="0" value="0">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label>{{ translate('AIT / withholding (Tk)') }}</label>
                    <input type="number" name="tax_amount" step="0.01" min="0" value="0">
                </div>
            </div>
        </div>

        <div class="form-group" style="margin-top:14px;">
            <label>{{ translate('Description') }}</label>
            <textarea name="description" rows="2" required maxlength="1000" placeholder="{{ $type === 'in' ? translate('e.g. Cash sale, customer Mr. Khan paid via bKash, refund returned by supplier') : translate('e.g. Gas refill 2 cylinders, July electricity bill, fuel for generator, vegetable supplier weekly') }}"></textarea>
        </div>

        <div class="form-group">
            <label>{{ translate('Date & time') }}</label>
            <input type="datetime-local" name="transacted_at" value="{{ now()->format('Y-m-d\TH:i') }}">
        </div>

        <div class="actions">
            <a href="{{ route('admin.account-transactions.index') }}" class="btn btn-light" style="flex:1;">{{ translate('Cancel') }}</a>
            <button type="submit" class="btn {{ $type === 'in' ? 'btn-success' : 'btn-primary' }}" style="flex:2;">
                {{ $type === 'in' ? translate('Post cash in') : translate('Post cash out') }}
            </button>
        </div>
    </form>
    @else
    {{-- TRANSFER form --}}
    <form method="POST" action="{{ route('admin.account-transactions.transfer') }}" class="lh-tx-card">
        @csrf

        <div class="form-row">
            <div class="form-group">
                <label>{{ translate('From account') }}</label>
                <select name="from_account_id" required>
                    <option value="" disabled selected>— {{ translate('select') }} —</option>
                    @foreach($accounts as $a)
                        <option value="{{ $a->id }}">{{ strtoupper($a->type) }} · {{ $a->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>{{ translate('To account') }}</label>
                <select name="to_account_id" required>
                    <option value="" disabled selected>— {{ translate('select') }} —</option>
                    @foreach($accounts as $a)
                        <option value="{{ $a->id }}">{{ strtoupper($a->type) }} · {{ $a->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>{{ translate('Amount (Tk)') }}</label>
                <input type="number" name="amount" step="0.01" min="0.01" required placeholder="0.00">
            </div>
            <div class="form-group">
                <label>{{ translate('Charge / fee (Tk)') }}</label>
                <input type="number" name="charge" step="0.01" min="0" value="0" placeholder="0.00">
                <div class="small-help">{{ translate('Cashout / wire fee on the source account.') }}</div>
            </div>
        </div>

        <div class="form-group">
            <label>{{ translate('Description') }}</label>
            <textarea name="description" rows="2" required maxlength="1000" placeholder="{{ translate('e.g. Daily till deposit to bank, weekly bKash → DBBL withdrawal') }}"></textarea>
        </div>

        <div class="form-group">
            <label>{{ translate('Date & time') }}</label>
            <input type="datetime-local" name="transacted_at" value="{{ now()->format('Y-m-d\TH:i') }}">
        </div>

        <div class="actions">
            <a href="{{ route('admin.account-transactions.index') }}" class="btn btn-light" style="flex:1;">{{ translate('Cancel') }}</a>
            <button type="submit" class="btn btn-primary" style="flex:2;">{{ translate('Post transfer') }}</button>
        </div>
    </form>
    @endif

</div>
@endsection
