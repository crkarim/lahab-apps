@extends('layouts.admin.app')

@section('title', translate('Work Assignments'))

@push('css_or_js')
    <style>
        .wa-shell { max-width: 1180px; margin: 0 auto; }
        .wa-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 14px; gap: 12px; flex-wrap: wrap; }
        .wa-title { font-size: 22px; font-weight: 700; color: #20140C; margin: 0; }
        .wa-subtitle { font-size: 12px; color: #6c757d; }

        /* Stats strip across the top */
        .wa-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 14px; }
        .wa-stat {
            background: #fff; border: 1px solid rgba(0,0,0,.06); border-radius: 12px;
            padding: 12px 14px; display: flex; align-items: center; gap: 12px;
        }
        .wa-stat .ic {
            width: 36px; height: 36px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px;
        }
        .wa-stat .v { font-size: 22px; font-weight: 800; color: #20140C; line-height: 1; }
        .wa-stat .l { font-size: 11px; color: #6c757d; text-transform: uppercase; letter-spacing: .3px; margin-top: 2px; }

        /* Filter + search row */
        .wa-filters { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 12px; }
        .wa-filter-pill {
            padding: 6px 12px; border-radius: 999px; font-size: 12px; font-weight: 600;
            text-decoration: none; border: 1px solid rgba(0,0,0,.08);
            color: #20140C; background: #fff;
        }
        .wa-filter-pill.active { background: #E87521; color: #fff; border-color: #E87521; }
        .wa-search input { font-size: 13px; height: 32px; padding: 4px 10px; }

        /* Card grid — 4 columns at xl, 3 at lg, 2 at md, 1 at sm */
        .wa-grid { display: grid; gap: 12px; grid-template-columns: repeat(2, 1fr); }
        @media (min-width: 992px) { .wa-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (min-width: 1280px) { .wa-grid { grid-template-columns: repeat(4, 1fr); } }

        .wa-card {
            background: #fff; border: 1px solid rgba(0,0,0,.06); border-radius: 12px;
            padding: 14px; display: flex; flex-direction: column;
            cursor: pointer;
            transition: box-shadow .15s, transform .15s, border-color .15s;
        }
        .wa-card:hover { box-shadow: 0 8px 20px rgba(0,0,0,.06); transform: translateY(-1px); border-color: rgba(232,117,33,.3); }
        .wa-card .top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
        .wa-card .name { font-size: 15px; font-weight: 700; color: #20140C; line-height: 1.25; margin-bottom: 4px; }
        .wa-card .meta { font-size: 11px; color: #6c757d; }
        .wa-card .status-row {
            margin-top: auto; padding-top: 10px;
            display: flex; align-items: center; justify-content: space-between;
            border-top: 1px dashed rgba(0,0,0,.08);
        }
        .wa-card .status {
            font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 999px;
        }
        .wa-card .actions { opacity: .35; transition: opacity .12s; }
        .wa-card:hover .actions { opacity: 1; }
        .wa-card .actions form { display: inline; }

        .kind-pill { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: 10px; font-weight: 700; letter-spacing: .3px; text-transform: uppercase; }
        .kind-open { background: #FFE7C7; color: #C55E15; }
        .kind-daily { background: #DFE7FF; color: #3A6FD5; }
        .kind-close { background: #FFD7D8; color: #B42525; }
        .kind-weekly { background: #D8F4DC; color: #1E8E3E; }

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
                <h1 class="wa-title"><i class="tio-checkmark-circle"></i> {{ translate('Work Assignments') }}</h1>
                <div class="wa-subtitle">{{ translate('Daily duties the team must complete on My Lahab — open / daily / close.') }}</div>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.checklist.runs') }}">
                    <i class="tio-time"></i> {{ translate('Run history') }}
                </a>
                <a class="btn btn-primary btn-sm" href="{{ route('admin.checklist.add-new') }}">
                    <i class="tio-add"></i> {{ translate('New assignment') }}
                </a>
            </div>
        </div>

        {{-- Stats strip --}}
        <div class="wa-stats">
            <div class="wa-stat">
                <div class="ic" style="background:#FFE7C7;color:#C55E15"><i class="tio-folder-bookmarked"></i></div>
                <div><div class="v">{{ $stats['total_assignments'] }}</div><div class="l">{{ translate('Active assignments') }}</div></div>
            </div>
            <div class="wa-stat">
                <div class="ic" style="background:#DFE7FF;color:#3A6FD5"><i class="tio-play-outlined"></i></div>
                <div><div class="v">{{ $stats['started_today'] }}</div><div class="l">{{ translate('Started today') }}</div></div>
            </div>
            <div class="wa-stat">
                <div class="ic" style="background:#D8F4DC;color:#1E8E3E"><i class="tio-checkmark-circle"></i></div>
                <div><div class="v">{{ $stats['completed_today'] }}</div><div class="l">{{ translate('Completed today') }}</div></div>
            </div>
        </div>

        {{-- Filter chips + search --}}
        <form method="get" class="wa-filters">
            @foreach(['' => translate('All'), 'open' => translate('Open'), 'daily' => translate('Daily'), 'close' => translate('Close'), 'weekly' => translate('Weekly')] as $k => $label)
                @php
                    $params = [];
                    if ($k !== '') $params['kind'] = $k;
                    if ($search !== '') $params['q'] = $search;
                @endphp
                <a class="wa-filter-pill {{ $kind === $k ? 'active' : '' }}"
                   href="{{ route('admin.checklist.list', $params) }}">{{ $label }}</a>
            @endforeach
            <div class="wa-search ms-auto">
                <input type="search" name="q" value="{{ $search }}" class="form-control" placeholder="{{ translate('Search…') }}" onchange="this.form.submit()">
                @if($kind !== '')<input type="hidden" name="kind" value="{{ $kind }}">@endif
            </div>
        </form>

        @if($templates->isEmpty())
            <div class="wa-empty">
                <i class="tio-checkmark-circle" style="font-size:42px;color:#D8D8D8"></i>
                <p class="mt-3 mb-2">{{ translate('No work assignments yet.') }}</p>
                <a class="btn btn-primary btn-sm" href="{{ route('admin.checklist.add-new') }}">
                    <i class="tio-add"></i> {{ translate('Create your first assignment') }}
                </a>
            </div>
        @else
            <div class="wa-grid">
                @foreach($templates as $t)
                    @php
                        $runsForT = $todayRuns[$t->id] ?? collect();
                        $hasRunToday = $runsForT->isNotEmpty();
                        $isCompleteToday = $hasRunToday && $runsForT->whereNotNull('completed_at')->isNotEmpty();
                        $progressRow = $todayProgress[$t->id] ?? null;
                        $checked = (int) ($progressRow->checked ?? 0);
                        $total   = (int) ($progressRow->total ?? 0);

                        if (! $t->is_active) {
                            $statusBg = '#F2F2F2'; $statusFg = '#9aa0a6'; $statusLabel = translate('Archived');
                        } elseif ($isCompleteToday) {
                            $statusBg = '#D8F4DC'; $statusFg = '#1E8E3E'; $statusLabel = translate('Done today');
                        } elseif ($hasRunToday) {
                            $statusBg = '#FFE7C7'; $statusFg = '#C55E15'; $statusLabel = "$checked / $total " . translate('done');
                        } else {
                            $statusBg = '#F0F0F2'; $statusFg = '#6c757d'; $statusLabel = translate('Not started');
                        }

                        $lastRunDate = $lastRuns[$t->id] ?? null;
                        $lastRunStr = $lastRunDate
                            ? \Illuminate\Support\Carbon::parse($lastRunDate)->diffForHumans()
                            : translate('Never run');
                    @endphp
                    <a class="wa-card" href="{{ route('admin.checklist.edit', [$t->id]) }}">
                        <div class="top">
                            <span class="kind-pill kind-{{ $t->kind }}">{{ ucfirst($t->kind) }}</span>
                            @if(! $t->is_active)
                                <i class="tio-blocked text-danger" title="{{ translate('Archived') }}"></i>
                            @endif
                        </div>
                        <div class="name">{{ $t->name }}</div>
                        <div class="meta">
                            @if($t->branch_id)
                                <i class="tio-store"></i> {{ $branches->firstWhere('id', $t->branch_id)?->name ?? '#' . $t->branch_id }}
                            @else
                                <i class="tio-globe"></i> {{ translate('All branches') }}
                            @endif
                            · {{ $t->items_count }} {{ $t->items_count === 1 ? translate('step') : translate('steps') }}
                        </div>
                        <div class="meta mt-1"><i class="tio-time"></i> {{ translate('Last') }}: {{ $lastRunStr }}</div>
                        <div class="status-row">
                            <span class="status" style="background:{{ $statusBg }};color:{{ $statusFg }}">{{ $statusLabel }}</span>
                            <div class="actions" onclick="event.stopPropagation();event.preventDefault();">
                                <form action="{{ route('admin.checklist.destroy', [$t->id]) }}" method="post"
                                      onsubmit="return confirm('Delete this work assignment? Past run history is kept.');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ translate('Delete') }}"><i class="tio-delete"></i></button>
                                </form>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
            <div class="mt-3">{{ $templates->links() }}</div>
        @endif
    </div>
@endsection
