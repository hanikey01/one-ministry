<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $current = $request->session()->getId();
        $sessions = DB::table('sessions')->where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')->get(['id', 'ip_address', 'user_agent', 'last_activity']);

        return response()->json(['data' => $sessions->map(fn ($session): array => [
            'id' => $this->handle($session->id),
            'ip_address' => $session->ip_address,
            'user_agent' => Str::limit($session->user_agent ?? '', 160),
            'last_activity' => gmdate(DATE_ATOM, $session->last_activity),
            'current' => hash_equals($session->id, $current),
        ])]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $sessions = DB::table('sessions')->where('user_id', $request->user()->id)->get(['id']);
        $session = $sessions->first(fn ($session): bool => hash_equals($this->handle($session->id), $id));
        if ($session === null) {
            abort(404);
        }

        DB::transaction(function () use ($request, $session, $id): void {
            DB::table('sessions')->where('id', $session->id)->where('user_id', $request->user()->id)->delete();
            DB::table('audit_events')->insert([
                'id' => (string) Str::uuid(), 'actor_user_id' => $request->user()->id,
                'action' => 'session.revoked', 'subject_type' => 'users',
                'subject_id' => $request->user()->id,
                'changes_json' => json_encode(['session_handle' => $id], JSON_THROW_ON_ERROR),
                'ip_address' => $request->ip(), 'created_at' => now(),
            ]);
        });
        if (hash_equals($session->id, $request->session()->getId())) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(null, 204);
    }

    public function destroyAll(Request $request): JsonResponse
    {
        DB::transaction(function () use ($request): void {
            $count = DB::table('sessions')->where('user_id', $request->user()->id)->delete();
            DB::table('audit_events')->insert([
                'id' => (string) Str::uuid(), 'actor_user_id' => $request->user()->id,
                'action' => 'sessions.revoked_all', 'subject_type' => 'users',
                'subject_id' => $request->user()->id,
                'changes_json' => json_encode(['count' => $count], JSON_THROW_ON_ERROR),
                'ip_address' => $request->ip(), 'created_at' => now(),
            ]);
        });
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(null, 204);
    }

    private function handle(string $id): string
    {
        return hash_hmac('sha256', $id, config('app.key'));
    }
}
