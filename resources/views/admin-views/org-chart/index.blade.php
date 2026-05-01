@extends('layouts.admin.app')

@section('title', translate('Org Chart'))

@section('content')
<style>
    .lh-oc-page { max-width: 1280px; margin: 0 auto; }
    .lh-oc-hero {
        background: linear-gradient(135deg, #fff 0%, #eef9f0 100%);
        border: 1px solid #cfe6d3; border-radius: 16px;
        padding: 22px 26px; margin-bottom: 18px;
        display: flex; gap: 18px; align-items: center; flex-wrap: wrap;
    }
    .lh-oc-hero .icon {
        width: 56px; height: 56px; border-radius: 50%;
        background: rgba(30, 142, 62, 0.14); color: #1E8E3E;
        display: flex; align-items: center; justify-content: center;
        font-size: 26px; flex-shrink: 0;
    }
    .lh-oc-hero h1 { margin: 0; font-size: 20px; font-weight: 800; color: #1A1A1A; }
    .lh-oc-hero p  { margin: 2px 0 0; color: #6A6A70; font-size: 13px; max-width: 700px; }
    .lh-oc-hero .stats { margin-left: auto; display: flex; gap: 14px; }
    .lh-oc-hero .stat .v { font-size: 22px; font-weight: 800; color: #1A1A1A; }
    .lh-oc-hero .stat .l { font-size: 10px; font-weight: 700; color: #6A6A70; letter-spacing: 1px; text-transform: uppercase; }

    .lh-dept-block {
        background: #fff; border: 1px solid #E5E7EB; border-radius: 12px;
        padding: 16px 20px; margin-bottom: 14px;
    }
    .lh-dept-head {
        display: flex; align-items: center; gap: 12px;
        padding-bottom: 12px; border-bottom: 1px solid #F0F2F5; margin-bottom: 14px;
    }
    .lh-dept-head .badge {
        display: inline-block; padding: 4px 12px;
        font-size: 12px; font-weight: 800; color: #fff;
        border-radius: 999px; letter-spacing: .3px;
    }
    .lh-dept-head .count {
        font-size: 11px; font-weight: 700; color: #6A6A70;
        text-transform: uppercase; letter-spacing: 1px;
    }
    .lh-dept-head .desc {
        font-size: 12px; color: #6A6A70; margin-left: auto; max-width: 60%;
    }

    /* Tree styling — pure CSS, no library */
    .lh-tree { padding-left: 0; list-style: none; margin: 0; }
    .lh-tree ul {
        padding-left: 28px; list-style: none; margin: 0;
        position: relative;
    }
    .lh-tree ul::before {
        content: ''; position: absolute; left: 12px; top: 0; bottom: 12px;
        width: 2px; background: #E5E7EB;
    }
    .lh-tree li {
        position: relative; padding: 6px 0;
    }
    .lh-tree ul > li::before {
        content: ''; position: absolute; left: -16px; top: 22px;
        width: 18px; height: 2px; background: #E5E7EB;
    }
    .lh-node {
        display: inline-flex; align-items: center; gap: 10px;
        background: #FAFBFC; border: 1px solid #E5E7EB; border-radius: 8px;
        padding: 6px 12px 6px 6px;
    }
    .lh-node .avatar {
        width: 32px; height: 32px; border-radius: 50%;
        background: #6A6A70; color: #fff;
        display: flex; align-items: center; justify-content: center;
        font-size: 13px; font-weight: 800; overflow: hidden;
    }
    .lh-node .avatar img { width: 100%; height: 100%; object-fit: cover; }
    .lh-node .info strong {
        font-size: 13px; color: #1A1A1A; display: block;
    }
    .lh-node .info small {
        font-size: 11px; color: #6A6A70;
    }
    .lh-node .code-pill {
        display: inline-block; padding: 1px 6px;
        background: #FFF4E5; color: #E67E22;
        border-radius: 3px; font-family: monospace;
        font-size: 10px; font-weight: 700;
    }
    .lh-node.master {
        background: #FFF8E1; border-color: #F4DDA1;
    }
    .lh-node.no-mgr {
        background: #FFEFEF; border-color: #F7C9C9;
    }

    .lh-empty-dept {
        font-size: 12px; color: #6A6A70; padding: 8px 0;
        font-style: italic;
    }
    .lh-unassigned {
        background: #FFEFEF; border: 1px solid #F7C9C9; border-radius: 12px;
        padding: 14px 18px; margin-bottom: 14px;
    }
    .lh-unassigned h3 {
        font-size: 13px; font-weight: 800; color: #C82626; margin: 0 0 8px;
    }
    .lh-unassigned-list { display: flex; gap: 8px; flex-wrap: wrap; }
</style>

@php
    /**
     * Tree renderer — recursive PHP closure that emits <li> + nested <ul>
     * for each manager → their direct reports. Bound to the data passed
     * in via `$childrenByMgr` so we don't re-query inside the loop.
     */
    $renderNode = function ($admin) use (&$renderNode, $childrenByMgr) {
        $name = trim(($admin->f_name ?? '') . ' ' . ($admin->l_name ?? ''));
        $initial = strtoupper(substr($admin->f_name ?? '?', 0, 1) . substr($admin->l_name ?? '', 0, 1));
        $isMaster = (int) ($admin->admin_role_id ?? 0) === 1;
        $kids = $childrenByMgr[$admin->id] ?? [];
        echo '<li>';
        echo '<div class="lh-node ' . ($isMaster ? 'master' : '') . '">';
        echo '  <div class="avatar">';
        if (!empty($admin->image)) {
            echo '<img src="' . e(asset('storage/app/public/admin/' . $admin->image)) . '" alt="">';
        } else {
            echo e($initial);
        }
        echo '  </div>';
        echo '  <div class="info">';
        echo '    <strong>' . e($name ?: '—') . '</strong>';
        echo '    <small>';
        if (!empty($admin->employee_code)) {
            echo '<span class="code-pill">' . e($admin->employee_code) . '</span> ';
        }
        echo e($admin->designation ?: ($isMaster ? 'Master Admin' : 'Staff'));
        echo '    </small>';
        echo '  </div>';
        echo '</div>';
        if (count($kids) > 0) {
            echo '<ul>';
            foreach ($kids as $kid) {
                $renderNode($kid);
            }
            echo '</ul>';
        }
        echo '</li>';
    };
@endphp

<div class="lh-oc-page">

    <div class="lh-oc-hero">
        <div class="icon">🌳</div>
        <div>
            <h1>{{ translate('Organization chart') }}</h1>
            <p>{{ translate('Reporting tree by department. Yellow = Master Admin / department head. Pink = no manager set (set "Reports to" on the employee record).') }}</p>
        </div>
        <div class="stats">
            <div class="stat">
                <div class="v">{{ count($tree) }}</div>
                <div class="l">{{ translate('Departments') }}</div>
            </div>
            <div class="stat">
                <div class="v">{{ $totalStaff }}</div>
                <div class="l">{{ translate('Staff') }}</div>
            </div>
        </div>
    </div>

    @foreach($tree as $node)
        @php $dept = $node['dept']; @endphp
        <div class="lh-dept-block">
            <div class="lh-dept-head">
                <span class="badge" style="background: {{ $dept->color }};">{{ $dept->name }}</span>
                <span class="count">{{ $node['all_count'] }} {{ translate('member(s)') }}</span>
                @if($dept->branch)
                    <span style="font-size:11px; color:#6A6A70;">{{ $dept->branch->name }}</span>
                @endif
                @if($dept->description)
                    <div class="desc">{{ $dept->description }}</div>
                @endif
            </div>

            @if(count($node['tops']) > 0)
                <ul class="lh-tree">
                    @foreach($node['tops'] as $top)
                        @php $renderNode($top); @endphp
                    @endforeach
                </ul>
            @else
                <div class="lh-empty-dept">{{ translate('No members assigned to this department yet.') }}</div>
            @endif
        </div>
    @endforeach

    @if(count($unassigned) > 0)
    <div class="lh-unassigned">
        <h3>⚠️ {{ translate('Staff with no department') }} ({{ count($unassigned) }})</h3>
        <div class="lh-unassigned-list">
            @foreach($unassigned as $u)
                <div class="lh-node">
                    <div class="avatar">{{ strtoupper(substr($u->f_name ?? '?', 0, 1) . substr($u->l_name ?? '', 0, 1)) }}</div>
                    <div class="info">
                        <strong>{{ trim(($u->f_name ?? '') . ' ' . ($u->l_name ?? '')) }}</strong>
                        <small>{{ $u->designation ?: 'Staff' }}</small>
                    </div>
                </div>
            @endforeach
        </div>
        <div style="font-size:11px; color:#C82626; margin-top:10px;">
            {{ translate('Open each person\'s edit page and set their Department + Reports-to so they show up in the chart above.') }}
        </div>
    </div>
    @endif

</div>
@endsection
