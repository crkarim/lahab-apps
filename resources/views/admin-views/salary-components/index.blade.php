@extends('layouts.admin.app')

@section('title', translate('Salary Components'))

@section('content')
@include('admin-views._partials.hrm_settings_nav', ['active' => 'components'])
<style>
    .lh-sc-page { max-width: 1100px; margin: 0 auto; }
    .lh-sc-hero {
        background: linear-gradient(135deg, #fff 0%, #f0fff4 100%);
        border: 1px solid #cfe6d3; border-radius: 16px;
        padding: 22px 26px; margin-bottom: 18px;
        display: flex; gap: 18px; align-items: center; flex-wrap: wrap;
    }
    .lh-sc-hero .icon {
        width: 56px; height: 56px; border-radius: 50%;
        background: rgba(30, 142, 62, 0.14); color: #1E8E3E;
        display: flex; align-items: center; justify-content: center;
        font-size: 26px; flex-shrink: 0;
    }
    .lh-sc-hero h1 { margin: 0; font-size: 20px; font-weight: 800; color: #1A1A1A; }
    .lh-sc-hero p  { margin: 2px 0 0; color: #6A6A70; font-size: 13px; max-width: 700px; }
    .lh-sc-hero .actions { margin-left: auto; }

    .lh-sum-tile {
        background: #fff; border: 1px solid #E5E7EB; border-radius: 12px;
        padding: 14px 18px; margin-bottom: 14px;
        display: flex; align-items: center; gap: 14px;
    }
    .lh-sum-tile .pct {
        font-size: 28px; font-weight: 800; color: #1A1A1A;
        font-variant-numeric: tabular-nums;
    }
    .lh-sum-tile .pct.warn { color: #C82626; }
    .lh-sum-tile .pct.ok   { color: #1E8E3E; }
    .lh-sum-tile .label {
        font-size: 11px; font-weight: 800; color: #6A6A70;
        text-transform: uppercase; letter-spacing: 1.1px;
    }
    .lh-sum-tile .help { font-size: 12px; color: #6A6A70; margin-top: 2px; }

    .lh-card {
        background: #fff; border: 1px solid #E5E7EB; border-radius: 12px;
        padding: 4px 0; margin-bottom: 14px; overflow-x: auto;
    }
    .lh-card h3 {
        font-size: 11px; font-weight: 800; letter-spacing: 1.4px;
        color: #6A6A70; text-transform: uppercase; margin: 14px 16px 10px;
        display: flex; align-items: center; gap: 8px;
    }
    .lh-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .lh-table th {
        font-size: 11px; font-weight: 700; color: #6A6A70;
        text-transform: uppercase; letter-spacing: 1px;
        padding: 10px 14px; border-bottom: 1px solid #F0F2F5;
        text-align: left; white-space: nowrap;
    }
    .lh-table td { padding: 8px 14px; border-bottom: 1px solid #F0F2F5; vertical-align: middle; }
    .lh-table tr:last-child td { border-bottom: 0; }
    .lh-table input[type="text"],
    .lh-table input[type="number"] {
        width: 100%; border: 1px solid #E5E7EB; border-radius: 6px;
        padding: 6px 10px; font-size: 13px;
        font-variant-numeric: tabular-nums;
    }
    .lh-table input[type="number"].pct-input { width: 88px; text-align: right; }
    .lh-table input[type="number"].sort-input { width: 64px; text-align: right; }
    .lh-table .row-actions { display: flex; gap: 6px; justify-content: flex-end; }
    .lh-table .row-actions .btn { padding: 3px 10px; font-size: 11px; font-weight: 700; }
    .lh-table .basic-lock {
        font-size: 10px; color: #9B59B6;
        font-weight: 800; letter-spacing: 1px;
    }
    .lh-bottom-bar {
        position: sticky; bottom: 0; background: #fff;
        border: 1px solid #E5E7EB; border-radius: 12px;
        padding: 14px 18px; margin-top: 8px;
        display: flex; gap: 10px; align-items: center;
    }
    .lh-bd-note {
        background: #FFF8E1; border: 1px solid #F4DDA1; border-radius: 10px;
        padding: 10px 14px; margin-top: 10px;
        font-size: 12px; color: #6A4A0A;
    }

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
    .modal-card input, .modal-card select {
        width: 100%; border: 1px solid #E5E7EB; border-radius: 8px;
        padding: 9px 12px; font-size: 14px; margin-top: 4px;
    }
    .modal-card .actions { display: flex; gap: 8px; margin-top: 16px; }
</style>

<div class="lh-sc-page">

    @if(session('error'))<div class="alert alert-soft-warning">{{ session('error') }}</div>@endif
    @if(session('success'))<div class="alert alert-soft-success">{{ session('success') }}</div>@endif

    <div class="lh-sc-hero">
        <div class="icon">💸</div>
        <div>
            <h1>{{ translate('Salary components') }}</h1>
            <p>{{ translate('Allowance and deduction line items used on every pay slip. Allowance percentages drive the gross-distribute button on the employee form. Deductions stay manual.') }}</p>
        </div>
        <div class="actions">
            <button type="button" class="btn btn-primary" onclick="document.getElementById('lh-new-comp').classList.add('open')">
                + {{ translate('New component') }}
            </button>
        </div>
    </div>

    <div class="lh-sum-tile">
        @php $isOk = abs($allowanceTotal - 100) < 0.01; @endphp
        <div class="pct {{ $isOk ? 'ok' : 'warn' }}">{{ number_format($allowanceTotal, 2) }}%</div>
        <div>
            <div class="label">{{ translate('Allowance distribution total') }}</div>
            <div class="help">
                @if($isOk)
                    ✓ {{ translate('Sums to 100% — gross will distribute exactly across these components.') }}
                @else
                    ⚠ {{ translate('Should sum to 100% for clean distribution. Currently') }} {{ $allowanceTotal < 100 ? translate('under (gap will be unallocated)') : translate('over (overshoot)') }}.
                @endif
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.salary-components.bulk-update') }}">
        @csrf

        <div class="lh-card">
            <h3>
                {{ translate('Allowances') }} <small style="font-weight:600; color:#1A1A1A;">{{ $allowances->count() }}</small>
                <span style="margin-left:auto; font-weight:600; color:#6A6A70; font-size:11px;">
                    {{ translate('% of gross') }} ↓
                </span>
            </h3>
            <table class="lh-table">
                <thead>
                    <tr>
                        <th style="width:30%;">{{ translate('Name') }}</th>
                        <th style="width:14%;">{{ translate('% of gross') }}</th>
                        <th>{{ translate('Taxable') }}</th>
                        <th>{{ translate('Active') }}</th>
                        <th style="width:80px;">{{ translate('Sort') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($allowances as $row)
                    <tr>
                        <td>
                            @if($row->name === 'Basic')
                                <strong>Basic</strong>
                                <span class="basic-lock">LOCKED</span>
                                <input type="hidden" name="rows[{{ $row->id }}][name]" value="Basic">
                            @else
                                <input type="text" name="rows[{{ $row->id }}][name]" value="{{ $row->name }}" maxlength="80">
                            @endif
                        </td>
                        <td>
                            <input type="number" class="pct-input" step="0.01" min="0" max="100"
                                   name="rows[{{ $row->id }}][default_pct]"
                                   value="{{ $row->default_pct !== null ? rtrim(rtrim(number_format((float) $row->default_pct, 2, '.', ''), '0'), '.') : '' }}"
                                   placeholder="—">
                        </td>
                        <td>
                            <input type="checkbox" name="rows[{{ $row->id }}][is_taxable]" value="1" {{ $row->is_taxable ? 'checked' : '' }}>
                        </td>
                        <td>
                            @if($row->name === 'Basic')
                                <input type="checkbox" checked disabled>
                                <input type="hidden" name="rows[{{ $row->id }}][is_active]" value="1">
                            @else
                                <input type="checkbox" name="rows[{{ $row->id }}][is_active]" value="1" {{ $row->is_active ? 'checked' : '' }}>
                            @endif
                        </td>
                        <td>
                            <input type="number" class="sort-input" name="rows[{{ $row->id }}][sort_order]" value="{{ $row->sort_order }}">
                        </td>
                        <td>
                            <div class="row-actions">
                                @if($row->name !== 'Basic')
                                <form method="POST" action="{{ route('admin.salary-components.destroy', ['id' => $row->id]) }}"
                                      onsubmit="return confirm('{{ translate('Delete this component? Deactivates if in use by any employee.') }}'); event.stopPropagation();">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-light" style="color:#C82626;">✕</button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="lh-card">
            <h3>{{ translate('Deductions') }} <small style="font-weight:600; color:#1A1A1A;">{{ $deductions->count() }}</small></h3>
            <table class="lh-table">
                <thead>
                    <tr>
                        <th style="width:30%;">{{ translate('Name') }}</th>
                        <th>{{ translate('Active') }}</th>
                        <th style="width:80px;">{{ translate('Sort') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($deductions as $row)
                    <tr>
                        <td>
                            <input type="text" name="rows[{{ $row->id }}][name]" value="{{ $row->name }}" maxlength="80">
                        </td>
                        <td>
                            <input type="checkbox" name="rows[{{ $row->id }}][is_active]" value="1" {{ $row->is_active ? 'checked' : '' }}>
                        </td>
                        <td>
                            <input type="number" class="sort-input" name="rows[{{ $row->id }}][sort_order]" value="{{ $row->sort_order }}">
                        </td>
                        <td>
                            <div class="row-actions">
                                <form method="POST" action="{{ route('admin.salary-components.destroy', ['id' => $row->id]) }}"
                                      onsubmit="return confirm('{{ translate('Delete this component? Deactivates if in use by any employee.') }}'); event.stopPropagation();">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-light" style="color:#C82626;">✕</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="lh-bottom-bar">
            <div style="font-size:12px; color:#6A6A70;">
                {{ translate('Click "Save changes" to apply all edits in one go. The total above updates after save.') }}
            </div>
            <button type="submit" class="btn btn-primary" style="margin-left:auto;">{{ translate('Save changes') }}</button>
        </div>
    </form>

    <div class="lh-bd-note">
        <strong>{{ translate('BD-standard split') }}:</strong> Basic 60% · House Rent 30% · Medical 5% · Transport 5% = 100%.
        {{ translate('Adjust if your company uses a different ratio (e.g. 50/30/10/10). Setting an allowance to 0% keeps it manual-only.') }}
    </div>

</div>

{{-- New component modal --}}
<div class="modal-overlay" id="lh-new-comp" onclick="if(event.target===this) this.classList.remove('open')">
    <form method="POST" action="{{ route('admin.salary-components.store') }}" class="modal-card">
        @csrf
        <h2>{{ translate('New salary component') }}</h2>
        <p>{{ translate('Add a custom allowance or deduction. Allowances participate in gross distribution; deductions are manual.') }}</p>

        <label>{{ translate('Name') }}</label>
        <input type="text" name="name" required maxlength="80" placeholder="e.g. Festival Bonus">

        <label style="display:block; margin-top:10px;">{{ translate('Type') }}</label>
        <select name="type" required id="lh-newcomp-type" onchange="document.getElementById('lh-newcomp-pctrow').style.display = this.value === 'allowance' ? 'block' : 'none'">
            <option value="allowance" selected>{{ translate('Allowance (added to gross)') }}</option>
            <option value="deduction">{{ translate('Deduction (subtracted to net)') }}</option>
        </select>

        <div id="lh-newcomp-pctrow">
            <label style="display:block; margin-top:10px;">{{ translate('% of gross') }} <span style="color:#6A6A70; font-weight:500;">({{ translate('blank = manual only') }})</span></label>
            <input type="number" name="default_pct" step="0.01" min="0" max="100" placeholder="0.00">
        </div>

        <label style="display:block; margin-top:10px;">
            <input type="checkbox" name="is_taxable" value="1" style="width:auto; margin-right:6px;">
            {{ translate('Taxable') }}
        </label>

        <label style="display:block; margin-top:10px;">{{ translate('Sort order') }}</label>
        <input type="number" name="sort_order" value="100">

        <div class="actions">
            <button type="button" class="btn btn-light" style="flex:1;" onclick="document.getElementById('lh-new-comp').classList.remove('open')">{{ translate('Cancel') }}</button>
            <button type="submit" class="btn btn-primary" style="flex:1;">{{ translate('Create') }}</button>
        </div>
    </form>
</div>
@endsection
