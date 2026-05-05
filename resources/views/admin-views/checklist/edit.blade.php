@extends('layouts.admin.app')

@section('title', translate('Edit Work Assignment'))

@push('css_or_js')
    <style>
        .wa-shell { max-width: 1180px; margin: 0 auto; }
        .wa-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; gap: 12px; flex-wrap: wrap; }
        .wa-title { font-size: 22px; font-weight: 700; color: #20140C; margin: 0; }
        .wa-subtitle { font-size: 12px; color: #6c757d; }
        .wa-card { background: #fff; border: 1px solid rgba(0,0,0,.06); border-radius: 14px; padding: 18px; }
        .wa-form .form-label { font-size: 11px; font-weight: 600; color: rgba(32,20,12,.55); margin-bottom: 4px; text-transform: uppercase; letter-spacing: .3px; }
        .wa-form .form-control, .wa-form .form-select { font-size: 14px; }
        .wa-side { background: #FFF7EE; border: 1px solid rgba(232,117,33,.15); border-radius: 14px; padding: 16px; }
        .wa-side .stat { display: flex; align-items: center; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed rgba(0,0,0,.06); }
        .wa-side .stat:last-child { border-bottom: none; }
        .wa-side .stat .v { font-weight: 700; color: #20140C; }
        .wa-section-h { font-size: 14px; font-weight: 700; color: rgba(32,20,12,.6); text-transform: uppercase; letter-spacing: .4px; margin: 24px 0 10px; }
        /* Step rows — compact 56-px row */
        .wa-step-row {
            display: grid;
            grid-template-columns: 28px 1fr auto;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            background: #fff;
            border: 1px solid rgba(0,0,0,.06);
            border-radius: 10px;
            margin-bottom: 6px;
            transition: background .12s, border-color .12s;
        }
        .wa-step-row:hover { background: #FFFBF6; border-color: rgba(232,117,33,.25); }
        .wa-step-row .num {
            background: #FFE7C7; color: #C55E15;
            width: 28px; height: 28px; border-radius: 999px;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 11px;
        }
        .wa-step-row .label { font-weight: 600; color: #20140C; font-size: 14px; }
        .wa-step-row .meta { font-size: 11px; color: #6c757d; margin-top: 2px; }
        .wa-step-row .meta .badge { font-size: 10px; font-weight: 500; }
        .wa-step-row .actions { opacity: .35; transition: opacity .12s; }
        .wa-step-row:hover .actions { opacity: 1; }
        /* Add-row form — single tight inline row, no labels */
        .wa-add { background: #FFFBF6; border: 1px dashed rgba(232,117,33,.4); border-radius: 10px; padding: 10px; margin-bottom: 14px; }
        .wa-add .inputs { display: grid; grid-template-columns: 1fr 160px 160px 90px 50px 50px 60px; gap: 6px; }
        .wa-add input, .wa-add select { font-size: 13px; }
        .wa-add .switch-wrap { display: flex; align-items: center; justify-content: center; }
        @media (max-width: 992px) {
            .wa-add .inputs { grid-template-columns: 1fr 1fr; }
        }
        /* Empty state */
        .wa-empty {
            text-align: center; padding: 40px 20px; color: #6c757d;
            background: #fff; border: 2px dashed rgba(0,0,0,.08); border-radius: 12px;
        }
        .wa-empty .ic { font-size: 36px; color: #D8D8D8; }
        /* Sticky save bar */
        .wa-save-bar {
            position: sticky; top: 0; z-index: 4; background: rgba(255,247,238,.95);
            backdrop-filter: blur(6px);
            padding: 10px 0; margin-bottom: 12px;
            display: flex; align-items: center; justify-content: space-between;
        }
    </style>
@endpush

@section('content')
    <div class="content container-fluid wa-shell">
        @php
            $stepCount = $template->items->count();
            $photoCount = $template->items->where('requires_photo', true)->count();
            $scheduledCount = $template->items->whereNotNull('scheduled_time')->count();
            $assignedCount = $template->items->filter(fn ($i) => $i->assigned_admin_id || $i->assigned_designation_id)->count();
        @endphp

        <form action="{{ route('admin.checklist.update', [$template->id]) }}" method="post" id="wa-form">
            <div class="wa-save-bar">
                <div>
                    <h1 class="wa-title">
                        <i class="tio-edit"></i> {{ $template->name }}
                        <span class="badge ms-1" style="background:#FFE7C7;color:#C55E15;font-size:11px;font-weight:700;letter-spacing:.3px;">{{ ucfirst($template->kind) }}</span>
                    </h1>
                    <div class="wa-subtitle">{{ translate('Work Assignment') }} · {{ $stepCount }} {{ $stepCount === 1 ? translate('step') : translate('steps') }}</div>
                </div>
                <div class="d-flex gap-2">
                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.checklist.list') }}">{{ translate('Back') }}</a>
                    <button type="submit" class="btn btn-primary btn-sm" form="wa-form"><i class="tio-save"></i> {{ translate('Save') }}</button>
                </div>
            </div>

            <div class="row g-3">
                {{-- Left: form (8 cols) --}}
                <div class="col-lg-8">
                    <div class="wa-card">
                        @include('admin-views.checklist._form')
                    </div>
                </div>
                {{-- Right: stats sidebar (4 cols) --}}
                <div class="col-lg-4">
                    <div class="wa-side">
                        <div class="wa-section-h" style="margin-top:0">{{ translate('Quick stats') }}</div>
                        <div class="stat"><span>{{ translate('Total steps') }}</span><span class="v">{{ $stepCount }}</span></div>
                        <div class="stat"><span><i class="tio-camera"></i> {{ translate('Photo required') }}</span><span class="v">{{ $photoCount }}</span></div>
                        <div class="stat"><span><i class="tio-time"></i> {{ translate('Scheduled time') }}</span><span class="v">{{ $scheduledCount }}</span></div>
                        <div class="stat"><span><i class="tio-user"></i> {{ translate('Assigned') }}</span><span class="v">{{ $assignedCount }}</span></div>
                        <div class="stat"><span>{{ translate('Last edited') }}</span><span class="v" style="font-weight:500;font-size:12px;">{{ $template->updated_at?->diffForHumans() ?? '—' }}</span></div>
                    </div>
                </div>
            </div>
        </form>

        <h2 class="wa-section-h">{{ translate('Steps') }}</h2>

        {{-- Inline add-row. No labels, placeholders inside, single row. --}}
        <form action="{{ route('admin.checklist.add-item', [$template->id]) }}" method="post" class="wa-add">
            @csrf
            <div class="inputs">
                <input type="text" name="label" class="form-control" placeholder="{{ translate('What needs to be done…') }}" maxlength="200" required>
                <select name="assigned_designation_id" class="form-select" title="{{ translate('Role') }}">
                    <option value="">— {{ translate('Any role') }} —</option>
                    @foreach($designations as $d)
                        <option value="{{ $d->id }}">{{ $d->name }}</option>
                    @endforeach
                </select>
                <select name="assigned_admin_id" class="form-select" title="{{ translate('Specific person') }}">
                    <option value="">— {{ translate('Anyone') }} —</option>
                    @foreach($staff as $s)
                        <option value="{{ $s->id }}">{{ trim(($s->f_name ?? '') . ' ' . ($s->l_name ?? '')) ?: ('#' . $s->id) }}</option>
                    @endforeach
                </select>
                <input type="time" name="scheduled_time" class="form-control" title="{{ translate('Time') }}">
                <div class="switch-wrap" title="{{ translate('Required') }}">
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" name="is_required" value="1" checked>
                    </div>
                </div>
                <div class="switch-wrap" title="{{ translate('Photo required') }}">
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" name="requires_photo" value="1" checked>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary"><i class="tio-add"></i></button>
            </div>
        </form>

        @if($template->items->isEmpty())
            <div class="wa-empty">
                <div class="ic"><i class="tio-add-circle"></i></div>
                <p class="mt-2 mb-0">{{ translate('No steps yet — add the first one above.') }}</p>
            </div>
        @else
            @foreach($template->items as $i => $item)
                <div class="wa-step-row">
                    <div class="num">{{ $i + 1 }}</div>
                    <div>
                        <div class="label">{{ $item->label }}</div>
                        <div class="meta">
                            @if($item->assignedAdmin)
                                <span class="badge" style="background:#E5E0FF;color:#4836A8"><i class="tio-user"></i> {{ trim(($item->assignedAdmin->f_name ?? '') . ' ' . ($item->assignedAdmin->l_name ?? '')) }}</span>
                            @elseif($item->assignedDesignation)
                                <span class="badge" style="background:#DFE7FF;color:#3A6FD5"><i class="tio-user"></i> {{ $item->assignedDesignation->name }}</span>
                            @else
                                <span class="badge bg-light text-muted">{{ translate('Anyone') }}</span>
                            @endif
                            @if($item->scheduled_time)
                                <span class="badge ms-1" style="background:#FFE7C7;color:#C55E15"><i class="tio-time"></i> {{ \Illuminate\Support\Carbon::parse($item->scheduled_time)->format('H:i') }}</span>
                            @endif
                            @if($item->requires_photo)
                                <span class="badge ms-1" style="background:#E0F4E2;color:#1E8E3E"><i class="tio-camera"></i> {{ translate('Photo') }}</span>
                            @endif
                            @if(! $item->is_required)
                                <span class="badge ms-1 bg-light text-muted">{{ translate('Optional') }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="actions">
                        <form action="{{ route('admin.checklist.remove-item', [$template->id, $item->id]) }}" method="post" onsubmit="return confirm('Remove this step?');" class="m-0">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" title="{{ translate('Remove') }}"><i class="tio-delete"></i></button>
                        </form>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
@endsection
