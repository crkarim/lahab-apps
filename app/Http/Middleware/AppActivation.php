<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AppActivation
{
    public function handle(Request $request, Closure $next, $app_id = null)
    {
        return $next($request);
    }
}
