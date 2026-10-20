<?php

namespace App\Http\Controllers;

use App\Models\FeatureSetting;
use App\Models\ServiceOccurrence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServicePollController extends Controller
{
    public function staffIndex(ServiceOccurrence $service): JsonResponse
    {
        return response()->json(['data' => $this->polls($service, true)]);
    }

    public function store(Request $request, ServiceOccurrence $service): JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'options' => ['required', 'array', 'min:2', 'max:8'],
            'options.*' => ['required', 'string', 'max:240'],
            'anonymous' => ['sometimes', 'boolean'],
        ]);
        $id = (string) Str::uuid();
        DB::transaction(function () use ($data, $service, $id): void {
            DB::table('service_polls')->insert(['id' => $id, 'service_occurrence_id' => $service->id, 'question' => $data['question'], 'anonymous' => $data['anonymous'] ?? true, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($data['options'] as $position => $label) {
                DB::table('service_poll_options')->insert(['id' => (string) Str::uuid(), 'service_poll_id' => $id, 'label' => $label, 'position' => $position]);
            }
        });

        return response()->json(['data' => $this->poll($id, true)], 201);
    }

    public function transition(Request $request, ServiceOccurrence $service, string $poll): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:open,closed']]);
        $query = DB::table('service_polls')->where('id', $poll)->where('service_occurrence_id', $service->id);
        abort_unless($query->exists(), 404);
        $query->update(['status' => $data['status'], $data['status'] === 'open' ? 'opened_at' : 'closed_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => $this->poll($poll, true)]);
    }

    public function publicIndex(ServiceOccurrence $service): JsonResponse
    {
        $this->guard($service);

        return response()->json(['data' => $this->polls($service, false)]);
    }

    public function respond(Request $request, ServiceOccurrence $service, string $poll): JsonResponse
    {
        $this->guard($service);
        $data = $request->validate(['option_id' => ['required', 'uuid', 'exists:service_poll_options,id'], 'guest_key' => ['nullable', 'string', 'min:16', 'max:200']]);
        $record = DB::table('service_polls')->where('id', $poll)->where('service_occurrence_id', $service->id)->where('status', 'open')->first();
        abort_unless($record, 404);
        abort_unless(DB::table('service_poll_options')->where('id', $data['option_id'])->where('service_poll_id', $poll)->exists(), 422, 'Option does not belong to this poll.');
        $guestHash = $request->user() ? null : hash('sha512', (string) $data['guest_key']);
        abort_if(! $request->user() && empty($data['guest_key']), 422, 'A guest key is required.');
        $existing = DB::table('service_poll_responses')->where('service_poll_id', $poll)->where($request->user() ? 'user_id' : 'guest_key_hash', $request->user()?->id ?? $guestHash)->exists();
        abort_if($existing, 409, 'You have already responded to this poll.');
        DB::table('service_poll_responses')->insert(['id' => (string) Str::uuid(), 'service_poll_id' => $poll, 'service_poll_option_id' => $data['option_id'], 'user_id' => $request->user()?->id, 'guest_key_hash' => $guestHash, 'created_at' => now()]);

        return response()->json(['data' => $this->poll($poll, false)], 201);
    }

    private function polls(ServiceOccurrence $service, bool $staff): array
    {
        $query = DB::table('service_polls')->where('service_occurrence_id', $service->id);
        if (! $staff) {
            $query->whereIn('status', ['open', 'closed']);
        }

        return $query->orderByDesc('created_at')->get()->map(fn (object $poll) => $this->poll($poll->id, $staff))->all();
    }

    private function poll(string $id, bool $staff): object
    {
        $poll = DB::table('service_polls')->where('id', $id)->first();
        $options = DB::table('service_poll_options')->where('service_poll_id', $id)->orderBy('position')->get()->map(function (object $option) use ($id, $staff, $poll): array {
            $item = ['id' => $option->id, 'label' => $option->label];
            if ($staff || $poll->status === 'closed') {
                $item['responses'] = DB::table('service_poll_responses')->where('service_poll_id', $id)->where('service_poll_option_id', $option->id)->count();
            }

            return $item;
        });

        return (object) ['id' => $poll->id, 'question' => $poll->question, 'status' => $poll->status, 'anonymous' => $poll->anonymous, 'options' => $options];
    }

    private function guard(ServiceOccurrence $service): void
    {
        abort_unless(FeatureSetting::query()->where('key', 'digital_church')->value('state') === 'enabled', 404);
        abort_unless(! $service->rehearsal_mode && $service->state === 'live', 404);
    }
}
