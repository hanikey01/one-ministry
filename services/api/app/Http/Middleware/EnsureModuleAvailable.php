<?php

namespace App\Http\Middleware;

use App\Services\ModuleAvailability;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleAvailable
{
    public function handle(Request $request, Closure $next): Response
    {
        $modules = app(ModuleAvailability::class);
        $key = $modules->requiredForPath($request->path());

        if ($key === null) {
            return $next($request);
        }

        $staffRoute = str_starts_with($request->path(), 'staff/') || str_starts_with($request->path(), 'api/v1/staff/');
        $available = $staffRoute
            ? $modules->visibleToStaff($key, $request->user())
            : $modules->enabled($key);

        abort_unless($available, 404);

        return $next($request);
    }
}
