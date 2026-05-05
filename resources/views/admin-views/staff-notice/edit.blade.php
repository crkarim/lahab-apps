@extends('layouts.admin.app')

@section('title', translate('Edit Staff Notice'))

@push('css_or_js')
    <style>
        .sn-shell { max-width: 1180px; margin: 0 auto; }
        .sn-title { font-size: 22px; font-weight: 700; color: #20140C; margin: 0; }
        .sn-subtitle { font-size: 12px; color: #6c757d; }
        .sn-card { background: #fff; border: 1px solid rgba(0,0,0,.06); border-radius: 14px; padding: 18px; }
        .sn-form .form-label { font-size: 10px; font-weight: 600; color: rgba(32,20,12,.55); margin-bottom: 3px; text-transform: uppercase; letter-spacing: .3px; }
        .sn-form .form-control, .sn-form .form-select { font-size: 13px; }
        .sn-form .form-control-sm { padding: 6px 10px; height: 34px; }
        .sn-form textarea { resize: vertical; min-height: 78px; }
        /* Pin pill — checkbox styled as a toggle chip. */
        .sn-pill {
            display: flex; align-items: center; justify-content: center;
            width: 100%; height: 34px; cursor: pointer;
            border: 1px solid rgba(0,0,0,.1);
            background: #fff;
            border-radius: 8px;
            font-size: 12px; font-weight: 600; color: #6c757d;
            user-select: none;
            transition: background .12s, border-color .12s, color .12s;
        }
        .sn-pill:hover { border-color: rgba(232,117,33,.3); }
        .sn-pill input { display: none; }
        .sn-pill input:checked + span { color: #856404; }
        .sn-pill:has(input:checked) { background: #FFF3CD; border-color: rgba(255,193,7,.5); }
        .sn-side { background: #FFF7EE; border: 1px solid rgba(232,117,33,.15); border-radius: 14px; padding: 16px; }
        .sn-side .sec { font-size: 11px; font-weight: 700; color: rgba(32,20,12,.6); text-transform: uppercase; letter-spacing: .4px; margin-bottom: 8px; }
        .sn-side .stat { display: flex; align-items: center; justify-content: space-between; padding: 6px 0; border-bottom: 1px dashed rgba(0,0,0,.06); font-size: 13px; }
        .sn-side .stat:last-child { border-bottom: none; }
        .sn-side .stat .v { font-weight: 700; color: #20140C; }
        /* Live phone preview */
        .sn-preview {
            background: #fff; border-radius: 14px; padding: 12px;
            border: 1px solid rgba(0,0,0,.06);
            box-shadow: 0 8px 22px rgba(0,0,0,.06);
        }
        .sn-preview .ph-card {
            border: 1px solid rgba(0,0,0,.06);
            border-radius: 12px; padding: 10px;
            display: flex; gap: 10px; align-items: flex-start;
        }
        .sn-preview .ph-pin { border-color: rgba(255,193,7,.5); }
        .sn-preview .ph-icon {
            width: 32px; height: 32px; border-radius: 999px;
            background: rgba(232,117,33,.15);
            display: flex; align-items: center; justify-content: center;
            color: #C55E15; flex-shrink: 0;
        }
        .sn-preview .ph-title { font-weight: 700; color: #20140C; font-size: 13px; line-height: 1.2; }
        .sn-preview .ph-body { color: rgba(32,20,12,.6); font-size: 12px; margin-top: 3px;
                               display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .sn-push-row {
            background: #FFFBF6; border: 1px solid rgba(232,117,33,.2);
            border-radius: 10px; padding: 10px 12px;
            display: flex; flex-wrap: wrap; align-items: baseline; gap: 12px;
        }
        .sn-push-row .form-check-label { font-size: 13px; }
        .sn-save-bar {
            position: sticky; top: 0; z-index: 4; background: rgba(255,247,238,.95);
            backdrop-filter: blur(6px);
            padding: 10px 0; margin-bottom: 12px;
            display: flex; align-items: center; justify-content: space-between; gap: 10px;
        }
    </style>
@endpush

@section('content')
    @php
        $readCount = $notice->reads()->count();
        $totalAdmins = (int) \Illuminate\Support\Facades\DB::table('admins')
            ->where('status', 1)
            ->where('app_login_enabled', 1)
            ->count();
        $readPct = $totalAdmins > 0 ? round(100 * $readCount / $totalAdmins) : 0;
    @endphp

    <div class="content container-fluid sn-shell">
        <form action="{{ route('admin.staff-notice.update', [$notice->id]) }}" method="post" enctype="multipart/form-data" id="sn-form">
            <div class="sn-save-bar">
                <div>
                    <h1 class="sn-title">
                        <i class="tio-edit"></i> {{ translate('Staff Notice') }} #{{ $notice->id }}
                        @if(!empty($notice->is_pinned))
                            <span class="badge ms-1" style="background:#FFF3CD;color:#856404;font-size:11px;font-weight:700;letter-spacing:.3px;"><i class="tio-pin"></i> {{ translate('Pinned') }}</span>
                        @endif
                    </h1>
                    <div class="sn-subtitle">
                        @if($notice->branch_id)
                            <i class="tio-store"></i> {{ $branches->firstWhere('id', $notice->branch_id)?->name ?? '#' . $notice->branch_id }}
                        @else
                            <i class="tio-globe"></i> {{ translate('All branches') }}
                        @endif
                        · {{ translate('Updated') }} {{ $notice->updated_at?->diffForHumans() ?? '—' }}
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.staff-notice.list') }}">{{ translate('Back') }}</a>
                    <button type="submit" class="btn btn-primary btn-sm" form="sn-form"><i class="tio-save"></i> {{ translate('Save') }}</button>
                </div>
            </div>

            <div class="row g-3">
                {{-- Left: form (8 cols) --}}
                <div class="col-lg-8">
                    <div class="sn-card">
                        @include('admin-views.staff-notice._form')
                    </div>
                </div>
                {{-- Right: preview + stats sidebar (4 cols) --}}
                <div class="col-lg-4">
                    <div class="sn-preview mb-3">
                        <div class="sn-subtitle mb-2"><i class="tio-mobile"></i> {{ translate('How staff will see it') }}</div>
                        <div class="ph-card {{ !empty($notice->is_pinned) ? 'ph-pin' : '' }}">
                            <div class="ph-icon">
                                <i class="{{ !empty($notice->is_pinned) ? 'tio-pin' : 'tio-notifications-on' }}"></i>
                            </div>
                            <div style="flex:1; min-width: 0;">
                                <div class="ph-title">{{ $notice->title ?: '—' }}</div>
                                <div class="ph-body">{{ $notice->body ?: '—' }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="sn-side">
                        <div class="sec">{{ translate('Reach') }}</div>
                        <div class="stat"><span>{{ translate('Read by staff') }}</span><span class="v">{{ $readCount }} / {{ $totalAdmins }}</span></div>
                        <div class="stat"><span>{{ translate('Read rate') }}</span><span class="v">{{ $readPct }}%</span></div>
                        <div class="stat"><span>{{ translate('Published') }}</span><span class="v" style="font-weight:500;font-size:12px;">{{ $notice->published_at?->format('Y-m-d H:i') ?? '—' }}</span></div>
                        <div class="stat"><span>{{ translate('Expires') }}</span><span class="v" style="font-weight:500;font-size:12px;">{{ $notice->expires_at?->format('Y-m-d H:i') ?? translate('Never') }}</span></div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection
