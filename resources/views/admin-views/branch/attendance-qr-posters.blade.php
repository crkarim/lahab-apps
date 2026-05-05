@extends('layouts.admin.app')

@section('title', translate('Attendance QR Posters'))

@push('css_or_js')
    <style>
        @media print {
            .no-print { display: none !important; }
            .qr-poster { page-break-after: always; }
        }
        .qr-poster {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 24px;
            text-align: center;
            background: #fff;
            margin-bottom: 24px;
        }
        .qr-poster img { max-width: 320px; width: 100%; height: auto; }
    </style>
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header d-flex justify-content-between align-items-center no-print">
            <div>
                <h1 class="page-header-title">
                    <i class="tio-qr-code"></i> {{ translate('Attendance QR Posters') }}
                </h1>
                <p class="text-muted mb-0">
                    {{ translate('One QR per branch. Print and laminate at the staff time-clock spot. Staff scan via the My Lahab app to clock in/out.') }}
                </p>
            </div>
            <button onclick="window.print()" class="btn btn-primary">
                <i class="tio-print"></i> {{ translate('Print all') }}
            </button>
        </div>

        @forelse($branches as $branch)
            <div class="qr-poster">
                <h2 class="mb-1">{{ $branch->name }}</h2>
                <p class="text-muted mb-3">{{ $branch->address ?? '' }}</p>

                @if(!empty($branch->attendance_qr_token))
                    <img src="{{ route('admin.branch.attendance-qr-image', [$branch->id]) }}" alt="Attendance QR for {{ $branch->name }}">
                    <p class="mt-3 mb-0">
                        <strong>{{ translate('Scan with the My Lahab app to clock in/out') }}</strong>
                    </p>
                    <p class="text-muted">
                        <small>{{ translate('Geofence radius') }}: {{ $branch->attendance_geo_radius_m ?? 150 }} m</small>
                    </p>
                @else
                    <p class="text-muted">{{ translate('No QR yet — open this branch and click Regenerate.') }}</p>
                @endif

                <div class="no-print mt-2">
                    <a href="{{ route('admin.branch.edit', [$branch->id]) }}" class="btn btn-sm btn-outline-secondary">
                        <i class="tio-edit"></i> {{ translate('Edit branch / rotate token') }}
                    </a>
                </div>
            </div>
        @empty
            <p class="text-center text-muted py-5">{{ translate('No branches found.') }}</p>
        @endforelse
    </div>
@endsection
