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

    {{-- Reset Test Data panel — separate from the migrate/clear flow
         because it is destructive. Two guards on top of the token:
         dry-run by default, and a typed confirmation phrase that
         changes by env (must type "production" on prod, "RESET" on
         local). The actual artisan command also has its own guards. --}}
    @if($has_token)
        <div class="card mb-3" style="border-color:#C82626;">
            <div class="card-header py-2" style="background:#FFF4F4;">
                <h5 class="card-title mb-0" style="color:#C82626;">
                    <i class="tio-warning"></i> Reset test data
                </h5>
            </div>
            <div class="card-body">
                @php $env = config('app.env', 'unknown'); @endphp
                <p class="mb-2">
                    Wipes <strong>orders, customers, transactions, ledger, employees, bills, suppliers, attendance, payslips, advances, shifts, handovers</strong>.
                    Re-seeds <strong>BD-default master data</strong> (cash accounts, departments, designations, HRM settings, leave types, salary components, expense categories).
                    <strong>Keeps</strong>: products, business settings, branches, dining tables, Master Admin.
                </p>
                <p class="mb-3" style="color:#C82626; font-weight:600;">
                    <strong>NOT REVERSIBLE without a database backup.</strong>
                    On cPanel: open <em>phpMyAdmin → Export → Quick → SQL → Go</em> before running this for real, save the .sql file off-server.
                </p>

                @php
                    $isProd = in_array($env, ['production', 'prod', 'live'], true);
                    $expectedPhrase = $isProd ? $env : 'RESET';
                @endphp

                <form action="{{ url('admin/maintenance/reset-test-data') }}" method="POST"
                      onsubmit="return confirm('Run reset:test-data now? Make sure you have a fresh DB backup.');"
                      style="background:#FFF8F8; padding:12px; border-radius:6px;">
                    @csrf

                    <div class="form-group">
                        <label>Maintenance token <small class="text-muted">(<code>MAINTENANCE_TOKEN</code> in .env)</small></label>
                        <input type="password" name="token" class="form-control" autocomplete="off" required>
                    </div>

                    <div class="form-group">
                        <label>
                            Type <code style="background:#1A1A1A; color:#fff; padding:2px 6px; border-radius:3px;">{{ $expectedPhrase }}</code> exactly to confirm
                        </label>
                        <input type="text" name="confirm_phrase" class="form-control" autocomplete="off"
                               required placeholder="{{ $expectedPhrase }}">
                        <small class="text-muted">
                            Env: <strong>{{ $env }}</strong> — phrase changes per environment so you can't muscle-memory the localhost confirm into a production wipe.
                        </small>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" name="dry_run" value="1" class="btn btn-light"
                                style="border:1px solid #C82626; color:#C82626; font-weight:700;">
                            Dry-run (preview only)
                        </button>
                        <button type="submit" class="btn btn-danger" style="font-weight:700;">
                            <i class="tio-warning mr-1"></i> Run for real
                        </button>
                    </div>
                </form>

                <div class="mt-3 small text-muted">
                    Always <strong>Dry-run first</strong> to see exactly which tables get wiped and how many rows each.
                </div>
            </div>
        </div>
    @endif

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
