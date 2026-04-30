@extends('layouts.admin.app')

@section('title', 'Maintenance')

@section('content')
<div class="content container-fluid">
    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <h2 class="h1 mb-0 d-flex align-items-center gap-2">
            <i class="tio-tools" style="font-size:24px; color:#B45A0A"></i>
            <span class="page-header-title">Maintenance</span>
        </h2>
        <span class="badge badge-soft-warning ml-2">Deploy helper</span>
    </div>

    @if(session('success'))
        <div class="alert alert-success py-2">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger py-2">{{ session('error') }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <p class="mb-2">
                Runs <code>php artisan migrate --force</code> + clears
                <code>config / route / view / cache</code>. Use after pushing new code to a host without shell access.
            </p>
            @unless($has_token)
                <div class="alert alert-warning mb-0">
                    <strong>Maintenance token not set.</strong> Add
                    <code>MAINTENANCE_TOKEN=&lt;long-random-string&gt;</code> to your <code>.env</code> file
                    on the server, then refresh this page. Nothing will run until the token is set.
                </div>
            @else
                <form action="{{ url('admin/maintenance/run') }}" method="POST" onsubmit="return confirm('Run migrate + clear caches now?');">
                    @csrf
                    <div class="form-group">
                        <label>Token <small class="text-muted">(must match <code>MAINTENANCE_TOKEN</code> in .env)</small></label>
                        <input type="password" name="token" class="form-control" autocomplete="off" required>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="tio-play-outlined mr-1"></i> Run update
                    </button>
                </form>
            @endunless
        </div>
    </div>

    @if($last_output)
        <div class="card">
            <div class="card-header py-2"><h5 class="card-title mb-0">Last output</h5></div>
            <div class="card-body">
                <pre style="background:#0e0e10; color:#dcdcdc; padding:14px; border-radius:6px; max-height:500px; overflow:auto; white-space:pre-wrap; font-size:12px;">{{ $last_output }}</pre>
            </div>
        </div>
    @endif

    <div class="alert alert-secondary mt-3 small">
        <strong>After the deploy is stable,</strong> remove this surface so it can't be used again:
        <ol class="mb-0 mt-1">
            <li>Delete <code>app/Http/Controllers/Admin/MaintenanceController.php</code></li>
            <li>Delete <code>resources/views/admin-views/maintenance/index.blade.php</code></li>
            <li>Remove the two <code>admin/maintenance</code> routes from <code>routes/admin.php</code></li>
            <li>Remove <code>MAINTENANCE_TOKEN</code> from <code>.env</code></li>
        </ol>
    </div>
</div>
@endsection
