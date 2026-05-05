@extends('layouts.admin.app')

@section('title', translate('New Work Assignment'))

@section('content')
    <div class="content container-fluid" style="max-width:1180px;margin:0 auto;">
        <div class="page-header d-flex justify-content-between align-items-center">
            <div>
                <h1 class="page-header-title mb-0"><i class="tio-add"></i> {{ translate('New Work Assignment') }}</h1>
                <small class="text-muted">{{ translate('Create the assignment first, then add the steps on the next screen.') }}</small>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.checklist.list') }}">{{ translate('Back') }}</a>
        </div>
        <form action="{{ route('admin.checklist.add-new') }}" method="post">
            <div class="card border-0 shadow-sm" style="background:#fff;border-radius:14px;">
                <div class="card-body" style="padding:18px;">
                    @include('admin-views.checklist._form')
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-3">
                <a class="btn btn-outline-secondary" href="{{ route('admin.checklist.list') }}">{{ translate('Cancel') }}</a>
                <button type="submit" class="btn btn-primary"><i class="tio-add"></i> {{ translate('Create assignment') }}</button>
            </div>
        </form>
    </div>
@endsection
