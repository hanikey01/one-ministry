<?php

namespace App\Http\Controllers;

use App\Models\ServiceOccurrence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CapacityRehearsalController extends Controller
{
    public function index(ServiceOccurrence $service): JsonResponse
    {
        return response()->json(['data' => DB::table('service_capacity_rehearsals')->where('service_occurrence_id', $service->id)->latest('created_at')->get()->map(fn ($row) => $this->present($row))]);
    }

    public function store(Request $request, ServiceOccurrence $service): JsonResponse
    {
        $data = $request->validate([
            'expected_peak_viewers' => ['required', 'integer', 'min:1', 'max:100000000'],
            'headroom_percent' => ['required', 'integer', 'min:0', 'max:200'],
            'geography' => ['required', 'string', 'max:255'],
            'provider_plan' => ['nullable', 'string', 'max:5000'],
            'target_startup_ms' => ['required', 'integer', 'min:100', 'max:120000'],
            'target_rebuffer_rate_bps' => ['required', 'integer', 'min:0', 'max:10000'],
            'target_error_rate_bps' => ['required', 'integer', 'min:0', 'max:10000'],
            'target_chat_latency_ms' => ['required', 'integer', 'min:50', 'max:120000'],
        ]);
        $id = (string) Str::uuid();
        $required = (int) ceil($data['expected_peak_viewers'] * (100 + $data['headroom_percent']) / 100);
        DB::transaction(function () use ($request, $service, $data, $id, $required): void {
            DB::table('service_capacity_rehearsals')->insert([...$data, 'id' => $id, 'service_occurrence_id' => $service->id, 'required_test_viewers' => $required, 'status' => 'planned', 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            $this->audit($request, 'capacity_rehearsal.created', $id, ['expected_peak_viewers' => $data['expected_peak_viewers'], 'required_test_viewers' => $required]);
        });

        return response()->json(['data' => $this->present(DB::table('service_capacity_rehearsals')->find($id))], 201);
    }

    public function start(Request $request, ServiceOccurrence $service, string $rehearsal): JsonResponse
    {
        $record = $this->record($service, $rehearsal);
        abort_unless($record->status === 'planned', 409, 'Only a planned rehearsal can be started.');
        DB::transaction(function () use ($request, $rehearsal): void {
            DB::table('service_capacity_rehearsals')->where('id', $rehearsal)->where('status', 'planned')->update(['status' => 'running', 'started_at' => now(), 'updated_at' => now()]);
            $this->audit($request, 'capacity_rehearsal.started', $rehearsal, []);
        });

        return response()->json(['data' => $this->present(DB::table('service_capacity_rehearsals')->find($rehearsal))]);
    }

    public function complete(Request $request, ServiceOccurrence $service, string $rehearsal): JsonResponse
    {
        $record = $this->record($service, $rehearsal);
        abort_unless($record->status === 'running', 409, 'Only a running rehearsal can be completed.');
        $data = $request->validate([
            'actual_peak_viewers' => ['required', 'integer', 'min:0', 'max:100000000'],
            'measured_p95_startup_ms' => ['required', 'integer', 'min:0', 'max:600000'],
            'measured_rebuffer_rate_bps' => ['required', 'integer', 'min:0', 'max:10000'],
            'measured_error_rate_bps' => ['required', 'integer', 'min:0', 'max:10000'],
            'measured_chat_p95_ms' => ['required', 'integer', 'min:0', 'max:600000'],
            'evidence_url' => ['nullable', 'url', 'max:1000'],
            'result_notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $criteria = $this->criteria($record, $data);
        $status = collect($criteria)->every(fn ($criterion) => $criterion['passed']) ? 'passed' : 'failed';
        DB::transaction(function () use ($request, $rehearsal, $data, $status, $criteria): void {
            DB::table('service_capacity_rehearsals')->where('id', $rehearsal)->where('status', 'running')->update([...$data, 'status' => $status, 'completed_by' => $request->user()->id, 'completed_at' => now(), 'updated_at' => now()]);
            $this->audit($request, 'capacity_rehearsal.completed', $rehearsal, ['status' => $status, 'criteria' => $criteria]);
        });

        return response()->json(['data' => $this->present(DB::table('service_capacity_rehearsals')->find($rehearsal))]);
    }

    private function record(ServiceOccurrence $service, string $id): object
    {
        $record = DB::table('service_capacity_rehearsals')->where('id', $id)->where('service_occurrence_id', $service->id)->first();
        abort_unless($record, 404);

        return $record;
    }

    private function criteria(object $record, array $measurements): array
    {
        return [
            'viewer_load' => ['actual' => $measurements['actual_peak_viewers'], 'target' => $record->required_test_viewers, 'operator' => '>=', 'passed' => $measurements['actual_peak_viewers'] >= $record->required_test_viewers],
            'startup' => ['actual' => $measurements['measured_p95_startup_ms'], 'target' => $record->target_startup_ms, 'operator' => '<=', 'passed' => $measurements['measured_p95_startup_ms'] <= $record->target_startup_ms],
            'rebuffer_rate' => ['actual' => $measurements['measured_rebuffer_rate_bps'], 'target' => $record->target_rebuffer_rate_bps, 'operator' => '<=', 'passed' => $measurements['measured_rebuffer_rate_bps'] <= $record->target_rebuffer_rate_bps],
            'error_rate' => ['actual' => $measurements['measured_error_rate_bps'], 'target' => $record->target_error_rate_bps, 'operator' => '<=', 'passed' => $measurements['measured_error_rate_bps'] <= $record->target_error_rate_bps],
            'chat_latency' => ['actual' => $measurements['measured_chat_p95_ms'], 'target' => $record->target_chat_latency_ms, 'operator' => '<=', 'passed' => $measurements['measured_chat_p95_ms'] <= $record->target_chat_latency_ms],
        ];
    }

    private function present(object $record): array
    {
        $payload = (array) $record;
        $payload['criteria'] = in_array($record->status, ['passed', 'failed'], true) ? $this->criteria($record, $payload) : null;

        return $payload;
    }

    private function audit(Request $request, string $action, string $subjectId, array $changes): void
    {
        DB::table('audit_events')->insert(['id' => (string) Str::uuid(), 'actor_user_id' => $request->user()->id, 'action' => $action, 'subject_type' => 'service_capacity_rehearsals', 'subject_id' => $subjectId, 'changes_json' => json_encode($changes), 'ip_address' => $request->ip(), 'created_at' => now()]);
    }
}
