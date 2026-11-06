<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffReportController extends Controller
{
    public function show(Request $request, string $report): JsonResponse
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $from = $request->date('from')?->startOfDay() ?? now()->subDays(30)->startOfDay();
        $to = $request->date('to')?->endOfDay() ?? now()->endOfDay();

        $data = match ($report) {
            'services' => [
                'definition' => 'Confirmed identified attendance and anonymous broadcast viewer buckets remain separate.',
                'identified_attendance' => DB::table('attendance_records')->whereBetween('observed_at', [$from, $to])->where('status', 'present')->count(),
                'anonymous_viewer_buckets' => DB::table('anonymous_viewer_aggregates')->whereBetween('bucket_started_at', [$from, $to])->sum('viewer_count'),
                'service_count' => DB::table('service_occurrences')->whereBetween('starts_at', [$from, $to])->count(),
            ],
            'follow_up' => [
                'definition' => 'Open and completed follow-up tasks in the selected period.',
                'open' => DB::table('follow_up_tasks')->where('status', '!=', 'completed')->count(),
                'completed' => DB::table('follow_up_tasks')->whereBetween('completed_at', [$from, $to])->count(),
                'overdue' => DB::table('follow_up_tasks')->where('status', '!=', 'completed')->where('due_at', '<', now())->count(),
            ],
            'departments' => [
                'definition' => 'Active departments and their current membership count.',
                'active_departments' => DB::table('departments')->where('status', 'active')->count(),
                'memberships' => DB::table('department_memberships')->where('status', 'active')->count(),
                'events_registered' => DB::table('event_registrations')->whereBetween('created_at', [$from, $to])->where('status', 'registered')->count(),
            ],
            'giving' => [
                'definition' => 'Confirmed giving totals. Donor identity is not included in this report.',
                'confirmed_count' => DB::table('donations')->where('status', 'confirmed')->whereBetween('confirmed_at', [$from, $to])->count(),
                'confirmed_amount_minor' => DB::table('donations')->where('status', 'confirmed')->whereBetween('confirmed_at', [$from, $to])->sum('amount_minor'),
            ],
            default => abort(404),
        };

        return response()->json(['data' => ['report' => $report, 'from' => $from->toIso8601String(), 'to' => $to->toIso8601String(), 'metrics' => $data]]);
    }
}
