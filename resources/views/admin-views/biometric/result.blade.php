@extends('layouts.admin.app')

@section('title', translate('Biometric Import Result'))

@section('content')
<style>
    .lh-bio-result { max-width: 720px; margin: 0 auto; }
    .lh-stat-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin-bottom: 18px;
    }
    @media (max-width: 600px) { .lh-stat-grid { grid-template-columns: repeat(2, 1fr); } }
    .lh-stat {
        background: #fff;
        border: 1px solid #E5E7EB;
        border-radius: 12px;
        padding: 16px 18px;
        text-align: center;
    }
    .lh-stat .label {
        font-size: 11px; font-weight: 700; color: #6A6A70;
        text-transform: uppercase; letter-spacing: 1.2px;
    }
    .lh-stat .value {
        font-size: 26px; font-weight: 800; color: #1A1A1A;
        margin-top: 4px;
    }
    .lh-stat.success .value { color: #1E8E3E; }
    .lh-stat.warn .value    { color: #B45A0A; }
    .lh-stat.err .value     { color: #E84D4F; }

    .lh-card { background: #fff; border: 1px solid #E5E7EB; border-radius: 12px; padding: 16px 20px; margin-bottom: 14px; }
    .lh-card h3 {
        font-size: 11px; font-weight: 800; letter-spacing: 1.4px;
        color: #6A6A70; text-transform: uppercase; margin: 0 0 12px;
    }
    .lh-list { font-size: 12px; max-height: 260px; overflow-y: auto; }
    .lh-list .item {
        padding: 6px 0;
        border-bottom: 1px solid #F0F2F5;
        color: #6A6A70;
    }
    .lh-list .item:last-child { border-bottom: 0; }
    .lh-list .item code {
        background: #fff4e5; color: #E67E22;
        padding: 2px 5px; border-radius: 3px;
        font-family: monospace; font-size: 11px;
    }
</style>

<div class="lh-bio-result">

    <div style="margin-bottom:14px;">
        <a href="{{ route('admin.biometric.index') }}" class="btn btn-light btn-sm">← {{ translate('Back to import') }}</a>
    </div>

    <h2 style="font-size:20px; font-weight:800; margin:0 0 14px; color:#1A1A1A;">
        {{ translate('Import complete') }}
    </h2>

    <div class="lh-stat-grid">
        <div class="lh-stat success">
            <div class="label">{{ translate('Clock-ins created') }}</div>
            <div class="value">{{ $imported }}</div>
        </div>
        <div class="lh-stat success">
            <div class="label">{{ translate('Clock-outs paired') }}</div>
            <div class="value">{{ $closed }}</div>
        </div>
        <div class="lh-stat warn">
            <div class="label">{{ translate('Duplicates skipped') }}</div>
            <div class="value">{{ $skipped }}</div>
        </div>
    </div>

    @if(count($unknowns))
    <div class="lh-card">
        <h3>{{ translate('Unknown employee codes') }} <small style="font-weight:600; color:#E84D4F; margin-left:6px;">{{ count($unknowns) }}</small></h3>
        <p style="font-size:12px; color:#6A6A70; margin:0 0 8px;">
            {{ translate('These User IDs in the CSV did not match any employee. Set Employee ID on the staff profile (Admin → Employees → Update) and re-import.') }}
        </p>
        <div class="lh-list">
            @foreach($unknowns as $code => $count)
                <div class="item">
                    <code>{{ $code }}</code> — {{ $count }} {{ translate($count === 1 ? 'event' : 'events') }}
                </div>
            @endforeach
        </div>
    </div>
    @endif

    @if(count($errors))
    <div class="lh-card">
        <h3>{{ translate('Errors') }} <small style="font-weight:600; color:#E84D4F; margin-left:6px;">{{ count($errors) }}</small></h3>
        <p style="font-size:12px; color:#6A6A70; margin:0 0 8px;">
            {{ translate('These rows were skipped. Most common cause: out events with no matching open clock-in.') }}
        </p>
        <div class="lh-list">
            @foreach(array_slice($errors, 0, 50) as $err)
                <div class="item">{{ $err }}</div>
            @endforeach
            @if(count($errors) > 50)
                <div class="item" style="font-style:italic;">… {{ count($errors) - 50 }} {{ translate('more') }}</div>
            @endif
        </div>
    </div>
    @endif

    @if($imported > 0 || $closed > 0)
    <div style="font-size:12px; color:#6A6A70; padding:14px 18px; background:#ECFFEF; border:1px solid #c7eed2; border-radius:8px;">
        ✓ {{ translate('All imported rows are tagged') }} <code style="background:#fff; padding:2px 5px; border-radius:3px; font-size:11px;">method = biometric</code>
        — {{ translate('they\'ll show up under Attendance with a BIO badge.') }}
    </div>
    @endif

</div>
@endsection
