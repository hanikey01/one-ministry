<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StaffCampaignController extends Controller
{
    public function templates(): JsonResponse
    {
        return response()->json(['data' => DB::table('message_templates')->orderBy('name')->get()]);
    }

    public function createTemplate(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:160'], 'channel' => ['required', 'in:email,sms'], 'subject' => ['nullable', 'string', 'max:240'], 'body' => ['required', 'string', 'max:20000']]);
        $id = (string) Str::uuid();
        DB::table('message_templates')->insert([...$data, 'id' => $id, 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => DB::table('message_templates')->where('id', $id)->first()], 201);
    }

    public function campaigns(): JsonResponse
    {
        return response()->json(['data' => DB::table('campaigns')->latest()->paginate(50)]);
    }

    public function createCampaign(Request $request): JsonResponse
    {
        $data = $request->validate(['template_id' => ['required', 'uuid', 'exists:message_templates,id'], 'name' => ['required', 'string', 'max:160'], 'purpose' => ['required', 'in:operational,ministry_marketing'], 'scheduled_at' => ['nullable', 'date']]);
        $id = (string) Str::uuid();
        DB::table('campaigns')->insert([...$data, 'id' => $id, 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => DB::table('campaigns')->where('id', $id)->first()], 201);
    }

    public function queue(Request $request, string $campaign): JsonResponse
    {
        $campaignRecord = DB::table('campaigns')->where('id', $campaign)->first();
        abort_unless($campaignRecord, 404);
        $template = DB::table('message_templates')->where('id', $campaignRecord->template_id)->first();
        $preference = $template->channel === 'email' ? 'email_marketing' : 'sms_marketing';
        $people = DB::table('people')->join('notification_preferences', 'notification_preferences.person_id', '=', 'people.id')
            ->where("notification_preferences.{$preference}", true)->select('people.id')->get();
        foreach ($people as $person) {
            DB::table('message_deliveries')->insertOrIgnore([
                'id' => (string) Str::uuid(), 'campaign_id' => $campaign, 'person_id' => $person->id, 'channel' => $template->channel,
                'idempotency_key' => hash('sha256', $campaign.'|'.$person->id), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('campaigns')->where('id', $campaign)->update(['status' => 'queued', 'updated_at' => now()]);

        return response()->json(['data' => ['campaign_id' => $campaign, 'status' => 'queued', 'recipient_count' => $people->count()]]);
    }
}
