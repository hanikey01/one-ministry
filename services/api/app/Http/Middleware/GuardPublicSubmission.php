<?php

namespace App\Http\Middleware;

use App\Services\PublicSubmissionGuard;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class GuardPublicSubmission
{
    /** @var array<string, int> */
    private const LIMITS = [
        'register.store' => 5,
        'register.verify.store' => 8,
        'register.resend' => 3,
        'register.complete.store' => 5,
        'password.email' => 3,
        'public.contact.submit' => 5,
        'member.prayer' => 5,
        'member.next-steps.request' => 5,
        'member.testimonies.store' => 3,
        'public.events.register' => 8,
        'api.password.reset-request' => 5,
        'api.email.verification-request' => 5,
    ];

    public function __construct(private readonly PublicSubmissionGuard $guard) {}

    public function handle(Request $request, Closure $next, string $profile = 'public'): Response
    {
        if ($profile === 'start') {
            $request->session()->put('public_form_started_at', now()->timestamp);

            return $next($request);
        }

        if ($profile === 'public' && $request->user()) {
            return $next($request);
        }

        $routeName = (string) ($request->route()?->getName() ?: $request->path());
        $fingerprint = hash_hmac('sha256', implode('|', [
            (string) $request->ip(),
            substr((string) $request->userAgent(), 0, 255),
        ]), (string) config('app.key'));
        $rateKey = 'public-form-rate:'.$routeName.':'.$fingerprint;
        $limit = self::LIMITS[$routeName] ?? 5;

        if (RateLimiter::tooManyAttempts($rateKey, $limit)) {
            return $this->blocked($request, $routeName, $fingerprint, 'rate_limited', 429);
        }
        RateLimiter::hit($rateKey, 60);

        if ((int) $request->server('CONTENT_LENGTH', 0) > 128 * 1024 || strlen($request->getContent()) > 128 * 1024) {
            return $this->blocked($request, $routeName, $fingerprint, 'payload_too_large', 413);
        }

        if ($profile !== 'rate') {
            if (filled($request->input('website'))) {
                return $this->blocked($request, $routeName, $fingerprint, 'honeypot');
            }
            $startedAt = $this->guard->startedAt((string) $request->input('_form_started_at'), $routeName)
                ?? $request->session()->get('public_form_started_at');
            $age = $startedAt === null ? null : now()->timestamp - (int) $startedAt;
            if ($age === null || $age < 2 || $age > 14_400) {
                return $this->blocked($request, $routeName, $fingerprint, 'completion_time');
            }
        }

        $payload = $request->except(['_token', '_form_started_at', 'website', 'password', 'password_confirmation', 'token', 'code']);
        $duplicateKey = 'public-form-duplicate:'.hash_hmac('sha256', $routeName.'|'.$fingerprint.'|'.json_encode($payload), (string) config('app.key'));
        if ($profile !== 'rate' && Cache::has($duplicateKey)) {
            return $this->blocked($request, $routeName, $fingerprint, 'duplicate');
        }

        $response = $next($request);
        if ($profile !== 'rate' && $response->getStatusCode() < 400 && ! $request->session()->has('errors')) {
            Cache::put($duplicateKey, true, now()->addMinutes(2));
        }

        return $response;
    }

    private function blocked(Request $request, string $routeName, string $fingerprint, string $reason, int $status = 422): Response
    {
        Log::notice('Public submission blocked.', [
            'route' => $routeName,
            'reason' => $reason,
            'fingerprint' => substr($fingerprint, 0, 20),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'We could not accept this request. Please wait and try again.'], $status);
        }

        throw ValidationException::withMessages(['form' => 'We could not accept this request. Please wait and try again.']);
    }
}
