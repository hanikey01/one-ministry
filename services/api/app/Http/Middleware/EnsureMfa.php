<?php

namespace App\Http\Middleware;

use App\Services\MfaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMfa
{
    public function __construct(private MfaService $mfa) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->mfa->enabled($request->user()) || (int) $request->session()->get('mfa_verified_at', 0) <= now()->subHours(12)->timestamp) {
            if (! $request->expectsJson()) {
                return to_route('staff.security')->with('security_notice', 'Set up two-factor authentication or verify this session to continue with this action.');
            }

            return response()->json(['error' => ['code' => 'mfa_required', 'message' => 'Multifactor verification is required for this action.', 'fields' => (object) []]], 403);
        }

        return $next($request);
    }
}
