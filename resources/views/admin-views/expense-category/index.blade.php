@extends('layouts.admin.app')

@section('title', translate('Expense Categories'))

@section('content')
<style>
    .lh-cat-page { max-width: 1100px; margin: 0 auto; }
    .lh-cat-hero {
        background: linear-gradient(135deg, #fff 0%, #f0f7ff 100%);
        border: 1px solid #d6e3f7; border-radius: 16px;
        padding: 22px 26px; margin-bottom: 18px;
        display: flex; gap: 18px; align-items: center; flex-wrap: wrap;
    }
    .lh-cat-hero .icon { width: 56px; height: 56px; border-radius: 50%; background: rgba(71, 148, 255, 0.14); color: #4794FF; display: flex; align-items: center; justify-content: center; font-size: 26px; flex-shrink: 0; }
    .lh-cat-hero h1 { margin: 0; font-size: 20px; font-weight: 800; color: #1A1A1A; }
    .lh-cat-hero p  { margin: 2px 0 0; color: #6A6A70; font-size: 13px; }
    .lh-cat-hero .actions { margin-left: auto; }

    .lh-card { background: #fff; border: 1px solid #E5E7EB; border-radius: 12px; padding: 4px 0; margin-bottom: 14px; overflow-x: auto; }
    .lh-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .lh-table th { font-size: 11px; font-weight: 700; color: #6A6A70; text-transform: uppercase; letter-spacing: 1px; padding: 10px 14px; border-bottom: 1px solid #F0F2F5; text-align: left; white-space: nowrap; }
    .lh-table td { padding: 10px 14px; border-bottom: 1px solid #F0F2F5; vertical-align: middle; }
    .lh-table .row-actions { display: flex; gap: 6px; justify-content: flex-end; }
    .lh-table .row-actions .btn { padding: 3px 10px; font-size: 11px; font-weight: 700; }
    .lh-table .cat-pill { display: inline-block; padding: 2px 10px; font-size: 11px; font-weight: 800; color: #fff; border-radius: 999px; }
    .lh-table .child-row td:first-child { padding-left: 36px; }
    .lh-table .child-row td:first-child::before { content: '↳ '; color: #6A6A70; }
    .lh-table .code-mono { font-family: monospace; font-size: 11px; color: #6A6A70; background: #F0F2F5; padding: 1px 6px; border-radius: 3px; }
    .lh-table .status-pill { display:inline-block; padding:2px 8px; font-size:10px; font-weight:800; letter-spacing:1px; border-radius:999px; }
    .lh-table .status-pill.on { background:#ECFFEF; color:#1E8E3E; }
    .lh-table .status-pill.off { background:#F0F2F5; color:#6A6A70; }
    .lh-empty { padding: 22px; text-align: center; color: #6A6A70; font-size: 13px; }

    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1050; }
    .modal-overlay.open { display: flex; }
    .modal-card { background: #fff; border-radius: 14px; max-width: 480px; width: 92%; padding: 22px 24px; max-height: 90vh; overflow-y: auto; }
    .modal-card h2 { font-size: 18px; font-weight: 800; margin: 0 0 4px; color: #1A1A1A; }
    .modal-card p { color: #6A6A70; font-size: 13px; margin: 0 0 14px; }
    .modal-card label { font-size: 12px; font-weight: 700; color: #6A6A70; }
    .modal-card input, .modal-card select, .modal-card textarea { width: 100%; border: 1px solid #E5E7EB; border-radius: 8px; padding: 9px 12px; font-size: 14px; margin-top: 4px; }
    .modal-card .actions { display: flex; gap: 8px; margin-top: 16px; }
</style>

<div class="lh-cat-page">

    @if(session('error'))<div class="alert alert-soft-danger">{{ session('error') }}</div>@endif
    @if(session('success'))<div class="alert alert-soft-success">{{ session('success') }}</div>@endif

    <div class="lh-cat-hero">
        <div class="icon">🏷️</div>
        <div>
            <h1>{{ translate('Expense categories') }}</h1>
            <p>{{ translate('Two-level taxonomy for bills. Top-level: Rent, Utilities, Fuel, Raw Materials, etc. Sub-categories under Utilities: Electricity, Water, Gas, Internet. Edit, recolor, deactivate, or add new.') }}</p>
        </div>
        <div class="actions">
            <button type="button" class="btn btn-primary" onclick="document.getElementById('lh-new-cat').classList.add('open')">+ {{ translate('New category') }}</button>
        </div>
    </div>

    <div class="lh-card">
        <table class="lh-table">
            <thead>
                <tr>
                    <th>{{ translate('Name') }}</th>
                    <th>{{ translate('Code') }}</th>
                    <th>{{ translate('Bills') }}</th>
                    <th>{{ translate('Status') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @php
                $byParent = $categories->groupBy('parent_id');
                $tops     = $byParent->get(null, collect())->sortBy(['sort_order', 'name']);
            @endphp
            @forelse($tops as $top)
                @include('admin-views.expense-category._row', ['cat' => $top, 'isChild' => false, 'allCategories' => $categories])
                @foreach($byParent->get($top->id, collect())->sortBy(['sort_order', 'name']) as $child)
                    @include('admin-views.expense-category._row', ['cat' => $child, 'isChild' => true, 'allCategories' => $categories])
                @endforeach
            @empty
                <tr><td colspan="5" class="lh-empty">{{ translate('No categories yet — seed should have run on migration.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

</div>

{{-- New category modal --}}
<div class="modal-overlay" id="lh-new-cat" onclick="if(event.target===this) this.classList.remove('open')">
    <form method="POST" action="{{ route('admin.expense-categories.store') }}" class="modal-card">
        @csrf
        <h2>{{ translate('New expense category') }}</h2>
        <p>{{ translate('Pick a parent only if you\'re adding a sub-category (e.g. under Utilities).') }}</p>

        <label>{{ translate('Name') }}</label>
        <input type="text" name="name" required maxlength="80" placeholder="e.g. Petrol — Generator">

        <div class="form-row" style="margin-top:10px;">
            <div class="col-md-6 form-group">
                <label>{{ translate('Code') }}</label>
                <input type="text" name="code" required maxlength="40" placeholder="petrol_generator">
            </div>
            <div class="col-md-6 form-group">
                <label>{{ translate('Color') }}</label>
                <input type="color" name="color" value="#6A6A70" style="height:38px;">
            </div>
        </div>

        <label style="display:block; margin-top:10px;">{{ translate('Parent category') }} <small style="color:#6A6A70; font-weight:500;">({{ translate('blank = top-level') }})</small></label>
        <select name="parent_id">
            <option value="">— {{ translate('top-level') }} —</option>
            @foreach($topLevel as $top)<option value="{{ $top->id }}">{{ $top->name }}</option>@endforeach
        </select>

        <label style="display:block; margin-top:10px;">{{ translate('Sort order') }}</label>
        <input type="number" name="sort_order" value="100">

        <label style="display:block; margin-top:10px;">{{ translate('Description') }}</label>
        <textarea name="description" rows="2" maxlength="500"></textarea>

        <div class="actions">
            <button type="button" class="btn btn-light" style="flex:1;" onclick="document.getElementById('lh-new-cat').classList.remove('open')">{{ translate('Cancel') }}</button>
            <button type="submit" class="btn btn-primary" style="flex:1;">{{ translate('Create') }}</button>
        </div>
    </form>
</div>

{{-- Edit category modal --}}
<div class="modal-overlay" id="lh-edit-cat" onclick="if(event.target===this) this.classList.remove('open')">
    <form method="POST" id="lh-edit-cat-form" class="modal-card">
        @csrf
        <h2>{{ translate('Edit category') }}</h2>

        <label>{{ translate('Name') }}</label>
        <input type="text" name="name" id="ec_name" required maxlength="80">

        <div class="form-row" style="margin-top:10px;">
            <div class="col-md-6 form-group">
                <label>{{ translate('Code') }}</label>
                <input type="text" name="code" id="ec_code" required maxlength="40">
            </div>
            <div class="col-md-6 form-group">
                <label>{{ translate('Color') }}</label>
                <input type="color" name="color" id="ec_color" style="height:38px;">
            </div>
        </div>

        <label style="display:block; margin-top:10px;">{{ translate('Parent') }}</label>
        <select name="parent_id" id="ec_parent">
            <option value="">— {{ translate('top-level') }} —</option>
            @foreach($topLevel as $top)<option value="{{ $top->id }}">{{ $top->name }}</option>@endforeach
        </select>

        <div class="form-row" style="margin-top:10px;">
            <div class="col-md-6 form-group">
                <label>{{ translate('Sort') }}</label>
                <input type="number" name="sort_order" id="ec_sort">
            </div>
            <div class="col-md-6 form-group" style="display:flex; align-items:flex-end;">
                <label style="display:flex; align-items:center; gap:6px; font-weight:600;">
                    <input type="checkbox" name="is_active" id="ec_active" value="1" style="width:auto; margin:0;">
                    {{ translate('Active') }}
                </label>
            </div>
        </div>

        <label style="display:block; margin-top:10px;">{{ translate('Description') }}</label>
        <textarea name="description" id="ec_desc" rows="2" maxlength="500"></textarea>

        <div class="actions">
            <button type="button" class="btn btn-light" style="flex:1;" onclick="document.getElementById('lh-edit-cat').classList.remove('open')">{{ translate('Cancel') }}</button>
            <button type="submit" class="btn btn-primary" style="flex:1;">{{ translate('Save') }}</button>
        </div>
    </form>
</div>

<script>
function lhEditCat(c) {
    var form = document.getElementById('lh-edit-cat-form');
    form.action = '{{ url('admin/expense-categories') }}/' + c.id;
    document.getElementById('ec_name').value     = c.name || '';
    document.getElementById('ec_code').value     = c.code || '';
    document.getElementById('ec_color').value    = c.color || '#6A6A70';
    document.getElementById('ec_parent').value   = c.parent_id || '';
    document.getElementById('ec_sort').value     = c.sort_order || 0;
    document.getElementById('ec_active').checked = !!c.is_active;
    document.getElementById('ec_desc').value     = c.description || '';
    document.getElementById('lh-edit-cat').classList.add('open');
}
</script>
@endsection
