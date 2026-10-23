<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AddServerTiming
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        $response = $next($request);

        if (config('app.debug')) {
            $milliseconds = (hrtime(true) - $startedAt) / 1_000_000;
            $response->headers->set('Server-Timing', 'app;dur='.number_format($milliseconds, 1, '.', ''));
        }

        return $response;
    }
}
