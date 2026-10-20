<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVerifiedEmail
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()->email_verified_at === null) {
            if (! $request->expectsJson()) {
                return to_route('staff.security')->with('security_notice', 'Verify your staff email before managing staff access.');
            }

            return response()->json(['error' => ['code' => 'email_verification_required', 'message' => 'Verify your email before using this action.', 'fields' => (object) []]], 403);
        }

        return $next($request);
    }
}
