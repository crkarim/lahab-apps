@extends('layouts.admin.app')

@section('title', translate('Record bill'))

@section('content')

<style>
    .lh-bn-page { max-width: 980px; margin: 0 auto; }
    .lh-bn-hero {
        background: linear-gradient(135deg, #fff 0%, #fff7ee 100%);
        border: 1px solid #f1e3cf; border-radius: 16px;
        padding: 18px 22px; margin-bottom: 14px;
        display: flex; gap: 14px; align-items: center;
    }
    .lh-bn-hero h1 { margin:0; font-size:18px; font-weight:800; }
    .lh-bn-hero p  { margin:2px 0 0; color:#6A6A70; font-size:13px; }

    .lh-bn-card { background:#fff; border:1px solid #E5E7EB; border-radius:12px; padding:20px 22px; margin-bottom:14px; }
    .lh-bn-card label { font-size:12px; font-weight:700; color:#6A6A70; }
    .lh-bn-card input, .lh-bn-card select, .lh-bn-card textarea {
        width:100%; border:1px solid #E5E7EB; border-radius:8px; padding:9px 12px; font-size:14px;
        margin-top:4px; font-variant-numeric:tabular-nums;
    }
    .lh-bn-card .form-group { margin-bottom:12px; }
    .lh-bn-card .form-row { display:flex; gap:12px; }
    .lh-bn-card .form-row > div { flex:1; }

    .lh-bn-section { font-size:11px; font-weight:800; letter-spacing:1.4px; color:#6A6A70; text-transform:uppercase; margin-bottom:10px; padding-bottom:6px; border-bottom:1px solid #F0F2F5; }

    .lh-line-table { width:100%; border-collapse:collapse; font-size:13px; }
    .lh-line-table th { font-size:10px; font-weight:700; color:#6A6A70; text-transform:uppercase; letter-spacing:.8px; padding:8px 10px; border-bottom:1px solid #F0F2F5; text-align:left; }
    .lh-line-table td { padding:6px 10px; border-bottom:1px solid #F0F2F5; vertical-align:middle; }
    .lh-line-table input, .lh-line-table select { width:100%; border:1px solid #E5E7EB; border-radius:6px; padding:6px 8px; font-size:13px; font-variant-numeric:tabular-nums; }
    .lh-line-table .num { text-align:right; }
    .lh-line-table .qty-input  { width: 80px; }
    .lh-line-table .price-input{ width: 100px; }
    .lh-line-totals { display:flex; justify-content:flex-end; padding-top:14px; }
    .lh-line-totals table { width: 320px; font-size:13px; }
    .lh-line-totals td { padding: 4px 8px; }
    .lh-line-totals td.label { color:#6A6A70; text-align:right; }
    .lh-line-totals td.amt { text-align:right; font-variant-numeric:tabular-nums; font-weight:700; }
    .lh-line-totals tr.grand td { font-size:15px; border-top:2px solid #1A1A1A; padding-top:8px; }
    .lh-line-totals input { width: 100px; text-align:right; }

    .lh-actions { display:flex; gap:10px; padding-top:18px; border-top:1px solid #F0F2F5; margin-top:14px; }
</style>

<div class="lh-bn-page">

    @if(session('error'))<div class="alert alert-soft-danger">{{ session('error') }}</div>@endif

    <div class="lh-bn-hero">
        <div style="font-size:34px;">🧾</div>
        <div>
            <h1>{{ translate('Record bill') }}</h1>
            <p>{{ translate('Supplier invoice with line items. Save records the bill as pending; payments come later from the bill\'s detail page.') }}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.expenses.store') }}">
        @csrf

        <div class="lh-bn-card">
            <div class="lh-bn-section">{{ translate('Header') }}</div>

            <div class="form-row">
                <div class="form-group">
                    <label>{{ translate('Supplier') }} <small style="color:#6A6A70;">({{ translate('blank = direct purchase') }})</small></label>
                    <select name="supplier_id">
                        <option value="">— {{ translate('direct purchase / petty cash') }} —</option>
                        @foreach($suppliers as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}@if($s->payment_terms !== 'net_0') ({{ str_replace('net_', 'NET ', $s->payment_terms) }})@endif</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>{{ translate('Default category') }} <small style="color:#6A6A70;">({{ translate('per-line override below') }})</small></label>
                    <select name="category_id">
                        <option value="">— {{ translate('select') }} —</option>
                        @foreach($categories as $c)
                            <option value="{{ $c->id }}">
                                @if($c->parent_id) ↳ @endif {{ $c->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>{{ translate('Bill number') }} <small style="color:#6A6A70;">({{ translate('supplier\'s invoice ref') }})</small></label>
                    <input type="text" name="bill_no" maxlength="80" placeholder="e.g. INV-2026-04-1234">
                </div>
                <div class="form-group">
                    <label>{{ translate('Bill date') }}</label>
                    <input type="date" name="bill_date" required value="{{ now()->toDateString() }}">
                </div>
                <div class="form-group">
                    <label>{{ translate('Due date') }} <small style="color:#6A6A70;">({{ translate('optional') }})</small></label>
                    <input type="date" name="due_date" value="{{ now()->addDays(30)->toDateString() }}">
                </div>
            </div>

            @if($isMaster)
            <div class="form-group">
                <label>{{ translate('Branch') }}</label>
                <select name="branch_id">
                    <option value="">— {{ translate('HQ-wide') }} —</option>
                    @foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                </select>
            </div>
            @endif

            <div class="form-group">
                <label>{{ translate('Description') }} <small style="color:#6A6A70;">({{ translate('optional') }})</small></label>
                <textarea name="description" rows="2" maxlength="1000" placeholder="{{ translate('What this bill covers — additional notes for accounts.') }}"></textarea>
            </div>
        </div>

        <div class="lh-bn-card">
            <div class="lh-bn-section">{{ translate('Line items') }}</div>

            <table class="lh-line-table" id="lh-lines">
                <thead>
                    <tr>
                        <th style="width:40%;">{{ translate('Description') }}</th>
                        <th style="width:18%;">{{ translate('Category override') }}</th>
                        <th class="num" style="width:90px;">{{ translate('Qty') }}</th>
                        <th class="num" style="width:120px;">{{ translate('Unit price') }}</th>
                        <th class="num" style="width:120px;">{{ translate('Total') }}</th>
                        <th style="width:36px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="lh-line-row">
                        <td><input type="text" name="lines[0][description]" required placeholder="{{ translate('e.g. Beef 25kg, Diesel 50L, Electricity April') }}"></td>
                        <td>
                            <select name="lines[0][category_id]">
                                <option value="">— {{ translate('use default') }} —</option>
                                @foreach($categories as $c)
                                    <option value="{{ $c->id }}">@if($c->parent_id) ↳ @endif {{ $c->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="num"><input type="number" class="qty-input lh-qty" name="lines[0][quantity]" step="0.001" min="0.001" value="1" required></td>
                        <td class="num"><input type="number" class="price-input lh-price" name="lines[0][unit_price]" step="0.01" min="0" value="0" required></td>
                        <td class="num"><input type="number" class="lh-line-total" step="0.01" value="0" readonly tabindex="-1"></td>
                        <td><button type="button" class="btn btn-light btn-sm lh-line-remove" style="display:none; color:#C82626; padding:2px 8px;">✕</button></td>
                    </tr>
                </tbody>
            </table>

            <div style="margin-top:10px;">
                <button type="button" class="btn btn-light btn-sm" id="lh-add-line" style="font-size:12px;">+ {{ translate('Add line') }}</button>
            </div>

            <div class="lh-line-totals">
                <table>
                    <tr><td class="label">{{ translate('Subtotal') }}</td><td class="amt"><span id="lh-sub">0.00</span></td></tr>
                    <tr>
                        <td class="label">{{ translate('VAT') }}</td>
                        <td class="amt"><input type="number" name="vat_amount" step="0.01" min="0" value="0" id="lh-vat"></td>
                    </tr>
                    <tr>
                        <td class="label">{{ translate('Tax / AIT') }}</td>
                        <td class="amt"><input type="number" name="tax_amount" step="0.01" min="0" value="0" id="lh-tax"></td>
                    </tr>
                    <tr>
                        <td class="label">{{ translate('Discount') }}</td>
                        <td class="amt"><input type="number" name="discount" step="0.01" min="0" value="0" id="lh-disc"></td>
                    </tr>
                    <tr class="grand"><td class="label">{{ translate('TOTAL') }}</td><td class="amt"><span id="lh-grand">0.00</span></td></tr>
                </table>
            </div>
        </div>

        <div class="lh-actions">
            <a href="{{ route('admin.expenses.index') }}" class="btn btn-light" style="flex:1;">{{ translate('Cancel') }}</a>
            <button type="submit" class="btn btn-primary" style="flex:2;">{{ translate('Save bill') }}</button>
        </div>
    </form>
</div>

<script>
(function () {
    var rowIdx = 0;

    function recalc() {
        var sub = 0;
        document.querySelectorAll('#lh-lines tbody tr').forEach(function (tr) {
            var qty   = parseFloat(tr.querySelector('.lh-qty').value) || 0;
            var price = parseFloat(tr.querySelector('.lh-price').value) || 0;
            var line  = +(qty * price).toFixed(2);
            tr.querySelector('.lh-line-total').value = line.toFixed(2);
            sub += line;
        });
        sub = +sub.toFixed(2);
        document.getElementById('lh-sub').textContent = sub.toFixed(2);

        var vat  = parseFloat(document.getElementById('lh-vat').value)  || 0;
        var tax  = parseFloat(document.getElementById('lh-tax').value)  || 0;
        var disc = parseFloat(document.getElementById('lh-disc').value) || 0;
        var grand = +(sub + vat + tax - disc).toFixed(2);
        document.getElementById('lh-grand').textContent = grand.toFixed(2);
    }

    document.getElementById('lh-add-line').addEventListener('click', function () {
        rowIdx += 1;
        var first = document.querySelector('#lh-lines .lh-line-row');
        var clone = first.cloneNode(true);
        clone.querySelectorAll('input, select').forEach(function (el) {
            if (el.name) el.name = el.name.replace(/lines\[\d+\]/, 'lines[' + rowIdx + ']');
            if (el.tagName === 'INPUT' && el.type === 'number') el.value = el.classList.contains('lh-qty') ? '1' : '0';
            if (el.tagName === 'INPUT' && el.type === 'text')   el.value = '';
            if (el.tagName === 'SELECT') el.selectedIndex = 0;
        });
        var rm = clone.querySelector('.lh-line-remove');
        if (rm) rm.style.display = 'inline-block';
        document.querySelector('#lh-lines tbody').appendChild(clone);
        recalc();
    });

    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('lh-line-remove')) {
            var rows = document.querySelectorAll('#lh-lines tbody tr');
            if (rows.length <= 1) return;
            e.target.closest('tr').remove();
            recalc();
        }
    });

    document.addEventListener('input', function (e) {
        if (e.target.classList.contains('lh-qty') || e.target.classList.contains('lh-price')
            || e.target.id === 'lh-vat' || e.target.id === 'lh-tax' || e.target.id === 'lh-disc') {
            recalc();
        }
    });

    recalc();
})();
</script>
@endsection
