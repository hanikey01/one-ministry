<?php

use App\Http\Middleware\EnsureMfa;
use App\Http\Middleware\EnsureModuleAvailable;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureVerifiedEmail;
use App\Http\Middleware\GuardPublicSubmission;
use App\Http\Middleware\AddServerTiming;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$application = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('web', AddServerTiming::class);
        $middleware->alias(['permission' => EnsurePermission::class, 'mfa' => EnsureMfa::class, 'verified_email' => EnsureVerifiedEmail::class, 'public_form_guard' => GuardPublicSubmission::class]);
        $middleware->appendToGroup('web', EnsureModuleAvailable::class);
        $middleware->appendToGroup('api', EnsureModuleAvailable::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

// Shared-hosting packages place the public entry files and compiled assets
// directly in the hosting document root instead of a nested public/ folder.
$basePath = dirname(__DIR__);
if (is_file($basePath.'/build/manifest.json') && ! is_file($basePath.'/public/build/manifest.json')) {
    $application->usePublicPath($basePath);
}

return $application;
