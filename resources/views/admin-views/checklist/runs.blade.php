@extends('layouts.admin.app')

@section('title', translate('Work Assignment Runs'))

@push('css_or_js')
    <style>
        .wa-shell { max-width: 1180px; margin: 0 auto; }
        .wa-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 14px; gap: 12px; flex-wrap: wrap; }
        .wa-title { font-size: 22px; font-weight: 700; color: #20140C; margin: 0; }
        .wa-subtitle { font-size: 12px; color: #6c757d; }

        .wa-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 14px; }
        .wa-stat {
            background: #fff; border: 1px solid rgba(0,0,0,.06); border-radius: 12px;
            padding: 12px 14px; display: flex; align-items: center; gap: 12px;
        }
        .wa-stat .ic { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
        .wa-stat .v { font-size: 22px; font-weight: 800; color: #20140C; line-height: 1; }
        .wa-stat .l { font-size: 11px; color: #6c757d; text-transform: uppercase; letter-spacing: .3px; margin-top: 2px; }

        .wa-filterbar {
            background: #fff; border: 1px solid rgba(0,0,0,.06); border-radius: 12px;
            padding: 10px 12px; display: flex; gap: 8px; align-items: center; flex-wrap: wrap;
            margin-bottom: 12px;
        }
        .wa-filterbar .form-control, .wa-filterbar .form-select {
            font-size: 13px; height: 32px; padding: 4px 10px;
        }
        .wa-filterbar .pill {
            padding: 5px 12px; border-radius: 999px; font-size: 12px; font-weight: 600;
            text-decoration: none; border: 1px solid rgba(0,0,0,.08);
            color: #20140C; background: #fff;
        }
        .wa-filterbar .pill.active { background: #E87521; color: #fff; border-color: #E87521; }

        /* Run row */
        .wa-row {
            background: #fff; border: 1px solid rgba(0,0,0,.06); border-radius: 12px;
            padding: 12px 14px; margin-bottom: 8px;
            display: grid; grid-template-columns: 80px 1fr 220px 100px 28px;
            gap: 14px; align-items: center;
            text-decoration: none; color: inherit;
            transition: box-shadow .15s, border-color .15s;
        }
        .wa-row:hover { box-shadow: 0 6px 16px rgba(0,0,0,.06); border-color: rgba(232,117,33,.3); color: inherit; text-decoration: none; }
        .wa-row .date { font-size: 12px; font-weight: 600; color: #20140C; }
        .wa-row .date small { display: block; color: #6c757d; font-weight: 500; font-size: 11px; }
        .wa-row .name { font-size: 14px; font-weight: 700; color: #20140C; }
        .wa-row .meta { font-size: 11px; color: #6c757d; margin-top: 3px; }
        .wa-row .meta .badge { font-weight: 500; font-size: 10px; }
        .wa-row .progress-cell .progress { height: 6px; }
        .wa-row .progress-cell .pct { font-size: 11px; color: #6c757d; margin-top: 3px; display: flex; justify-content: space-between; }
        .wa-row .status {
            font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 999px;
            white-space: nowrap;
        }
        .wa-row .chev { color: rgba(0,0,0,.25); }

        .kind-pill { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: 9px; font-weight: 700; letter-spacing: .3px; text-transform: uppercase; }
        .kind-open { background: #FFE7C7; color: #C55E15; }
        .kind-daily { background: #DFE7FF; color: #3A6FD5; }
        .kind-close { background: #FFD7D8; color: #B42525; }
        .kind-weekly { background: #D8F4DC; color: #1E8E3E; }

        .avatar-stack { display: inline-flex; align-items: center; }
        .avatar-stack .av {
            width: 22px; height: 22px; border-radius: 999px;
            background: #E5E0FF; color: #4836A8;
            font-size: 10px; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            border: 2px solid #fff; margin-left: -6px;
        }
        .avatar-stack .av:first-child { margin-left: 0; }

        .wa-empty {
            background: #fff; border: 2px dashed rgba(0,0,0,.1);
            border-radius: 14px; padding: 48px 24px; text-align: center; color: #6c757d;
        }
    </style>
@endpush

@section('content')
    <div class="content container-fluid wa-shell">
        <div class="wa-header">
            <div>
                <h1 class="wa-title"><i class="tio-time"></i> {{ translate('Work Assignment History') }}</h1>
                <div class="wa-subtitle">{{ translate('Every started run, with progress + photos. Click a row for full audit detail.') }}</div>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.checklist.list') }}">{{ translate('Back to assignments') }}</a>
        </div>

        {{-- Stats strip --}}
        <div class="wa-stats">
            <div class="wa-stat">
                <div class="ic" style="background:#DFE7FF;color:#3A6FD5"><i class="tio-play-outlined"></i></div>
                <div><div class="v">{{ $stats['started_today'] }}</div><div class="l">{{ translate('Started today') }}</div></div>
            </div>
            <div class="wa-stat">
                <div class="ic" style="background:#D8F4DC;color:#1E8E3E"><i class="tio-checkmark-circle"></i></div>
                <div><div class="v">{{ $stats['completed_today'] }}</div><div class="l">{{ translate('Completed today') }}</div></div>
            </div>
            <div class="wa-stat">
                <div class="ic" style="background:#FFE7C7;color:#C55E15"><i class="tio-time"></i></div>
                <div><div class="v">{{ $stats['in_progress'] }}</div><div class="l">{{ translate('Still in progress') }}</div></div>
            </div>
        </div>

        {{-- Filter bar --}}
        <form method="get" class="wa-filterbar">
            @foreach(['' => translate('All'), 'in_progress' => translate('In progress'), 'completed' => translate('Completed')] as $k => $label)
                @php
                    $params = [];
                    if ($k !== '') $params['status'] = $k;
                    if ($branchId) $params['branch_id'] = $branchId;
                    if ($date) $params['date'] = $date;
                    if ($search !== '') $params['q'] = $search;
                @endphp
                <a class="pill {{ $status === $k ? 'active' : '' }}"
                   href="{{ route('admin.checklist.runs', $params) }}">{{ $label }}</a>
            @endforeach
            <select name="branch_id" class="form-select" style="width:auto;" onchange="this.form.submit()">
                <option value="">{{ translate('All branches') }}</option>
                @foreach($branches as $b)
                    <option value="{{ $b->id }}" {{ $branchId == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                @endforeach
            </select>
            <input type="date" name="date" value="{{ $date }}" class="form-control" style="width:auto;" onchange="this.form.submit()">
            <input type="search" name="q" value="{{ $search }}" class="form-control ms-auto" style="width:200px;" placeholder="{{ translate('Search assignment…') }}" onchange="this.form.submit()">
            @if($status !== '')<input type="hidden" name="status" value="{{ $status }}">@endif
            <a class="btn btn-link btn-sm p-0 ms-2" href="{{ route('admin.checklist.runs') }}">{{ translate('Clear') }}</a>
        </form>

        @if($runs->isEmpty())
            <div class="wa-empty">
                <i class="tio-time" style="font-size:42px;color:#D8D8D8"></i>
                <p class="mt-3 mb-0">{{ translate('No runs in the selected range.') }}</p>
            </div>
        @else
            @foreach($runs as $r)
                @php
                    $row = $progressRows[$r->id] ?? null;
                    $checked = (int) ($row->checked ?? 0);
                    $total = (int) ($row->total ?? 0);
                    $photos = (int) ($row->photos ?? 0);
                    $pct = $total === 0 ? 0 : round(100 * $checked / $total);

                    if ($r->completed_at) {
                        $statusBg = '#D8F4DC'; $statusFg = '#1E8E3E';
                        $statusLabel = translate('Done') . ' ' . optional($r->completed_at)->format('H:i');
                    } else {
                        $statusBg = '#FFE7C7'; $statusFg = '#C55E15';
                        $statusLabel = translate('In progress');
                    }

                    $startedBy = trim(($r->startedBy?->f_name ?? '') . ' ' . ($r->startedBy?->l_name ?? '')) ?: '—';
                    $subs = $submitters[$r->id] ?? collect();
                @endphp
                <a class="wa-row" href="{{ route('admin.checklist.runs.show', [$r->id]) }}">
                    <div class="date">
                        {{ optional($r->run_date)->format('M d') }}
                        <small>{{ optional($r->run_date)->format('Y') }}</small>
                    </div>
                    <div>
                        <div>
                            <span class="kind-pill kind-{{ $r->template?->kind ?? 'daily' }}">{{ ucfirst($r->template?->kind ?? '') }}</span>
                            <span class="name ms-1">{{ $r->template?->name ?? '—' }}</span>
                        </div>
                        <div class="meta">
                            <i class="tio-store"></i> {{ $r->branch?->name ?? translate('Global') }}
                            · <i class="tio-user-outlined"></i> {{ $startedBy }} {{ optional($r->started_at)->format('H:i') }}
                            @if($photos > 0)
                                · <i class="tio-camera"></i> {{ $photos }}
                            @endif
                            @if($subs->isNotEmpty())
                                · <span class="avatar-stack">
                                    @foreach($subs->take(4) as $s)
                                        @php $name = $s->admin_name ?: ''; $initial = mb_substr($name, 0, 1) ?: '·'; @endphp
                                        <span class="av" title="{{ $name }}">{{ mb_strtoupper($initial) }}</span>
                                    @endforeach
                                    @if($subs->count() > 4)<span class="av" style="background:#E5E5E5;color:#666">+{{ $subs->count() - 4 }}</span>@endif
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="progress-cell">
                        <div class="progress">
                            <div class="progress-bar" style="width:{{ $pct }}%; background: {{ $r->completed_at ? '#1E8E3E' : '#E87521' }}"></div>
                        </div>
                        <div class="pct"><span>{{ $checked }} / {{ $total }}</span><span>{{ $pct }}%</span></div>
                    </div>
                    <div><span class="status" style="background:{{ $statusBg }};color:{{ $statusFg }}">{{ $statusLabel }}</span></div>
                    <div class="chev"><i class="tio-chevron-right"></i></div>
                </a>
            @endforeach
            <div class="mt-3">{{ $runs->links() }}</div>
        @endif
    </div>
@endsection
