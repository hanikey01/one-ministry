<?php

namespace App\Http\Controllers;

use App\Models\FeatureSetting;
use App\Models\ServiceOccurrence;
use App\Services\ConnectionRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServiceResponseController extends Controller
{
    public function connection(Request $request, ServiceOccurrence $service, ConnectionRequestService $connections): JsonResponse
    {
        $this->guard($service);
        $data = $request->validate([
            'kind' => ['required', 'string', 'in:newcomer,speak_to_someone,join_department,decision'],
            'name' => ['nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:254'],
            'phone' => ['nullable', 'string', 'max:40'],
            'consent_to_contact' => ['boolean'],
        ]);
        $key = $request->header('Idempotency-Key');
        abort_if($key !== null && strlen($key) > 80, 422, 'Idempotency-Key is too long.');
        $existing = $key ? DB::table('service_connection_responses')->where('service_occurrence_id', $service->id)->where('idempotency_key', $key)->first() : null;
        if ($existing) {
            return response()->json(['data' => ['id' => $existing->id, 'status' => 'already_received']]);
        }
        $email = filled($data['email'] ?? null) ? mb_strtolower(trim($data['email'])) : null;
        $person = $request->user() ? $connections->person($request) : $connections->existingPersonForEmail($email);
        $id = (string) Str::uuid();
        DB::table('service_connection_responses')->insert(['id' => $id, 'service_occurrence_id' => $service->id, 'person_id' => $person?->id, 'user_id' => $request->user()?->id, 'kind' => $data['kind'], 'name' => $data['name'] ?? null, 'email' => $email, 'phone' => $data['phone'] ?? null, 'consent_to_contact' => (bool) ($data['consent_to_contact'] ?? false), 'idempotency_key' => $key, 'created_at' => now()]);

        return response()->json(['data' => ['id' => $id, 'status' => 'received']], 201);
    }

    public function prayer(Request $request, ServiceOccurrence $service, ConnectionRequestService $connections): JsonResponse
    {
        $this->guard($service);
        $data = $request->validate(['prayer_text' => ['required', 'string', 'max:4000'], 'display_name' => ['nullable', 'string', 'max:160'], 'contact_email' => ['nullable', 'email', 'max:254'], 'consent_to_contact' => ['boolean']]);
        $key = $request->header('Idempotency-Key');
        $existing = $key ? DB::table('service_prayer_requests')->where('service_occurrence_id', $service->id)->where('idempotency_key', $key)->first() : null;
        if ($existing) {
            return response()->json(['data' => ['id' => $existing->id, 'status' => 'already_received']]);
        }
        $email = filled($data['contact_email'] ?? null) ? mb_strtolower(trim($data['contact_email'])) : null;
        $person = $request->user() ? $connections->person($request) : $connections->existingPersonForEmail($email);
        $id = (string) Str::uuid();
        DB::table('service_prayer_requests')->insert(['id' => $id, 'service_occurrence_id' => $service->id, 'person_id' => $person?->id, 'user_id' => $request->user()?->id, 'display_name' => $data['display_name'] ?? null, 'contact_email' => $email, 'prayer_text' => trim($data['prayer_text']), 'visibility' => 'prayer_team', 'consent_to_contact' => (bool) ($data['consent_to_contact'] ?? false), 'idempotency_key' => $key, 'created_at' => now()]);

        return response()->json(['data' => ['id' => $id, 'status' => 'received']], 201);
    }

    private function guard(ServiceOccurrence $service): void
    {
        abort_unless(FeatureSetting::query()->where('key', 'digital_church')->value('state') === 'enabled', 404);
        abort_unless(! $service->rehearsal_mode && in_array($service->state, ['live', 'interrupted'], true), 422, 'Responses are available during a live service.');
    }
}
