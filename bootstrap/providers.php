<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    App\Providers\RouteServiceProvider::class,
    Barryvdh\DomPDF\ServiceProvider::class,
    App\Providers\ConfigServiceProvider::class,
    Unicodeveloper\Paystack\PaystackServiceProvider::class,
    Nwidart\Modules\LaravelModulesServiceProvider::class,
    App\Providers\FirebaseServiceProvider::class,
];
