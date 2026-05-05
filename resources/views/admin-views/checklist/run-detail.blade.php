@extends('layouts.admin.app')

@section('title', translate('Work Assignment Run'))

@push('css_or_js')
    <style>
        .wa-shell { max-width: 1180px; margin: 0 auto; }
        .wa-strip {
            background: #fff; border: 1px solid rgba(0,0,0,.06); border-radius: 14px;
            padding: 14px 18px; margin-bottom: 16px;
            display: grid; grid-template-columns: 1fr auto; gap: 16px; align-items: center;
        }
        .wa-strip .ttl { font-size: 18px; font-weight: 800; color: #20140C; margin: 0; }
        .wa-strip .meta { font-size: 12px; color: #6c757d; margin-top: 2px; }
        .wa-strip .progress-wrap { width: 220px; }
        .wa-strip .progress { height: 8px; }
        .wa-strip .pct { font-size: 22px; font-weight: 800; line-height: 1; }
        .wa-strip .badge-pill {
            font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 999px;
            display: inline-block;
        }
        /* Step row — compact 64-px row, photo on left for fast scan */
        .wa-step {
            display: grid;
            grid-template-columns: 28px 60px 1fr auto;
            gap: 14px; align-items: center;
            background: #fff; border: 1px solid rgba(0,0,0,.06); border-radius: 12px;
            padding: 10px 14px; margin-bottom: 6px;
        }
        .wa-step:hover { background: #FFFBF6; }
        .wa-tick {
            width: 28px; height: 28px; border-radius: 999px;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700;
        }
        .wa-tick.done { background: #1E8E3E; color: #fff; }
        .wa-tick.pending { background: #fff; border: 2px solid #D8D8D8; color: #D8D8D8; }
        .wa-photo {
            width: 60px; height: 60px; border-radius: 8px; object-fit: cover;
            cursor: zoom-in; background: #F2F2F2;
        }
        .wa-photo-empty {
            width: 60px; height: 60px; border-radius: 8px; background: #F8F8F8;
            display: flex; align-items: center; justify-content: center;
            color: #C9C9C9; font-size: 22px;
        }
        .wa-step .label { font-weight: 600; color: #20140C; font-size: 14px; line-height: 1.2; }
        .wa-step .sub { font-size: 11px; color: #6c757d; margin-top: 4px; }
        .wa-step .sub .badge { font-weight: 500; font-size: 10px; }
        .wa-section-h { font-size: 14px; font-weight: 700; color: rgba(32,20,12,.6); text-transform: uppercase; letter-spacing: .4px; margin: 20px 0 10px; }
        .wa-foot { font-size: 11px; color: #6c757d; margin-top: 14px; }
        /* Lightbox */
        .wa-lb {
            position: fixed; inset: 0; background: rgba(0,0,0,.85);
            display: none; align-items: center; justify-content: center;
            z-index: 9999; padding: 24px;
        }
        .wa-lb.show { display: flex; }
        .wa-lb img { max-width: 100%; max-height: 100%; border-radius: 8px; }
        .wa-lb .x { position: absolute; top: 16px; right: 24px; color: #fff; font-size: 32px; cursor: pointer; }
    </style>
@endpush

@section('content')
    @php
        $checked = $run->items->whereNotNull('checked_at')->count();
        $required = $run->items->where('is_required', true)->count();
        $reqLeft = $run->items->where('is_required', true)->whereNull('checked_at')->count();
        $progress = $run->items->count() === 0 ? 0 : round(100 * $checked / $run->items->count());
        $statusColor = $run->completed_at ? '#1E8E3E' : '#C55E15';
        $statusLabel = $run->completed_at ? translate('Completed') . ' ' . optional($run->completed_at)->format('H:i') : translate('In progress');
    @endphp

    <div class="content container-fluid wa-shell">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div></div>
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.checklist.runs') }}">{{ translate('Back') }}</a>
        </div>

        {{-- Compact strip header --}}
        <div class="wa-strip">
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <h1 class="ttl">{{ $run->template?->name ?? '—' }}</h1>
                    <span class="badge-pill" style="background:#FFE7C7;color:#C55E15">{{ ucfirst($run->template?->kind ?? '') }}</span>
                    <span class="badge-pill" style="background:rgba({{ $run->completed_at ? '30,142,62' : '197,94,21' }},.13);color:{{ $statusColor }}">
                        {{ $statusLabel }}
                    </span>
                </div>
                <div class="meta">
                    <i class="tio-store"></i> {{ $run->branch?->name ?? translate('Global') }}
                    · <i class="tio-date-range"></i> {{ optional($run->run_date)->format('Y-m-d') }}
                    · <i class="tio-user"></i> {{ trim(($run->startedBy?->f_name ?? '') . ' ' . ($run->startedBy?->l_name ?? '')) }}
                    {{ optional($run->started_at)->format('H:i') }}
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="progress-wrap">
                    <div class="progress mb-1">
                        <div class="progress-bar" style="width: {{ $progress }}%; background: {{ $statusColor }}"></div>
                    </div>
                    <div class="d-flex justify-content-between" style="font-size:11px;color:#6c757d">
                        <span><strong>{{ $checked }}</strong> / {{ $run->items->count() }}</span>
                        <span>{{ $reqLeft }} {{ translate('left') }}</span>
                    </div>
                </div>
                <div class="text-end">
                    <div class="pct" style="color:{{ $statusColor }}">{{ $progress }}%</div>
                    <div style="font-size:11px;color:#6c757d">{{ translate('done') }}</div>
                </div>
            </div>
        </div>

        <h2 class="wa-section-h">{{ translate('Steps') }}</h2>
        @foreach($run->items as $i)
            <div class="wa-step">
                <div class="wa-tick {{ $i->checked_at ? 'done' : 'pending' }}">
                    @if($i->checked_at)<i class="tio-checkmark"></i>@else<i class="tio-time"></i>@endif
                </div>
                @if($i->photo_path)
                    <img class="wa-photo"
                         src="{{ asset('storage/app/public/' . $i->photo_path) }}"
                         alt="proof"
                         onclick="lahabLightbox(this.src)">
                @else
                    <div class="wa-photo-empty"><i class="tio-camera"></i></div>
                @endif
                <div>
                    <div class="label">{{ $i->label_snapshot }}</div>
                    <div class="sub">
                        @if($i->checked_at)
                            <i class="tio-checkmark text-success"></i>
                            {{ trim(($i->checkedBy?->f_name ?? '') . ' ' . ($i->checkedBy?->l_name ?? '')) ?: '—' }}
                            · {{ optional($i->checked_at)->format('H:i') }}
                        @else
                            <i class="tio-time"></i> {{ translate('Pending') }}
                        @endif
                        @if($i->assigned_admin_name)
                            · <span class="badge" style="background:#E5E0FF;color:#4836A8"><i class="tio-user"></i> {{ $i->assigned_admin_name }}</span>
                        @elseif($i->assigned_designation_name)
                            · <span class="badge" style="background:#DFE7FF;color:#3A6FD5"><i class="tio-user"></i> {{ $i->assigned_designation_name }}</span>
                        @endif
                        @if($i->scheduled_time)
                            · <span class="badge" style="background:#FFE7C7;color:#C55E15"><i class="tio-time"></i> {{ \Illuminate\Support\Carbon::parse($i->scheduled_time)->format('H:i') }}</span>
                        @endif
                        @if(! $i->is_required)
                            · <span class="badge bg-light text-muted">{{ translate('Optional') }}</span>
                        @endif
                    </div>
                    @if($i->note)
                        <div class="sub" style="color:#444;margin-top:4px">"{{ $i->note }}"</div>
                    @endif
                </div>
                <div></div>
            </div>
        @endforeach

        <div class="wa-foot">
            <i class="tio-info"></i>
            {{ translate('Photos auto-delete 24 h after each step is checked. Audit trail (who, when, note) is kept permanently.') }}
        </div>
    </div>

    <div class="wa-lb" id="lahab-lightbox" onclick="this.classList.remove('show')">
        <span class="x">&times;</span>
        <img id="lahab-lightbox-img" src="" alt="">
    </div>

    <script>
        function lahabLightbox(src) {
            document.getElementById('lahab-lightbox-img').src = src;
            document.getElementById('lahab-lightbox').classList.add('show');
        }
    </script>
@endsection
