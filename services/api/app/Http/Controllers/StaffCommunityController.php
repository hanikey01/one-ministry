<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StaffCommunityController extends Controller
{
    public function groups(): JsonResponse
    {
        return response()->json(['data' => DB::table('groups')->latest()->get()]);
    }

    public function group(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:160'], 'description' => ['nullable', 'string', 'max:5000']]);
        $id = (string) Str::uuid();
        DB::table('groups')->insert([...$data, 'id' => $id, 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => DB::table('groups')->where('id', $id)->first()], 201);
    }

    public function events(): JsonResponse
    {
        return response()->json(['data' => DB::table('events')->orderBy('starts_at')->get()]);
    }

    public function publicEvents(): JsonResponse
    {
        return response()->json(['data' => DB::table('events')->where('registration_open', true)->whereNull('cancelled_at')->where('starts_at', '>=', now())->orderBy('starts_at')->get(['id', 'title', 'description', 'starts_at', 'ends_at', 'location', 'capacity'])]);
    }

    public function event(Request $request): JsonResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:200'], 'description' => ['nullable', 'string'], 'starts_at' => ['required', 'date'], 'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'], 'location' => ['nullable', 'string', 'max:200'], 'capacity' => ['nullable', 'integer', 'min:1'], 'waitlist_enabled' => ['sometimes', 'boolean']]);
        $id = (string) Str::uuid();
        DB::table('events')->insert([...$data, 'id' => $id, 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => DB::table('events')->where('id', $id)->first()], 201);
    }

    public function announcements(): JsonResponse
    {
        $announcements = DB::table('announcements')->where('published', true)
            ->where(fn ($query) => $query->whereNull('publish_at')->orWhere('publish_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest()->get();

        return response()->json(['data' => $announcements]);
    }

    public function announcement(Request $request): JsonResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:200'], 'body' => ['required', 'string'], 'publish_at' => ['nullable', 'date'], 'expires_at' => ['nullable', 'date', 'after:publish_at']]);
        $id = (string) Str::uuid();
        DB::table('announcements')->insert([...$data, 'id' => $id, 'published' => true, 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => DB::table('announcements')->where('id', $id)->first()], 201);
    }
}
