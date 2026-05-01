{{--
    HRM Settings hub tab nav. Included at the top of every config page so the
    five settings sub-pages feel like one section. The `$active` parameter
    (passed via @include('...', ['active' => 'general'])) drives highlighting.

    Adding a tab? Drop a row in $tabs below — both the route and the active
    key. The CSS lives here so the partial is self-contained.
--}}
@php
    $hrmSettingsTabs = [
        ['key' => 'general',     'label' => 'General',           'icon' => '⚙️',   'route' => 'admin.hrm-settings.index',      'help' => 'Gratuity, OT, probation, working hours.'],
        ['key' => 'departments', 'label' => 'Departments',       'icon' => '🏢',   'route' => 'admin.departments.index',       'help' => 'Kitchen, Service, Bar, Accounts...'],
        ['key' => 'designations','label' => 'Designations',      'icon' => '🎖️',   'route' => 'admin.designations.index',      'help' => 'Job titles + pay-grade hints.'],
        ['key' => 'components',  'label' => 'Salary Components', 'icon' => '💸',   'route' => 'admin.salary-components.index', 'help' => 'Allowance / deduction lines + gross-distribution %.'],
        ['key' => 'roles',       'label' => 'Employee Roles',    'icon' => '🔐',   'route' => 'admin.custom-role.create',      'help' => 'Permission packs assigned to staff.'],
    ];
    $activeTab = $active ?? 'general';
@endphp

<style>
    .lh-hrm-tabs {
        max-width: 1200px; margin: 0 auto 18px;
        background: #fff; border: 1px solid #E5E7EB; border-radius: 12px;
        padding: 6px; overflow-x: auto;
    }
    .lh-hrm-tabs-row {
        display: flex; gap: 4px; flex-wrap: nowrap; min-width: max-content;
    }
    .lh-hrm-tab {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 8px 14px; border-radius: 8px;
        font-size: 13px; font-weight: 600; color: #6A6A70;
        text-decoration: none; white-space: nowrap;
        transition: background .12s ease;
    }
    .lh-hrm-tab:hover { background: #F4F6F8; color: #1A1A1A; text-decoration: none; }
    .lh-hrm-tab.active {
        background: #FFF4E5; color: #B45A0A; font-weight: 800;
    }
    .lh-hrm-tab .icon { font-size: 16px; line-height: 1; }
    .lh-hrm-tab small {
        display: none; color: inherit; opacity: .7; font-weight: 500;
    }
    @media (min-width: 1100px) {
        .lh-hrm-tab small { display: inline; }
    }
</style>

<div class="lh-hrm-tabs">
    <div class="lh-hrm-tabs-row">
        @foreach($hrmSettingsTabs as $t)
            @if(Route::has($t['route']))
                <a href="{{ route($t['route']) }}"
                   class="lh-hrm-tab {{ $activeTab === $t['key'] ? 'active' : '' }}"
                   title="{{ $t['help'] }}">
                    <span class="icon">{{ $t['icon'] }}</span>
                    {{ translate($t['label']) }}
                </a>
            @endif
        @endforeach
    </div>
</div>
