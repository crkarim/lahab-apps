@extends('layouts.admin.app')

@section('title', translate('Designations'))

@section('content')
@include('admin-views._partials.hrm_settings_nav', ['active' => 'designations'])
@php
    $sym = fn ($v) => \App\CentralLogics\Helpers::set_symbol($v);
@endphp

<style>
    .lh-ds-page { max-width: 1200px; margin: 0 auto; }
    .lh-ds-hero {
        background: linear-gradient(135deg, #fff 0%, #f0f7ff 100%);
        border: 1px solid #d6e3f7; border-radius: 16px;
        padding: 22px 26px; margin-bottom: 18px;
        display: flex; gap: 18px; align-items: center; flex-wrap: wrap;
    }
    .lh-ds-hero .icon {
        width: 56px; height: 56px; border-radius: 50%;
        background: rgba(71, 148, 255, 0.14); color: #4794FF;
        display: flex; align-items: center; justify-content: center;
        font-size: 26px; flex-shrink: 0;
    }
    .lh-ds-hero h1 { margin: 0; font-size: 20px; font-weight: 800; color: #1A1A1A; }
    .lh-ds-hero p  { margin: 2px 0 0; color: #6A6A70; font-size: 13px; max-width: 700px; }
    .lh-ds-hero .actions { margin-left: auto; }

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
        display: inline-block; padding: 2px 8px;
        font-size: 10px; font-weight: 700; color: #fff;
        border-radius: 999px; letter-spacing: .5px;
    }
    .lh-table .grade-pill {
        display: inline-block; padding: 1px 6px;
        background: #FFF4E5; color: #E67E22;
        border-radius: 3px; font-family: monospace;
        font-size: 11px; font-weight: 800;
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

<div class="lh-ds-page">

    @if(session('error'))<div class="alert alert-soft-danger">{{ session('error') }}</div>@endif
    @if(session('success'))<div class="alert alert-soft-success">{{ session('success') }}</div>@endif

    <div class="lh-ds-hero">
        <div class="icon">🎖️</div>
        <div>
            <h1>{{ translate('Designations') }}</h1>
            <p>{{ translate('Job titles + pay-grade hints. Default basic is a memo, not authoritative — actual salary lives on the employee record. Seeded with BD restaurant defaults.') }}</p>
        </div>
        <div class="actions">
            <button type="button" class="btn btn-primary" onclick="document.getElementById('lh-new-des').classList.add('open')">
                + {{ translate('New designation') }}
            </button>
        </div>
    </div>

    <div class="lh-card">
        <h3>{{ translate('All designations') }} <small style="font-weight:600; color:#1A1A1A; margin-left:6px;">{{ $designations->count() }}</small></h3>
        <table class="lh-table">
            <thead>
                <tr>
                    <th>{{ translate('Title') }}</th>
                    <th>{{ translate('Code') }}</th>
                    <th>{{ translate('Department') }}</th>
                    <th>{{ translate('Grade') }}</th>
                    <th class="num">{{ translate('Default basic') }}</th>
                    <th class="num">{{ translate('Members') }}</th>
                    <th>{{ translate('Status') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($designations as $d)
                <tr>
                    <td>
                        <strong>{{ $d->name }}</strong>
                        @if($d->notes)
                            <div style="font-size:11px; color:#6A6A70; margin-top:2px; max-width:280px;">{{ $d->notes }}</div>
                        @endif
                    </td>
                    <td><span class="code-mono">{{ $d->code }}</span></td>
                    <td>
                        @if($d->department)
                            <span class="dept-pill" style="background: {{ $d->department->color }};">{{ $d->department->name }}</span>
                        @else
                            <span style="color:#6A6A70; font-size:11px;">— {{ translate('any') }} —</span>
                        @endif
                    </td>
                    <td>@if($d->grade)<span class="grade-pill">{{ $d->grade }}</span>@else<span style="color:#6A6A70;">—</span>@endif</td>
                    <td class="num">{{ $d->default_basic ? $sym($d->default_basic) : '—' }}</td>
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
                                $desPayload = json_encode([
                                    'id'            => $d->id,
                                    'name'          => $d->name,
                                    'code'          => $d->code,
                                    'department_id' => $d->department_id,
                                    'default_basic' => $d->default_basic,
                                    'grade'         => $d->grade,
                                    'notes'         => $d->notes,
                                    'sort_order'    => $d->sort_order,
                                    'is_active'     => (bool) $d->is_active,
                                ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
                            @endphp
                            <button type="button" class="btn btn-light" onclick='lhEditDes({!! $desPayload !!})'>
                                {{ translate('Edit') }}
                            </button>
                            <form method="POST" action="{{ route('admin.designations.destroy', ['id' => $d->id]) }}"
                                  onsubmit="return confirm('{{ translate('Delete this designation? Members must be reassigned first; otherwise it deactivates.') }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-light" style="color:#C82626;">✕</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="lh-empty">{{ translate('No designations yet — seed should have run on migration.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

</div>

{{-- New designation modal --}}
<div class="modal-overlay" id="lh-new-des" onclick="if(event.target===this) this.classList.remove('open')">
    <form method="POST" action="{{ route('admin.designations.store') }}" class="modal-card">
        @csrf
        <h2>{{ translate('New designation') }}</h2>
        <p>{{ translate('Job title + optional default pay grade. The "default basic" is shown as a memo when this title is picked on the employee form, never overrides actual salary lines.') }}</p>

        <label>{{ translate('Title') }}</label>
        <input type="text" name="name" required maxlength="80" placeholder="e.g. Senior Captain">

        <div class="form-row" style="margin-top:10px;">
            <div class="col-md-6 form-group">
                <label>{{ translate('Code') }}</label>
                <input type="text" name="code" required maxlength="32" placeholder="senior_captain">
            </div>
            <div class="col-md-6 form-group">
                <label>{{ translate('Grade') }}</label>
                <input type="text" name="grade" maxlength="16" placeholder="A1, B2 ...">
            </div>
        </div>

        <label style="display:block; margin-top:10px;">{{ translate('Department') }} <span style="color:#6A6A70; font-weight:500;">({{ translate('optional') }})</span></label>
        <select name="department_id">
            <option value="">— {{ translate('any department') }} —</option>
            @foreach($departments as $dp)
                <option value="{{ $dp->id }}">{{ $dp->name }}</option>
            @endforeach
        </select>

        <div class="form-row" style="margin-top:10px;">
            <div class="col-md-6 form-group">
                <label>{{ translate('Default basic (Tk)') }}</label>
                <input type="number" name="default_basic" step="0.01" min="0" placeholder="18000">
            </div>
            <div class="col-md-6 form-group">
                <label>{{ translate('Sort order') }}</label>
                <input type="number" name="sort_order" value="0">
            </div>
        </div>

        <label style="display:block; margin-top:10px;">{{ translate('Notes') }}</label>
        <textarea name="notes" rows="2" maxlength="500" placeholder="{{ translate('Internal note — what this title does, scope, etc.') }}"></textarea>

        <div class="actions">
            <button type="button" class="btn btn-light" style="flex:1;" onclick="document.getElementById('lh-new-des').classList.remove('open')">{{ translate('Cancel') }}</button>
            <button type="submit" class="btn btn-primary" style="flex:1;">{{ translate('Create') }}</button>
        </div>
    </form>
</div>

{{-- Edit designation modal --}}
<div class="modal-overlay" id="lh-edit-des" onclick="if(event.target===this) this.classList.remove('open')">
    <form method="POST" id="lh-edit-des-form" class="modal-card">
        @csrf
        <h2>{{ translate('Edit designation') }}</h2>

        <label>{{ translate('Title') }}</label>
        <input type="text" name="name" id="es_name" required maxlength="80">

        <div class="form-row" style="margin-top:10px;">
            <div class="col-md-6 form-group">
                <label>{{ translate('Code') }}</label>
                <input type="text" name="code" id="es_code" required maxlength="32">
            </div>
            <div class="col-md-6 form-group">
                <label>{{ translate('Grade') }}</label>
                <input type="text" name="grade" id="es_grade" maxlength="16">
            </div>
        </div>

        <label style="display:block; margin-top:10px;">{{ translate('Department') }}</label>
        <select name="department_id" id="es_dept">
            <option value="">— {{ translate('any department') }} —</option>
            @foreach($departments as $dp)
                <option value="{{ $dp->id }}">{{ $dp->name }}</option>
            @endforeach
        </select>

        <div class="form-row" style="margin-top:10px;">
            <div class="col-md-6 form-group">
                <label>{{ translate('Default basic (Tk)') }}</label>
                <input type="number" name="default_basic" id="es_basic" step="0.01" min="0">
            </div>
            <div class="col-md-6 form-group">
                <label>{{ translate('Sort order') }}</label>
                <input type="number" name="sort_order" id="es_sort">
            </div>
        </div>

        <label style="display:block; margin-top:10px;">
            <input type="checkbox" name="is_active" id="es_active" value="1" style="width:auto; margin-right:6px;">
            {{ translate('Active') }}
        </label>

        <label style="display:block; margin-top:10px;">{{ translate('Notes') }}</label>
        <textarea name="notes" id="es_notes" rows="2" maxlength="500"></textarea>

        <div class="actions">
            <button type="button" class="btn btn-light" style="flex:1;" onclick="document.getElementById('lh-edit-des').classList.remove('open')">{{ translate('Cancel') }}</button>
            <button type="submit" class="btn btn-primary" style="flex:1;">{{ translate('Save') }}</button>
        </div>
    </form>
</div>

<script>
function lhEditDes(d) {
    var form = document.getElementById('lh-edit-des-form');
    form.action = '{{ url('admin/designations') }}/' + d.id;
    document.getElementById('es_name').value    = d.name || '';
    document.getElementById('es_code').value    = d.code || '';
    document.getElementById('es_grade').value   = d.grade || '';
    document.getElementById('es_dept').value    = d.department_id || '';
    document.getElementById('es_basic').value   = d.default_basic || '';
    document.getElementById('es_sort').value    = d.sort_order || 0;
    document.getElementById('es_active').checked = !!d.is_active;
    document.getElementById('es_notes').value   = d.notes || '';
    document.getElementById('lh-edit-des').classList.add('open');
}
</script>
@endsection
