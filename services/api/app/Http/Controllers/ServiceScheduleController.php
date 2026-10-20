<?php

namespace App\Http\Controllers;

use App\Models\ServiceOccurrence;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ServiceScheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $from = CarbonImmutable::parse($request->string('from', now()->subMonth()->toDateString())->toString())->startOfDay();
        $to = CarbonImmutable::parse($request->string('to', now()->addMonths(3)->toDateString())->toString())->endOfDay();

        return response()->json(['data' => ['schedules' => DB::table('service_schedules')->orderBy('title')->get(), 'occurrences' => ServiceOccurrence::query()->whereBetween('starts_at', [$from, $to])->orderBy('starts_at')->get()]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:160'], 'service_type' => ['required', Rule::in(['broadcast', 'meeting', 'in_person', 'hybrid'])], 'timezone' => ['required', 'timezone'], 'frequency' => ['required', Rule::in(['weekly', 'monthly'])], 'interval' => ['sometimes', 'integer', 'min:1', 'max:12'], 'weekdays' => ['nullable', 'array'], 'weekdays.*' => ['integer', 'between:0,6'], 'day_of_month' => ['nullable', 'integer', 'between:1,28'], 'starts_on' => ['required', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'], 'starts_at_local' => ['required', 'date_format:H:i'], 'duration_minutes' => ['sometimes', 'integer', 'min:15', 'max:720'], 'location_id' => ['nullable', 'uuid', 'exists:locations,id'], 'service_template_id' => ['nullable', 'uuid', 'exists:service_templates,id']]);
        if ($data['frequency'] === 'weekly' && empty($data['weekdays'])) {
            return response()->json(['error' => ['code' => 'weekday_required', 'message' => 'Choose at least one weekday.']], 422);
        }
        if ($data['frequency'] === 'monthly' && empty($data['day_of_month'])) {
            return response()->json(['error' => ['code' => 'day_required', 'message' => 'Choose a day of the month.']], 422);
        }
        $id = (string) Str::uuid();
        DB::table('service_schedules')->insert([...$data, 'id' => $id, 'interval' => $data['interval'] ?? 1, 'duration_minutes' => $data['duration_minutes'] ?? 90, 'weekdays' => isset($data['weekdays']) ? json_encode(array_values($data['weekdays'])) : null, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $schedule = DB::table('service_schedules')->where('id', $id)->first();
        $this->generate($schedule, CarbonImmutable::parse($data['starts_on']), CarbonImmutable::parse($data['starts_on'])->addWeeks(12));

        return response()->json(['data' => $schedule], 201);
    }

    public function oneTime(Request $request): JsonResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:160'], 'service_type' => ['required', Rule::in(['broadcast', 'meeting', 'in_person', 'hybrid'])], 'timezone' => ['required', 'timezone'], 'starts_at' => ['required', 'date'], 'ends_at' => ['nullable', 'date', 'after:starts_at'], 'location_id' => ['nullable', 'uuid', 'exists:locations,id']]);

        return response()->json(['data' => ServiceOccurrence::create($data)], 201);
    }

    public function override(Request $request, ServiceOccurrence $service): JsonResponse
    {
        $data = $request->validate(['title' => ['sometimes', 'string', 'max:160'], 'starts_at' => ['sometimes', 'date'], 'ends_at' => ['nullable', 'date'], 'state' => ['sometimes', Rule::in(['scheduled', 'cancelled'])]]);
        $service->update([...$data, 'schedule_override' => true]);

        return response()->json(['data' => $service->fresh()]);
    }

    private function generate(object $schedule, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $cursor = CarbonImmutable::parse($schedule->starts_on, $schedule->timezone)->startOfDay()->max($from->setTimezone($schedule->timezone)->startOfDay());
        $last = $schedule->ends_on ? CarbonImmutable::parse($schedule->ends_on, $schedule->timezone)->endOfDay()->min($to->setTimezone($schedule->timezone)->endOfDay()) : $to->setTimezone($schedule->timezone)->endOfDay();
        for (; $cursor->lte($last); $cursor = $cursor->addDay()) {
            $weekly = $schedule->frequency === 'weekly' && in_array($cursor->dayOfWeek, json_decode($schedule->weekdays ?? '[]', true), true) && $cursor->diffInWeeks(CarbonImmutable::parse($schedule->starts_on, $schedule->timezone)) % $schedule->interval === 0;
            $monthly = $schedule->frequency === 'monthly' && $cursor->day === $schedule->day_of_month && $cursor->diffInMonths(CarbonImmutable::parse($schedule->starts_on, $schedule->timezone)) % $schedule->interval === 0;
            if (! $weekly && ! $monthly) {
                continue;
            }
            $starts = $cursor->setTimeFromTimeString($schedule->starts_at_local)->utc();
            if (ServiceOccurrence::query()->where('service_schedule_id', $schedule->id)->where('starts_at', $starts)->exists()) {
                continue;
            }
            ServiceOccurrence::create(['service_schedule_id' => $schedule->id, 'service_template_id' => $schedule->service_template_id, 'location_id' => $schedule->location_id, 'title' => $schedule->title, 'service_type' => $schedule->service_type, 'timezone' => $schedule->timezone, 'starts_at' => $starts, 'ends_at' => $starts->addMinutes($schedule->duration_minutes), 'state' => 'scheduled']);
        }
    }
}
