<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Auth\Middleware\AuthenticateWithBasicAuth;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Auth\Middleware\RequirePassword;
use App\Http\Middleware\RedirectIfAuthenticated;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Http\Middleware\SetCacheHeaders;
use App\Http\Middleware\RateLimiterMiddleware;

use App\Http\Middleware\{ActivationCheckMiddleware,
    AdminMiddleware,
    ApiActiveCustomer,
    ApiActiveDeliveryMan,
    AppActivation,
    BranchAdder,
    BranchMiddleware,
    BranchStatusCheck,
    InstallationMiddleware,
    MaintenanceModeMiddleware,
    ModulePermissionMiddleware,
    TrustProxies,
    CheckForMaintenanceMode,
    TrimStrings,
    EncryptCookies,
    VerifyCsrfToken,
    Authenticate,
    Localization
    };

$app = Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->use([
            TrustProxies::class,
            CheckForMaintenanceMode::class,
            ValidatePostSize::class,
            TrimStrings::class,
            ConvertEmptyStringsToNull::class,
            HandleCors::class,
        ]);
        $middleware->group('web', [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
        ]);
        $middleware->group('api', [
            SubstituteBindings::class,
            RateLimiterMiddleware::class,
        ]);
        /*
        |--------------------------------------------------------------------------
        | Route Middleware (Aliases)
        |--------------------------------------------------------------------------
        */
        $middleware->alias([
            'auth' => Authenticate::class,
            'auth.basic' => AuthenticateWithBasicAuth::class,
            'bindings' => SubstituteBindings::class,
            'cache.headers' => SetCacheHeaders::class,
            'can' => Authorize::class,
            'guest' => RedirectIfAuthenticated::class,
            'password.confirm' => RequirePassword::class,
            'signed' => ValidateSignature::class,
            'throttle' => ThrottleRequests::class,
            'verified' => EnsureEmailIsVerified::class,
            'admin'=>AdminMiddleware::class,
            'branch'=>BranchMiddleware::class,
            'installation-check' => InstallationMiddleware::class,
            'actch' => ActivationCheckMiddleware::class,
            'localization' => Localization::class,
            'module' => ModulePermissionMiddleware::class,
            'app_activate'=>AppActivation::class,
            'is_active' => ApiActiveCustomer::class,
            'deliveryman_is_active' => ApiActiveDeliveryMan::class,
            'branch_status' => BranchStatusCheck::class,
            'branch_adder' => BranchAdder::class,
            'maintenance_mode' => MaintenanceModeMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // You can customize exception handling here if needed
    })
    ->create();

return $app;
