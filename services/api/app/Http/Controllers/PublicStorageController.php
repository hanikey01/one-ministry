<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves explicitly public uploads when a host cannot expose Laravel's
 * public/storage symbolic link. A working link remains the faster path and
 * is handled by the web server before this route is reached.
 */
class PublicStorageController extends Controller
{
    public function show(string $path): StreamedResponse
    {
        $path = str_replace('\\', '/', rawurldecode($path));
        $segments = explode('/', trim($path, '/'));

        abort_if(
            $path === ''
            || str_contains($path, "\0")
            || collect($segments)->contains(fn (string $segment): bool => $segment === '' || $segment === '.' || $segment === '..'),
            404,
        );

        $disk = Storage::disk('public');
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            'Cache-Control' => 'public, max-age=86400, stale-while-revalidate=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
