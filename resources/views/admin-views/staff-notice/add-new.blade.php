@extends('layouts.admin.app')

@section('title', translate('New Staff Notice'))

@push('css_or_js')
    <style>
        .sn-shell { max-width: 1180px; margin: 0 auto; }
        .sn-form .form-label { font-size: 10px; font-weight: 600; color: rgba(32,20,12,.55); margin-bottom: 3px; text-transform: uppercase; letter-spacing: .3px; }
        .sn-form .form-control, .sn-form .form-select { font-size: 13px; }
        .sn-form .form-control-sm { padding: 6px 10px; height: 34px; }
        .sn-form textarea { resize: vertical; min-height: 78px; }
        .sn-pill {
            display: flex; align-items: center; justify-content: center;
            width: 100%; height: 34px; cursor: pointer;
            border: 1px solid rgba(0,0,0,.1);
            background: #fff;
            border-radius: 8px;
            font-size: 12px; font-weight: 600; color: #6c757d;
            user-select: none;
        }
        .sn-pill input { display: none; }
        .sn-pill:has(input:checked) { background: #FFF3CD; border-color: rgba(255,193,7,.5); color: #856404; }
        .sn-push-row {
            background: #FFFBF6; border: 1px solid rgba(232,117,33,.2);
            border-radius: 10px; padding: 10px 12px;
            display: flex; flex-wrap: wrap; align-items: baseline; gap: 12px;
        }
    </style>
@endpush

@section('content')
    <div class="content container-fluid sn-shell">
        <div class="page-header d-flex justify-content-between align-items-center">
            <div>
                <h1 class="page-header-title mb-0"><i class="tio-add"></i> {{ translate('New Staff Notice') }}</h1>
                <small class="text-muted">{{ translate('Posts to staff phones via the My Lahab Notices tab.') }}</small>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.staff-notice.list') }}">{{ translate('Back') }}</a>
        </div>
        <form action="{{ route('admin.staff-notice.add-new') }}" method="post" enctype="multipart/form-data">
            <div class="card border-0 shadow-sm" style="background:#fff;border-radius:14px;">
                <div class="card-body" style="padding:18px;">
                    @include('admin-views.staff-notice._form')
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-3">
                <a class="btn btn-outline-secondary" href="{{ route('admin.staff-notice.list') }}">{{ translate('Cancel') }}</a>
                <button type="submit" class="btn btn-primary"><i class="tio-add"></i> {{ translate('Publish notice') }}</button>
            </div>
        </form>
    </div>
@endsection
