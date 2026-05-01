@extends('layouts.admin.app')

@section('title', translate('HRM Settings'))

@section('content')
@include('admin-views._partials.hrm_settings_nav', ['active' => 'general'])
<style>
    .lh-hs-page { max-width: 900px; margin: 0 auto; }
    .lh-hs-hero {
        background: linear-gradient(135deg, #fff 0%, #f7f0ff 100%);
        border: 1px solid #e3d6f7; border-radius: 16px;
        padding: 22px 26px; margin-bottom: 18px;
        display: flex; gap: 18px; align-items: center; flex-wrap: wrap;
    }
    .lh-hs-hero .icon {
        width: 56px; height: 56px; border-radius: 50%;
        background: rgba(155, 89, 182, 0.14); color: #9B59B6;
        display: flex; align-items: center; justify-content: center;
        font-size: 26px; flex-shrink: 0;
    }
    .lh-hs-hero h1 { margin: 0; font-size: 20px; font-weight: 800; color: #1A1A1A; }
    .lh-hs-hero p  { margin: 2px 0 0; color: #6A6A70; font-size: 13px; max-width: 700px; }

    .lh-group {
        background: #fff; border: 1px solid #E5E7EB; border-radius: 12px;
        padding: 18px 20px; margin-bottom: 14px;
    }
    .lh-group h2 {
        font-size: 14px; font-weight: 800; color: #1A1A1A;
        margin: 0 0 4px; display: flex; align-items: center; gap: 8px;
    }
    .lh-group .group-sub {
        font-size: 12px; color: #6A6A70; margin-bottom: 16px;
    }
    .lh-row {
        padding: 12px 0;
        border-top: 1px solid #F0F2F5;
        display: grid; grid-template-columns: 1fr 200px; gap: 16px;
        align-items: start;
    }
    .lh-row:first-of-type { border-top: 0; padding-top: 0; }
    @media (max-width: 700px) { .lh-row { grid-template-columns: 1fr; } }
    .lh-row .label-col label {
        font-size: 13px; font-weight: 700; color: #1A1A1A; margin: 0;
    }
    .lh-row .label-col .help {
        font-size: 11px; color: #6A6A70; margin-top: 4px; line-height: 1.5;
    }
    .lh-row .input-col input,
    .lh-row .input-col select {
        width: 100%; border: 1px solid #E5E7EB; border-radius: 8px;
        padding: 9px 12px; font-size: 14px;
        font-variant-numeric: tabular-nums;
    }
    .lh-actions {
        display: flex; gap: 8px; padding-top: 16px;
        position: sticky; bottom: 0; background: #fff;
        margin: 0 -20px -18px; padding: 16px 20px; border-top: 1px solid #E5E7EB;
        border-radius: 0 0 12px 12px;
    }
    .lh-bd-flag {
        background: #FFF8E1; border: 1px solid #F4DDA1; border-radius: 10px;
        padding: 10px 14px; margin-bottom: 14px;
        font-size: 12px; color: #6A4A0A;
    }
</style>

<div class="lh-hs-page">

    @if(session('error'))<div class="alert alert-soft-warning">{{ session('error') }}</div>@endif
    @if(session('success'))<div class="alert alert-soft-success">{{ session('success') }}</div>@endif

    <div class="lh-hs-hero">
        <div class="icon">⚙️</div>
        <div>
            <h1>{{ translate('HRM settings') }}</h1>
            <p>{{ translate('All compliance numbers — gratuity, overtime, probation, working hours — live here. Defaults follow BD Labour Act 2006. Changes take effect on the next payroll/leave action.') }}</p>
        </div>
    </div>

    <div class="lh-bd-flag">
        <strong>{{ translate('Note') }}:</strong>
        {{ translate('Numbers below the statutory minimum (e.g. OT < 2×) are flagged but not blocked — your HR/legal team owns the decision. Citations beside each field reference the relevant section.') }}
    </div>

    <form method="POST" action="{{ route('admin.hrm-settings.update') }}">
        @csrf

        @foreach(['gratuity', 'overtime', 'probation', 'working_time', 'tips', 'general'] as $g)
            @if(!isset($groups[$g]) || $groups[$g]->isEmpty()) @continue @endif

            <div class="lh-group">
                <h2>{{ translate($groupTitles[$g] ?? ucfirst($g)) }}</h2>
                <div class="group-sub">
                    @switch($g)
                        @case('gratuity')      {{ translate('End-of-service gratuity rules. BD Labour Act Sec 2(10) + 26-27.') }} @break
                        @case('overtime')      {{ translate('Overtime rate multipliers. BD Labour Act Sec 108.') }} @break
                        @case('probation')     {{ translate('Default probation periods for new hires.') }} @break
                        @case('working_time')  {{ translate('Standard daily/weekly hours and weekly off-day. BD Labour Act Sec 100, 102, 103.') }} @break
                        @case('tips')          {{ translate('How service charge / tip pool flows into payroll.') }} @break
                        @default               {{ translate('Other HR knobs.') }}
                    @endswitch
                </div>

                @foreach($groups[$g]->sortBy('sort_order') as $s)
                    <div class="lh-row">
                        <div class="label-col">
                            <label for="set_{{ $s->key }}">{{ $s->label }}</label>
                            @if($s->help_text)
                                <div class="help">{{ $s->help_text }}</div>
                            @endif
                        </div>
                        <div class="input-col">
                            @switch($s->type)
                                @case('int')
                                    <input id="set_{{ $s->key }}" type="number" step="1" name="settings[{{ $s->key }}]" value="{{ $s->value }}">
                                    @break
                                @case('decimal')
                                    <input id="set_{{ $s->key }}" type="number" step="0.01" name="settings[{{ $s->key }}]" value="{{ $s->value }}">
                                    @break
                                @case('bool')
                                    <select id="set_{{ $s->key }}" name="settings[{{ $s->key }}]">
                                        <option value="1" @selected($s->value === '1')>{{ translate('Yes') }}</option>
                                        <option value="0" @selected($s->value !== '1')>{{ translate('No') }}</option>
                                    </select>
                                    @break
                                @case('enum')
                                    <select id="set_{{ $s->key }}" name="settings[{{ $s->key }}]">
                                        @foreach($s->optionsList() as $optKey => $optLabel)
                                            <option value="{{ $optKey }}" @selected($s->value === $optKey)>{{ $optLabel }}</option>
                                        @endforeach
                                    </select>
                                    @break
                                @default
                                    <input id="set_{{ $s->key }}" type="text" name="settings[{{ $s->key }}]" value="{{ $s->value }}">
                            @endswitch
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach

        <div class="lh-group" style="margin-top: 0;">
            <div class="lh-actions">
                <button type="button" class="btn btn-light" onclick="window.location.reload()">{{ translate('Reset to saved') }}</button>
                <button type="submit" class="btn btn-primary" style="margin-left:auto;">{{ translate('Save settings') }}</button>
            </div>
        </div>
    </form>

</div>
@endsection
