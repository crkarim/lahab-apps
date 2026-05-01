@extends('layouts.admin.app')

@section('title', translate('Biometric Import'))

@section('content')
<style>
    .lh-bio-page { max-width: 900px; margin: 0 auto; }
    .lh-bio-hero {
        background: linear-gradient(135deg, #fff 0%, #fff7ee 100%);
        border: 1px solid #f1e3cf; border-radius: 16px;
        padding: 22px 26px; margin-bottom: 18px;
        display: flex; gap: 18px; align-items: center; flex-wrap: wrap;
    }
    .lh-bio-hero .icon {
        width: 56px; height: 56px; border-radius: 50%;
        background: rgba(71, 148, 255, 0.14); color: #4794FF;
        display: flex; align-items: center; justify-content: center;
        font-size: 26px; flex-shrink: 0;
    }
    .lh-bio-hero h1 { margin: 0; font-size: 20px; font-weight: 800; color: #1A1A1A; }
    .lh-bio-hero p  { margin: 2px 0 0; color: #6A6A70; font-size: 13px; }

    .lh-card { background: #fff; border: 1px solid #E5E7EB; border-radius: 12px; padding: 18px 22px; margin-bottom: 14px; }
    .lh-card h3 {
        font-size: 11px; font-weight: 800; letter-spacing: 1.4px;
        color: #6A6A70; text-transform: uppercase; margin: 0 0 12px;
    }

    .csv-spec {
        background: #0e0e10; color: #d8d8d8;
        font-family: monospace; font-size: 12px;
        padding: 12px 14px; border-radius: 8px;
        white-space: pre;
        overflow-x: auto;
        margin: 8px 0 14px;
    }

    .lh-recent-row {
        display: grid;
        grid-template-columns: 110px 1fr 110px 110px 70px;
        gap: 10px;
        padding: 8px 4px;
        border-bottom: 1px solid #F0F2F5;
        font-size: 12px;
        align-items: center;
    }
    .lh-recent-row:last-child { border-bottom: 0; }
    .lh-recent-row .code {
        background: #FFF4E5; color: #E67E22;
        padding: 2px 7px; border-radius: 4px;
        font-family: monospace; font-weight: 700;
        font-size: 11px;
    }
    .lh-recent-row .who strong { color: #1A1A1A; font-weight: 700; }
    .lh-recent-row .who small { display:block; color:#6A6A70; font-size:11px; }
    .lh-recent-row .time { font-variant-numeric: tabular-nums; }
    .lh-empty { padding: 20px; text-align: center; color: #6A6A70; font-size: 13px; }
</style>

<div class="lh-bio-page">

    @if(session('error'))<div class="alert alert-soft-danger">{{ session('error') }}</div>@endif
    @if(session('success'))<div class="alert alert-soft-success">{{ session('success') }}</div>@endif

    <div class="lh-bio-hero">
        <div class="icon">🖐</div>
        <div>
            <h1>{{ translate('Biometric attendance import') }}</h1>
            <p>{{ translate('Upload a CSV exported from your ZKTeco / fingerprint / face-recognition device. Rows match employees by Employee ID (User ID on the device).') }}</p>
        </div>
    </div>

    <div class="lh-card">
        <h3>{{ translate('Upload CSV') }}</h3>
        <form method="POST" action="{{ route('admin.biometric.import') }}" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <input type="file" name="file" accept=".csv,text/csv" class="form-control" required>
                <small class="form-text text-muted">{{ translate('Max 5 MB. UTF-8 encoded.') }}</small>
            </div>
            <div style="display:flex; gap:8px; margin-top:10px;">
                <button type="submit" class="btn btn-primary">{{ translate('Import') }}</button>
                <a href="{{ route('admin.biometric.sample') }}" class="btn btn-light">⬇ {{ translate('Download sample CSV') }}</a>
            </div>
        </form>

        <h3 style="margin-top:18px;">{{ translate('Expected format') }}</h3>
        <div class="csv-spec">user_id,timestamp,event_type
1001,2026-05-01 09:13:42,in
1001,2026-05-01 18:05:11,out
1002,2026-05-01 09:18:00,in
1002,2026-05-01 17:55:33,out

# event_type is optional — leave blank for alternating mode
# (1st event of the day = in, 2nd = out, 3rd = in, ...)
1003,2026-05-01 09:25:00
1003,2026-05-01 18:10:00</div>

        <ul style="font-size:12px; color:#6A6A70; line-height:1.7; margin:0; padding-left:18px;">
            <li>{{ translate('user_id matches the Employee ID field on each staff member\'s profile (admins.employee_code).') }}</li>
            <li>{{ translate('event_type accepts: in, out, IN, OUT, 0 (in), 1 (out), or empty (alternating).') }}</li>
            <li>{{ translate('Re-importing the same file is safe — duplicates within the same minute are skipped.') }}</li>
            <li>{{ translate('Rows for unknown user_ids are reported, not silently dropped.') }}</li>
        </ul>
    </div>

    <div class="lh-card">
        <h3>{{ translate('Recent biometric rows') }} <small style="font-weight:600; color:#1A1A1A; margin-left:6px;">{{ $recent->count() }}</small></h3>
        @forelse($recent as $r)
            <div class="lh-recent-row">
                <div>
                    @if($r->employee?->employee_code)
                        <span class="code">{{ $r->employee->employee_code }}</span>
                    @else
                        <span style="color:#A0A4AB; font-size:11px;">—</span>
                    @endif
                </div>
                <div class="who">
                    <strong>{{ trim(($r->employee->f_name ?? '') . ' ' . ($r->employee->l_name ?? '')) ?: translate('Unknown') }}</strong>
                    <small>{{ optional($r->clock_in_at)->format('d M (D)') }}</small>
                </div>
                <div class="time">{{ optional($r->clock_in_at)->format('H:i') }}</div>
                <div class="time">{{ $r->clock_out_at ? $r->clock_out_at->format('H:i') : '—' }}</div>
                <div style="font-size:10px; font-weight:700; color:#4794FF;">BIO</div>
            </div>
        @empty
            <div class="lh-empty">{{ translate('No biometric rows imported yet.') }}</div>
        @endforelse
    </div>
</div>
@endsection
