<?php

namespace App\Http\Middleware;

use App\Services\PermissionChecker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function __construct(private PermissionChecker $permissions) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if ($request->user() === null) {
            return response()->json(['error' => ['code' => 'unauthenticated', 'message' => 'Please sign in.', 'fields' => (object) []]], 401);
        }

        if (! $this->permissions->allows($request->user(), $permission)) {
            return response()->json(['error' => ['code' => 'forbidden', 'message' => 'You do not have access to this action.', 'fields' => (object) []]], 403);
        }

        return $next($request);
    }
}
