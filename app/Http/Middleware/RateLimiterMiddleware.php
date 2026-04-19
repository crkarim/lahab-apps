<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimiterMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->ip(), ['127.0.0.1', '::1']) || env('APP_MODE') == 'demo') {
            // Skip rate limiting for local or demo
            return $next($request);
        }

        [$limit, $delaySeconds] = $this->resolveLimit($request);

        if ($limit === 0) {
            return $next($request);
        }

        $key = 'limiter:' . ($request->user()?->id ?: $request->ip()) . ':' . $request->route()?->getName();

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            $seconds = RateLimiter::availableIn($key);

            $errors = [];
            $errors[] = ['code' => 'api-limit', 'message' => translate('Too many requests. Please slow down. Retry after ') . $seconds. translate(' seconds.')];
            return response()->json(['errors' => $errors], 429);
        }

        RateLimiter::hit($key, $delaySeconds);

        $response = $next($request);

        $response->headers->add([
            'X-RateLimit-Limit' => $limit,
            'X-RateLimit-Remaining' => RateLimiter::remaining($key, $limit),
        ]);

        return $response;

    }

    protected function resolveLimit(Request $request): array
    {
        $routeName = $request->route()?->getName() ?: $request->path();

        return match ($routeName) {

            'api.v1.auth.registration' => [6, 60],
            'api.v1.auth.login' => [6, 60],
            'api.v1.password.request' => [6, 60],
            'api.v1.password.update' => [6, 60],

            'api.v1.delivery-man.register' => [6, 60],
            'api.v1.delivery-man.login' => [6, 60],

            'api.v1.order.place' => [6, 60],
            'api.v1.table.order.place' => [6, 60],

            'api.v1.product.review.submit' => [6, 60],

            'api.v1.subscribe.newsletter' => [6, 60],

            default => [180, 60],
        };
    }
}
