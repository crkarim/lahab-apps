@extends('layouts.admin.app')

@section('title', translate('Departments'))

@section('content')
@include('admin-views._partials.hrm_settings_nav', ['active' => 'departments'])
<style>
    .lh-dp-page { max-width: 1200px; margin: 0 auto; }
    .lh-dp-hero {
        background: linear-gradient(135deg, #fff 0%, #fff7f0 100%);
        border: 1px solid #f3e0c9; border-radius: 16px;
        padding: 22px 26px; margin-bottom: 18px;
        display: flex; gap: 18px; align-items: center; flex-wrap: wrap;
    }
    .lh-dp-hero .icon {
        width: 56px; height: 56px; border-radius: 50%;
        background: rgba(232, 126, 34, 0.14); color: #E67E22;
        display: flex; align-items: center; justify-content: center;
        font-size: 26px; flex-shrink: 0;
    }
    .lh-dp-hero h1 { margin: 0; font-size: 20px; font-weight: 800; color: #1A1A1A; }
    .lh-dp-hero p  { margin: 2px 0 0; color: #6A6A70; font-size: 13px; max-width: 700px; }
    .lh-dp-hero .actions { margin-left: auto; }

    .lh-card {
        background: #fff; border: 1px solid #E5E7EB; border-radius: 12px;
        padding: 4px 0; margin-bottom: 14px; overflow-x: auto;
    }
    .lh-card h3 {
        font-size: 11px; font-weight: 800; letter-spacing: 1.4px;
        color: #6A6A70; text-transform: uppercase; margin: 14px 16px 10px;
    }
    .lh-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .lh-table th {
        font-size: 11px; font-weight: 700; color: #6A6A70;
        text-transform: uppercase; letter-spacing: 1px;
        padding: 10px 14px; border-bottom: 1px solid #F0F2F5;
        text-align: left; white-space: nowrap;
    }
    .lh-table td { padding: 10px 14px; border-bottom: 1px solid #F0F2F5; vertical-align: middle; }
    .lh-table tr:last-child td { border-bottom: 0; }
    .lh-table .num { font-variant-numeric: tabular-nums; font-weight: 700; color: #1A1A1A; text-align: right; }
    .lh-table .row-actions { display: flex; gap: 6px; justify-content: flex-end; flex-wrap: wrap; }
    .lh-table .row-actions .btn { padding: 3px 10px; font-size: 11px; font-weight: 700; }
    .lh-table .dept-pill {
        display: inline-block; padding: 2px 10px;
        font-size: 11px; font-weight: 800; color: #fff;
        border-radius: 999px;
    }
    .lh-table .code-mono {
        font-family: monospace; font-size: 11px; color: #6A6A70;
        background: #F0F2F5; padding: 1px 6px; border-radius: 3px;
    }
    .lh-table .status-pill {
        display: inline-block; padding: 2px 8px;
        font-size: 10px; font-weight: 800; letter-spacing: 1px;
        border-radius: 999px;
    }
    .lh-table .status-pill.on  { background: #ECFFEF; color: #1E8E3E; }
    .lh-table .status-pill.off { background: #F0F2F5; color: #6A6A70; }
    .lh-empty { padding: 22px; text-align: center; color: #6A6A70; font-size: 13px; }

    .modal-overlay {
        position: fixed; inset: 0; background: rgba(0,0,0,0.5);
        display: none; align-items: center; justify-content: center;
        z-index: 1050;
    }
    .modal-overlay.open { display: flex; }
    .modal-card {
        background: #fff; border-radius: 14px;
        max-width: 520px; width: 92%; padding: 22px 24px;
        max-height: 90vh; overflow-y: auto;
    }
    .modal-card h2 { font-size: 18px; font-weight: 800; margin: 0 0 4px; color: #1A1A1A; }
    .modal-card p  { color: #6A6A70; font-size: 13px; margin: 0 0 14px; }
    .modal-card label { font-size: 12px; font-weight: 700; color: #6A6A70; }
    .modal-card input, .modal-card select, .modal-card textarea {
        width: 100%; border: 1px solid #E5E7EB; border-radius: 8px;
        padding: 9px 12px; font-size: 14px; margin-top: 4px;
    }
    .modal-card .actions { display: flex; gap: 8px; margin-top: 16px; }
</style>

<div class="lh-dp-page">

    @if(session('error'))<div class="alert alert-soft-danger">{{ session('error') }}</div>@endif
    @if(session('success'))<div class="alert alert-soft-success">{{ session('success') }}</div>@endif

    <div class="lh-dp-hero">
        <div class="icon">🏢</div>
        <div>
            <h1>{{ translate('Departments') }}</h1>
            <p>{{ translate('Group employees by function. Seeded with BD restaurant defaults — rename, recolor, deactivate, or add your own. HQ-wide rows (no branch) apply everywhere.') }}</p>
        </div>
        <div class="actions">
            <button type="button" class="btn btn-primary" onclick="document.getElementById('lh-new-dept').classList.add('open')">
                + {{ translate('New department') }}
            </button>
        </div>
    </div>

    <div class="lh-card">
        <h3>{{ translate('All departments') }} <small style="font-weight:600; color:#1A1A1A; margin-left:6px;">{{ $departments->count() }}</small></h3>
        <table class="lh-table">
            <thead>
                <tr>
                    <th>{{ translate('Name') }}</th>
                    <th>{{ translate('Code') }}</th>
                    <th>{{ translate('Branch') }}</th>
                    <th>{{ translate('Head') }}</th>
                    <th class="num">{{ translate('Members') }}</th>
                    <th>{{ translate('Status') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($departments as $d)
                <tr>
                    <td>
                        <span class="dept-pill" style="background: {{ $d->color }};">{{ $d->name }}</span>
                        @if($d->description)
                            <div style="font-size:11px; color:#6A6A70; margin-top:3px; max-width:340px;">{{ $d->description }}</div>
                        @endif
                    </td>
                    <td><span class="code-mono">{{ $d->code }}</span></td>
                    <td style="font-size:12px;">{{ $d->branch?->name ?: '— ' . translate('HQ-wide') . ' —' }}</td>
                    <td style="font-size:12px;">
                        @if($d->head)
                            <strong>{{ trim(($d->head->f_name ?? '') . ' ' . ($d->head->l_name ?? '')) }}</strong>
                            @if($d->head->employee_code)
                                <br><small style="color:#6A6A70;">{{ $d->head->employee_code }}</small>
                            @endif
                        @else
                            <span style="color:#6A6A70;">— {{ translate('not set') }} —</span>
                        @endif
                    </td>
                    <td class="num">{{ $d->members_count }}</td>
                    <td>
                        @if($d->is_active)
                            <span class="status-pill on">ACTIVE</span>
                        @else
                            <span class="status-pill off">INACTIVE</span>
                        @endif
                    </td>
                    <td>
                        <div class="row-actions">
                            @php
                                $deptPayload = json_encode([
                                    'id'            => $d->id,
                                    'name'          => $d->name,
                                    'code'          => $d->code,
                                    'head_admin_id' => $d->head_admin_id,
                                    'color'         => $d->color,
                                    'description'   => $d->description,
                                    'sort_order'    => $d->sort_order,
                                    'is_active'     => (bool) $d->is_active,
                                ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
                            @endphp
                            <button type="button" class="btn btn-light" onclick='lhEditDept({!! $deptPayload !!})'>
                                {{ translate('Edit') }}
                            </button>
                            <form method="POST" action="{{ route('admin.departments.destroy', ['id' => $d->id]) }}"
                                  onsubmit="return confirm('{{ translate('Delete this department? Members must be reassigned first; otherwise it deactivates.') }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-light" style="color:#C82626;">✕</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="lh-empty">{{ translate('No departments yet — seed should have run on migration.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

</div>

{{-- New department modal --}}
<div class="modal-overlay" id="lh-new-dept" onclick="if(event.target===this) this.classList.remove('open')">
    <form method="POST" action="{{ route('admin.departments.store') }}" class="modal-card">
        @csrf
        <h2>{{ translate('New department') }}</h2>
        <p>{{ translate('Code is the unique identifier (used in API + reports). Lowercase + underscores, e.g. "kitchen_late_shift".') }}</p>

        <label>{{ translate('Name') }}</label>
        <input type="text" name="name" required maxlength="80">

        <div class="form-row" style="margin-top:10px;">
            <div class="col-md-6 form-group">
                <label>{{ translate('Code') }}</label>
                <input type="text" name="code" required maxlength="32" placeholder="kitchen_main">
            </div>
            <div class="col-md-6 form-group">
                <label>{{ translate('Color') }}</label>
                <input type="color" name="color" value="#6A6A70" style="height:38px;">
            </div>
        </div>

        @if($isMaster)
        <label style="display:block; margin-top:10px;">{{ translate('Branch') }} <span style="color:#6A6A70; font-weight:500;">({{ translate('blank = HQ-wide') }})</span></label>
        <select name="branch_id">
            <option value="">— {{ translate('HQ-wide') }} —</option>
            @foreach($branches as $b)
                <option value="{{ $b->id }}">{{ $b->name }}</option>
            @endforeach
        </select>
        @endif

        <label style="display:block; margin-top:10px;">{{ translate('Department head') }}</label>
        <select name="head_admin_id">
            <option value="">— {{ translate('not set') }} —</option>
            @foreach($heads as $h)
                <option value="{{ $h->id }}">
                    {{ trim(($h->f_name ?? '') . ' ' . ($h->l_name ?? '')) }}
                    @if($h->employee_code) · {{ $h->employee_code }}@endif
                    @if($h->designation) · {{ $h->designation }}@endif
                </option>
            @endforeach
        </select>

        <label style="display:block; margin-top:10px;">{{ translate('Sort order') }}</label>
        <input type="number" name="sort_order" value="0" style="width:120px;">

        <label style="display:block; margin-top:10px;">{{ translate('Description') }}</label>
        <textarea name="description" rows="2" maxlength="500"></textarea>

        <div class="actions">
            <button type="button" class="btn btn-light" style="flex:1;" onclick="document.getElementById('lh-new-dept').classList.remove('open')">{{ translate('Cancel') }}</button>
            <button type="submit" class="btn btn-primary" style="flex:1;">{{ translate('Create') }}</button>
        </div>
    </form>
</div>

{{-- Edit department modal (shared, populated via JS) --}}
<div class="modal-overlay" id="lh-edit-dept" onclick="if(event.target===this) this.classList.remove('open')">
    <form method="POST" id="lh-edit-dept-form" class="modal-card">
        @csrf
        <h2>{{ translate('Edit department') }}</h2>

        <label>{{ translate('Name') }}</label>
        <input type="text" name="name" id="ed_name" required maxlength="80">

        <div class="form-row" style="margin-top:10px;">
            <div class="col-md-6 form-group">
                <label>{{ translate('Code') }}</label>
                <input type="text" name="code" id="ed_code" required maxlength="32">
            </div>
            <div class="col-md-6 form-group">
                <label>{{ translate('Color') }}</label>
                <input type="color" name="color" id="ed_color" style="height:38px;">
            </div>
        </div>

        <label style="display:block; margin-top:10px;">{{ translate('Department head') }}</label>
        <select name="head_admin_id" id="ed_head">
            <option value="">— {{ translate('not set') }} —</option>
            @foreach($heads as $h)
                <option value="{{ $h->id }}">
                    {{ trim(($h->f_name ?? '') . ' ' . ($h->l_name ?? '')) }}
                    @if($h->employee_code) · {{ $h->employee_code }}@endif
                </option>
            @endforeach
        </select>

        <div class="form-row" style="margin-top:10px;">
            <div class="col-md-6 form-group">
                <label>{{ translate('Sort order') }}</label>
                <input type="number" name="sort_order" id="ed_sort" value="0">
            </div>
            <div class="col-md-6 form-group" style="display:flex; align-items:flex-end;">
                <label style="display:flex; align-items:center; gap:6px; font-weight:600;">
                    <input type="checkbox" name="is_active" id="ed_active" value="1" style="width:auto; margin:0;">
                    {{ translate('Active') }}
                </label>
            </div>
        </div>

        <label style="display:block; margin-top:10px;">{{ translate('Description') }}</label>
        <textarea name="description" id="ed_desc" rows="2" maxlength="500"></textarea>

        <div class="actions">
            <button type="button" class="btn btn-light" style="flex:1;" onclick="document.getElementById('lh-edit-dept').classList.remove('open')">{{ translate('Cancel') }}</button>
            <button type="submit" class="btn btn-primary" style="flex:1;">{{ translate('Save') }}</button>
        </div>
    </form>
</div>

<script>
function lhEditDept(d) {
    var form = document.getElementById('lh-edit-dept-form');
    form.action = '{{ url('admin/departments') }}/' + d.id;
    document.getElementById('ed_name').value     = d.name || '';
    document.getElementById('ed_code').value     = d.code || '';
    document.getElementById('ed_color').value    = d.color || '#6A6A70';
    document.getElementById('ed_head').value     = d.head_admin_id || '';
    document.getElementById('ed_sort').value     = d.sort_order || 0;
    document.getElementById('ed_active').checked = !!d.is_active;
    document.getElementById('ed_desc').value     = d.description || '';
    document.getElementById('lh-edit-dept').classList.add('open');
}
</script>
@endsection
