@extends('layouts.admin.app')

@section('title', translate('Suppliers'))

@section('content')
@php $sym = fn ($v) => \App\CentralLogics\Helpers::set_symbol($v); @endphp

<style>
    .lh-sup-page { max-width: 1200px; margin: 0 auto; }
    .lh-sup-hero {
        background: linear-gradient(135deg, #fff 0%, #fff7ee 100%);
        border: 1px solid #f1e3cf; border-radius: 16px;
        padding: 22px 26px; margin-bottom: 18px;
        display: flex; gap: 18px; align-items: center; flex-wrap: wrap;
    }
    .lh-sup-hero .icon {
        width: 56px; height: 56px; border-radius: 50%;
        background: rgba(232, 126, 34, 0.14); color: #E67E22;
        display: flex; align-items: center; justify-content: center;
        font-size: 26px; flex-shrink: 0;
    }
    .lh-sup-hero h1 { margin: 0; font-size: 20px; font-weight: 800; color: #1A1A1A; }
    .lh-sup-hero p  { margin: 2px 0 0; color: #6A6A70; font-size: 13px; }
    .lh-sup-hero .actions { margin-left: auto; }

    .lh-totals { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 18px; }
    .lh-tile { background: #fff; border: 1px solid #E5E7EB; border-radius: 12px; padding: 14px 16px; }
    .lh-tile .label { font-size: 10px; font-weight: 800; color: #6A6A70; text-transform: uppercase; letter-spacing: 1.1px; }
    .lh-tile .value { font-size: 22px; font-weight: 800; color: #1A1A1A; font-variant-numeric: tabular-nums; margin-top: 2px; }
    .lh-tile.outstanding .value { color: #C82626; }

    .lh-card { background: #fff; border: 1px solid #E5E7EB; border-radius: 12px; padding: 4px 0; margin-bottom: 14px; overflow-x: auto; }
    .lh-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .lh-table th { font-size: 11px; font-weight: 700; color: #6A6A70; text-transform: uppercase; letter-spacing: 1px; padding: 10px 14px; border-bottom: 1px solid #F0F2F5; text-align: left; white-space: nowrap; }
    .lh-table td { padding: 10px 14px; border-bottom: 1px solid #F0F2F5; vertical-align: middle; }
    .lh-table tr:last-child td { border-bottom: 0; }
    .lh-table .num { font-variant-numeric: tabular-nums; font-weight: 700; text-align: right; }
    .lh-table .num.outstanding { color: #C82626; }
    .lh-table .row-actions { display: flex; gap: 6px; justify-content: flex-end; flex-wrap: wrap; }
    .lh-table .row-actions .btn { padding: 3px 10px; font-size: 11px; font-weight: 700; }
    .lh-table .status-pill { display: inline-block; padding: 2px 8px; font-size: 10px; font-weight: 800; letter-spacing: 1px; border-radius: 999px; }
    .lh-table .status-pill.on { background: #ECFFEF; color: #1E8E3E; }
    .lh-table .status-pill.off { background: #F0F2F5; color: #6A6A70; }
    .lh-table .terms-pill { display:inline-block; padding:1px 6px; background:#F4F6F8; color:#6A6A70; border-radius:3px; font-family:monospace; font-size:11px; font-weight:700; }
    .lh-empty { padding: 22px; text-align: center; color: #6A6A70; font-size: 13px; }

    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1050; }
    .modal-overlay.open { display: flex; }
    .modal-card { background: #fff; border-radius: 14px; max-width: 540px; width: 92%; padding: 22px 24px; max-height: 90vh; overflow-y: auto; }
    .modal-card h2 { font-size: 18px; font-weight: 800; margin: 0 0 4px; color: #1A1A1A; }
    .modal-card p { color: #6A6A70; font-size: 13px; margin: 0 0 14px; }
    .modal-card label { font-size: 12px; font-weight: 700; color: #6A6A70; }
    .modal-card input, .modal-card select, .modal-card textarea { width: 100%; border: 1px solid #E5E7EB; border-radius: 8px; padding: 9px 12px; font-size: 14px; margin-top: 4px; }
    .modal-card .actions { display: flex; gap: 8px; margin-top: 16px; }
</style>

<div class="lh-sup-page">

    @if(session('error'))<div class="alert alert-soft-danger">{{ session('error') }}</div>@endif
    @if(session('success'))<div class="alert alert-soft-success">{{ session('success') }}</div>@endif

    <div class="lh-sup-hero">
        <div class="icon">🏭</div>
        <div>
            <h1>{{ translate('Suppliers') }}</h1>
            <p>{{ translate('Vendors you buy from — meat, fish, vegetables, gas cylinders, fuel, packaging. Bills are recorded against these and their balances accumulate until paid.') }}</p>
        </div>
        <div class="actions">
            <button type="button" class="btn btn-primary" onclick="document.getElementById('lh-new-sup').classList.add('open')">+ {{ translate('Add supplier') }}</button>
        </div>
    </div>

    <div class="lh-totals">
        <div class="lh-tile">
            <div class="label">{{ translate('Active suppliers') }}</div>
            <div class="value">{{ $totals['count'] }}</div>
        </div>
        <div class="lh-tile outstanding">
            <div class="label">{{ translate('Total outstanding') }}</div>
            <div class="value">{{ $sym($totals['outstanding']) }}</div>
        </div>
    </div>

    <div class="lh-card">
        <table class="lh-table">
            <thead>
                <tr>
                    <th>{{ translate('Supplier') }}</th>
                    <th>{{ translate('Contact') }}</th>
                    <th>{{ translate('Branch') }}</th>
                    <th>{{ translate('Terms') }}</th>
                    <th class="num">{{ translate('Bills') }}</th>
                    <th class="num">{{ translate('Outstanding') }}</th>
                    <th>{{ translate('Status') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($suppliers as $s)
                <tr>
                    <td>
                        <strong>{{ $s->name }}</strong>
                        @if($s->code)<span style="color:#6A6A70; font-family:monospace; font-size:11px; margin-left:6px;">{{ $s->code }}</span>@endif
                        @if($s->bin)<div style="font-size:10px; color:#6A6A70;">BIN {{ $s->bin }}</div>@endif
                    </td>
                    <td style="font-size:12px;">
                        @if($s->contact_person)<div>{{ $s->contact_person }}</div>@endif
                        @if($s->phone)<div style="color:#6A6A70;"><a href="tel:{{ $s->phone }}" style="color:inherit;">{{ $s->phone }}</a></div>@endif
                        @if($s->email)<div style="color:#6A6A70;"><a href="mailto:{{ $s->email }}" style="color:inherit;">{{ $s->email }}</a></div>@endif
                    </td>
                    <td style="font-size:12px;">{{ $s->branch?->name ?: '— ' . translate('HQ-wide') . ' —' }}</td>
                    <td><span class="terms-pill">{{ str_replace('net_', 'NET ', $s->payment_terms) }}</span></td>
                    <td class="num">{{ $s->expenses_count }}</td>
                    <td class="num outstanding">{{ $s->outstanding_balance > 0 ? $sym($s->outstanding_balance) : '—' }}</td>
                    <td>
                        @if($s->is_active)<span class="status-pill on">ACTIVE</span>@else<span class="status-pill off">INACTIVE</span>@endif
                    </td>
                    <td>
                        <div class="row-actions">
                            @php
                                $editPayload = json_encode([
                                    'id'             => $s->id,
                                    'name'           => $s->name,
                                    'code'           => $s->code,
                                    'contact_person' => $s->contact_person,
                                    'phone'          => $s->phone,
                                    'email'          => $s->email,
                                    'address'        => $s->address,
                                    'bin'            => $s->bin,
                                    'payment_terms'  => $s->payment_terms,
                                    'sort_order'     => $s->sort_order,
                                    'notes'          => $s->notes,
                                    'is_active'      => (bool) $s->is_active,
                                ], JSON_HEX_APOS | JSON_HEX_QUOT);
                            @endphp
                            <button type="button" class="btn btn-light" onclick='lhEditSup({!! $editPayload !!})'>{{ translate('Edit') }}</button>
                            <form method="POST" action="{{ route('admin.suppliers.destroy', ['id' => $s->id]) }}" onsubmit="return confirm('{{ translate('Delete this supplier? Deactivates if any bills booked.') }}')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-light" style="color:#C82626;">✕</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="lh-empty">{{ translate('No suppliers yet — click "+ Add supplier" above.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

</div>

{{-- Add supplier modal --}}
<div class="modal-overlay" id="lh-new-sup" onclick="if(event.target===this) this.classList.remove('open')">
    <form method="POST" action="{{ route('admin.suppliers.store') }}" class="modal-card">
        @csrf
        <h2>{{ translate('Add supplier') }}</h2>
        <p>{{ translate('Vendor master record. Required: name. Optional: contact, BIN, payment terms.') }}</p>

        <label>{{ translate('Name') }}</label>
        <input type="text" name="name" required maxlength="120" placeholder="e.g. Anwar Meat Suppliers">

        <div class="form-row" style="margin-top:10px;">
            <div class="col-md-6 form-group">
                <label>{{ translate('Code') }}</label>
                <input type="text" name="code" maxlength="32" placeholder="opt — internal ref">
            </div>
            <div class="col-md-6 form-group">
                <label>{{ translate('BIN') }} <small style="color:#6A6A70; font-weight:500;">({{ translate('VAT reg #') }})</small></label>
                <input type="text" name="bin" maxlength="32">
            </div>
        </div>

        <div class="form-row">
            <div class="col-md-6 form-group">
                <label>{{ translate('Contact person') }}</label>
                <input type="text" name="contact_person" maxlength="120">
            </div>
            <div class="col-md-6 form-group">
                <label>{{ translate('Phone') }}</label>
                <input type="tel" name="phone" maxlength="30">
            </div>
        </div>

        <label style="display:block; margin-top:10px;">{{ translate('Email') }}</label>
        <input type="email" name="email" maxlength="120">

        <label style="display:block; margin-top:10px;">{{ translate('Address') }}</label>
        <textarea name="address" rows="2" maxlength="500"></textarea>

        <div class="form-row" style="margin-top:10px;">
            <div class="col-md-6 form-group">
                <label>{{ translate('Payment terms') }}</label>
                <select name="payment_terms">
                    <option value="net_0">{{ translate('Cash on delivery') }}</option>
                    <option value="net_7">NET 7</option>
                    <option value="net_15">NET 15</option>
                    <option value="net_30" selected>NET 30</option>
                    <option value="net_45">NET 45</option>
                    <option value="net_60">NET 60</option>
                </select>
            </div>
            @if($isMaster)
            <div class="col-md-6 form-group">
                <label>{{ translate('Branch') }} <small style="color:#6A6A70; font-weight:500;">({{ translate('blank = HQ-wide') }})</small></label>
                <select name="branch_id">
                    <option value="">— {{ translate('HQ-wide') }} —</option>
                    @foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                </select>
            </div>
            @endif
        </div>

        <label style="display:block; margin-top:10px;">{{ translate('Notes') }}</label>
        <textarea name="notes" rows="2" maxlength="1000"></textarea>

        <div class="actions">
            <button type="button" class="btn btn-light" style="flex:1;" onclick="document.getElementById('lh-new-sup').classList.remove('open')">{{ translate('Cancel') }}</button>
            <button type="submit" class="btn btn-primary" style="flex:1;">{{ translate('Create') }}</button>
        </div>
    </form>
</div>

{{-- Edit supplier modal --}}
<div class="modal-overlay" id="lh-edit-sup" onclick="if(event.target===this) this.classList.remove('open')">
    <form method="POST" id="lh-edit-sup-form" class="modal-card">
        @csrf
        <h2>{{ translate('Edit supplier') }}</h2>

        <label>{{ translate('Name') }}</label>
        <input type="text" name="name" id="es_name" required maxlength="120">

        <div class="form-row" style="margin-top:10px;">
            <div class="col-md-6 form-group">
                <label>{{ translate('Code') }}</label>
                <input type="text" name="code" id="es_code" maxlength="32">
            </div>
            <div class="col-md-6 form-group">
                <label>{{ translate('BIN') }}</label>
                <input type="text" name="bin" id="es_bin" maxlength="32">
            </div>
        </div>

        <div class="form-row">
            <div class="col-md-6 form-group">
                <label>{{ translate('Contact') }}</label>
                <input type="text" name="contact_person" id="es_contact" maxlength="120">
            </div>
            <div class="col-md-6 form-group">
                <label>{{ translate('Phone') }}</label>
                <input type="tel" name="phone" id="es_phone" maxlength="30">
            </div>
        </div>

        <label style="display:block; margin-top:10px;">{{ translate('Email') }}</label>
        <input type="email" name="email" id="es_email" maxlength="120">

        <label style="display:block; margin-top:10px;">{{ translate('Address') }}</label>
        <textarea name="address" id="es_address" rows="2" maxlength="500"></textarea>

        <div class="form-row" style="margin-top:10px;">
            <div class="col-md-6 form-group">
                <label>{{ translate('Payment terms') }}</label>
                <select name="payment_terms" id="es_terms">
                    <option value="net_0">{{ translate('Cash on delivery') }}</option>
                    <option value="net_7">NET 7</option>
                    <option value="net_15">NET 15</option>
                    <option value="net_30">NET 30</option>
                    <option value="net_45">NET 45</option>
                    <option value="net_60">NET 60</option>
                </select>
            </div>
            <div class="col-md-6 form-group" style="display:flex; align-items:flex-end;">
                <label style="display:flex; align-items:center; gap:6px; font-weight:600;">
                    <input type="checkbox" name="is_active" id="es_active" value="1" style="width:auto; margin:0;">
                    {{ translate('Active') }}
                </label>
            </div>
        </div>

        <label style="display:block; margin-top:10px;">{{ translate('Notes') }}</label>
        <textarea name="notes" id="es_notes" rows="2" maxlength="1000"></textarea>

        <div class="actions">
            <button type="button" class="btn btn-light" style="flex:1;" onclick="document.getElementById('lh-edit-sup').classList.remove('open')">{{ translate('Cancel') }}</button>
            <button type="submit" class="btn btn-primary" style="flex:1;">{{ translate('Save') }}</button>
        </div>
    </form>
</div>

<script>
function lhEditSup(s) {
    var form = document.getElementById('lh-edit-sup-form');
    form.action = '{{ url('admin/suppliers') }}/' + s.id;
    document.getElementById('es_name').value     = s.name || '';
    document.getElementById('es_code').value     = s.code || '';
    document.getElementById('es_bin').value      = s.bin || '';
    document.getElementById('es_contact').value  = s.contact_person || '';
    document.getElementById('es_phone').value    = s.phone || '';
    document.getElementById('es_email').value    = s.email || '';
    document.getElementById('es_address').value  = s.address || '';
    document.getElementById('es_terms').value    = s.payment_terms || 'net_30';
    document.getElementById('es_active').checked = !!s.is_active;
    document.getElementById('es_notes').value    = s.notes || '';
    document.getElementById('lh-edit-sup').classList.add('open');
}
</script>
@endsection
