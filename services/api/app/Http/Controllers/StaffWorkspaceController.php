<?php

namespace App\Http\Controllers;

use App\Actions\UpdateMinistrySettings;
use App\Models\FeatureSetting;
use App\Models\MinistrySetting;
use App\Models\Location;
use App\Models\ServiceOccurrence;
use App\Services\AdministrationService;
use App\Services\EmailDeliverySettingsService;
use App\Services\EmailVerificationService;
use App\Services\LiveStreamService;
use App\Services\MfaService;
use App\Services\PermissionChecker;
use App\Services\StaffInvitationService;
use App\Services\ServiceLifecycle;
use App\Services\ZoomVideoSdkSettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StaffWorkspaceController extends Controller
{
    public function overview(Request $request): View
    {
        $settings = MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID);
        $modules = app(\App\Services\ModuleAvailability::class);
        $visibleModules = [
            'people' => $modules->visibleToStaff('people', $request->user()),
            'digital_church' => $modules->visibleToStaff('digital_church', $request->user()),
        ];

        return view('staff.overview', [
            'settings' => $settings,
            'user' => $request->user(),
            'visibleModules' => $visibleModules,
            'metrics' => [
                'people' => $visibleModules['people'] ? DB::table('people')->count() : null,
                'activeDepartments' => DB::table('departments')->where('status', 'active')->count(),
                'openFollowUps' => $visibleModules['people'] ? DB::table('follow_up_tasks')->where('status', 'open')->count() : null,
                'upcomingServices' => $visibleModules['digital_church'] ? DB::table('service_occurrences')->whereIn('state', ['scheduled', 'rehearsing', 'live'])->count() : null,
            ],
        ]);
    }

    public function settings(Request $request): View
    {
        $zoom = app(ZoomVideoSdkSettingsService::class)->setting();
        $homepagePages = DB::table('pages')->where('status', 'published')->orderBy('title')->get(['id', 'title', 'slug']);

        return view('staff.settings', ['settings' => MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID), 'homepagePages' => $homepagePages, 'user' => $request->user(), 'timezones' => timezone_identifiers_list(), 'zoomVideoSdk' => $zoom]);
    }

    public function storeNextStepOption(Request $request): RedirectResponse
    {
        $data = $request->validate(['label' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:500']]);
        $key = Str::slug($data['label'], '_');
        $key = substr($key, 0, 40);
        if ($key === '' || DB::table('next_step_options')->where('key', $key)->exists()) {
            return to_route('staff.follow-up')->withErrors(['label' => 'Use a unique next-step name.']);
        }
        DB::table('next_step_options')->insert(['id' => (string) Str::uuid(), 'key' => $key, 'label' => trim($data['label']), 'description' => filled($data['description'] ?? null) ? trim($data['description']) : null, 'active' => true, 'display_order' => (int) DB::table('next_step_options')->max('display_order') + 1, 'created_at' => now(), 'updated_at' => now()]);

        return to_route('staff.follow-up')->with('status', 'Next-step option added.');
    }

    public function updateNextStepOption(Request $request, string $option): RedirectResponse
    {
        $data = $request->validate(['label' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:500'], 'active' => ['nullable', 'boolean'], 'display_order' => ['required', 'integer', 'min:0', 'max:9999']]);
        DB::table('next_step_options')->where('id', $option)->update(['label' => trim($data['label']), 'description' => filled($data['description'] ?? null) ? trim($data['description']) : null, 'active' => $request->boolean('active'), 'display_order' => $data['display_order'], 'updated_at' => now()]);

        return to_route('staff.follow-up')->with('status', 'Next-step option updated.');
    }

    public function destroyNextStepOption(string $option): RedirectResponse
    {
        DB::table('next_step_options')->where('id', $option)->update(['active' => false, 'updated_at' => now()]);

        return to_route('staff.follow-up')->with('status', 'Next-step option hidden from members.');
    }

    public function updateHomepage(Request $request): RedirectResponse
    {
        $data = $request->validate(['homepage_page_id' => ['nullable', 'uuid', Rule::exists('pages', 'id')->where(fn ($query) => $query->where('status', 'published'))]]);
        $settings = MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID);
        $settings->forceFill(['homepage_page_id' => $data['homepage_page_id'] ?? null])->save();

        return to_route('staff.settings')->with('status', 'Homepage selection saved.');
    }

    public function people(Request $request): View
    {
        $settings = MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID);
        $search = trim((string) $request->query('q'));
        $people = DB::table('people')
            ->when($search !== '', fn ($query) => $query->where(function ($inner) use ($search): void {
                $inner->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            }))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('staff.people', compact('settings', 'people', 'search') + ['user' => $request->user()]);
    }

    public function person(Request $request, string $person): View
    {
        $settings = MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID);
        $record = DB::table('people')->where('id', $person)->first();
        abort_unless($record, 404);
        $departments = DB::table('department_memberships')->join('departments', 'departments.id', '=', 'department_memberships.department_id')->where('department_memberships.person_id', $person)->where('department_memberships.status', 'active')->get(['departments.name', 'department_memberships.role']);
        $followUp = DB::table('follow_up_tasks')->where('person_id', $person)->latest()->limit(25)->get();
        return view('staff.person', compact('settings', 'record', 'departments', 'followUp') + ['user' => $request->user()]);
    }

    public function updatePersonPage(Request $request, string $person): RedirectResponse
    {
        $data = $request->validate(['first_name' => ['required', 'string', 'max:100'], 'last_name' => ['nullable', 'string', 'max:100'], 'email' => ['nullable', 'email', 'max:254'], 'phone' => ['nullable', 'string', 'max:40']]);
        $query = DB::table('people')->where('id', $person);
        abort_unless($query->exists(), 404);
        $query->update([...$data, 'status' => 'member', 'updated_at' => now()]);
        return to_route('staff.people.person', $person)->with('status', 'Person updated.');
    }

    public function services(Request $request): View
    {
        $settings = MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID);
        $interactiveMeetingsEnabled = FeatureSetting::query()->where(['key' => 'interactive_meetings', 'state' => 'enabled'])->exists();
        $recurringServices = DB::table('service_schedules')
            ->leftJoin('locations', 'locations.id', '=', 'service_schedules.location_id')
            ->select('service_schedules.*', 'locations.name as location_name')
            ->selectSub(function ($query): void {
                $query->from('service_occurrences')
                    ->select('id')
                    ->whereColumn('service_schedule_id', 'service_schedules.id')
                    ->whereIn('state', ['scheduled', 'ready', 'rehearsing', 'live'])
                    ->where('starts_at', '>=', now())
                    ->orderBy('starts_at')
                    ->limit(1);
            }, 'next_occurrence_id')
            ->selectSub(function ($query): void {
                $query->from('service_occurrences')
                    ->select('starts_at')
                    ->whereColumn('service_schedule_id', 'service_schedules.id')
                    ->whereIn('state', ['scheduled', 'ready', 'rehearsing', 'live'])
                    ->where('starts_at', '>=', now())
                    ->orderBy('starts_at')
                    ->limit(1);
            }, 'next_starts_at')
            ->orderByDesc('active')
            ->orderBy('starts_on')
            ->get();

        $oneTimeServices = DB::table('service_occurrences')
            ->leftJoin('locations', 'locations.id', '=', 'service_occurrences.location_id')
            ->select('service_occurrences.*', 'locations.name as location_name')
            ->whereNull('service_occurrences.service_schedule_id')
            ->orderBy('starts_at')
            ->paginate(15);

        $locations = DB::table('locations')->where('is_public', true)->orderBy('name')->get(['id', 'name']);

        return view('staff.services', compact('settings', 'recurringServices', 'oneTimeServices', 'locations', 'interactiveMeetingsEnabled') + ['user' => $request->user()]);
    }

    public function serviceDesk(Request $request, ServiceOccurrence $service): View
    {
        $settings = MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID);
        $session = DB::table('live_sessions')->where('service_occurrence_id', $service->id)->first();
        $meeting = DB::table('meetings')->where('service_occurrence_id', $service->id)->first();
        $zoomReady = app(ZoomVideoSdkSettingsService::class)->ready();
        $interactiveMeetingsEnabled = FeatureSetting::query()->where(['key' => 'interactive_meetings', 'state' => 'enabled'])->exists();
        $events = $session ? DB::table('live_stream_events')->where('live_session_id', $session->id)->latest('created_at')->limit(20)->get() : collect();
        $playbackConfigUrl = match ($session?->provider) {
            'youtube' => $session?->provider_stream_id ? 'https://www.youtube.com/watch?v='.$session->provider_stream_id : null,
            'vimeo' => $session?->provider_stream_id ? 'https://vimeo.com/'.$session->provider_stream_id : null,
            default => $session?->playback_url,
        };

        $nextStates = match ($service->state) {
            'draft' => ['scheduled', 'cancelled'],
            'scheduled' => ['ready', 'cancelled'],
            'ready' => ['live', 'cancelled'],
            'live' => ['interrupted', 'ended'],
            'interrupted' => ['live', 'ended'],
            default => [],
        };

        return view('staff.service-desk', compact('settings', 'service', 'session', 'meeting', 'zoomReady', 'interactiveMeetingsEnabled', 'events', 'playbackConfigUrl', 'nextStates') + ['user' => $request->user()]);
    }

    public function createServiceMeeting(Request $request, ServiceOccurrence $service): RedirectResponse
    {
        if (! FeatureSetting::query()->where(['key' => 'interactive_meetings', 'state' => 'enabled'])->exists()) {
            return to_route('staff.services.show', $service)->with('status', 'Enable the Interactive Meetings module before creating a Zoom room.');
        }

        if (! app(ZoomVideoSdkSettingsService::class)->ready()) {
            return to_route('staff.settings')->with('status', 'Configure and activate Zoom Video SDK before creating an interactive service room.');
        }

        $existing = DB::table('meetings')->where('service_occurrence_id', $service->id)->first();
        if ($existing) {
            return to_route('meetings.show', $existing->id);
        }

        $id = (string) Str::uuid();
        DB::transaction(function () use ($id, $request, $service): void {
            DB::table('meetings')->insert([
                'id' => $id, 'created_by' => $request->user()->id, 'service_occurrence_id' => $service->id,
                'title' => $service->title.' interactive room', 'provider' => 'zoom_video_sdk',
                'session_name' => 'ministry-'.substr(str_replace('-', '', $id), 0, 28),
                'session_passcode_encrypted' => Crypt::encryptString(Str::password(12, true, true, false, false)),
                'status' => 'scheduled', 'starts_at' => $service->starts_at, 'ends_at' => $service->ends_at,
                'access_policy' => 'member_only', 'entry_policy' => 'waiting_room',
                'participant_media_policy' => 'camera_mic_allowed', 'screen_share_policy' => 'presenter_host_only',
                'chat_policy' => 'enabled', 'recording_notice' => true, 'recording_policy' => 'off', 'breakouts_enabled' => false,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('meeting_participants')->insert([
                'id' => (string) Str::uuid(), 'meeting_id' => $id, 'user_id' => $request->user()->id, 'display_name' => $request->user()->name,
                'role' => 'host', 'admission_status' => 'admitted', 'provider_identity' => substr((string) $request->user()->id, 0, 36),
                'access_token_hash' => hash('sha512', Str::random(48)), 'expires_at' => now()->addHours(8), 'admitted_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('audit_events')->insert([
                'id' => (string) Str::uuid(), 'actor_user_id' => $request->user()->id, 'action' => 'meeting.created_from_service',
                'subject_type' => 'meeting', 'subject_id' => $id, 'changes_json' => json_encode(['service_occurrence_id' => $service->id]),
                'ip_address' => $request->ip(), 'created_at' => now(),
            ]);
        });

        return to_route('staff.services.show', $service)->with('status', 'Zoom interactive room created for this service.');
    }

    public function updateServiceExperience(Request $request, ServiceOccurrence $service): RedirectResponse
    {
        $data = $request->validate(['service_type' => ['required', Rule::in(['broadcast', 'meeting', 'hybrid', 'in_person'])]]);
        if (in_array($data['service_type'], ['meeting', 'hybrid'], true) && ! FeatureSetting::query()->where(['key' => 'interactive_meetings', 'state' => 'enabled'])->exists()) {
            return to_route('staff.services.show', $service)->with('status', 'Enable the Interactive Meetings module before selecting a Zoom room experience.');
        }
        $before = $service->service_type;
        $service->update($data);
        DB::table('audit_events')->insert([
            'id' => (string) Str::uuid(), 'actor_user_id' => $request->user()->id, 'action' => 'service.experience_changed',
            'subject_type' => 'service_occurrence', 'subject_id' => $service->id,
            'changes_json' => json_encode(['before' => $before, 'after' => $data['service_type']]), 'ip_address' => $request->ip(), 'created_at' => now(),
        ]);

        return to_route('staff.services.show', $service)->with('status', 'Service experience updated.');
    }

    public function configureStream(Request $request, ServiceOccurrence $service, LiveStreamService $streams): RedirectResponse
    {
        $data = $request->validate(['provider' => ['required', Rule::in(['youtube', 'vimeo', 'hls'])], 'playback_url' => ['required', 'url', 'max:1000'], 'lower_bandwidth_url' => ['nullable', 'url', 'max:1000'], 'captions_url' => ['nullable', 'url', 'max:1000'], 'fallback_message' => ['nullable', 'string', 'max:1000']]);
        $streams->configure($service, $data, $request->user());

        return to_route('staff.services.show', $service)->with('status', 'Playback configuration saved.');
    }

    public function transitionService(Request $request, ServiceOccurrence $service, ServiceLifecycle $lifecycle): RedirectResponse
    {
        $data = $request->validate(['state' => ['required', Rule::in(['scheduled', 'ready', 'live', 'interrupted', 'ended', 'cancelled'])]]);
        $lifecycle->transition($service, $data['state'], $request->user(), $request->ip());

        return to_route('staff.services.show', $service)->with('status', 'Service state updated.');
    }

    public function updateStreamHealth(Request $request, ServiceOccurrence $service, LiveStreamService $streams): RedirectResponse
    {
        $data = $request->validate([
            'ingest_health' => ['required', Rule::in(['unknown', 'healthy', 'degraded', 'offline'])],
            'playback_health' => ['required', Rule::in(['unknown', 'healthy', 'degraded', 'offline'])],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $session = DB::table('live_sessions')->where('service_occurrence_id', $service->id)->first();
        abort_unless($session, 404);
        $streams->updateHealth($session, $data['ingest_health'], $data['playback_health'], $data['reason'] ?? null, $request->user());

        return to_route('staff.services.show', $service)->with('status', 'Stream health updated.');
    }

    public function probeStream(Request $request, ServiceOccurrence $service, LiveStreamService $streams): RedirectResponse
    {
        $session = DB::table('live_sessions')->where('service_occurrence_id', $service->id)->first();
        abort_unless($session, 404);
        $result = $streams->probe($session, $request->user());

        return to_route('staff.services.show', $service)->with('status', "Playback check complete: {$result['status']} ({$result['latency_ms']} ms).");
    }

    public function updateZoomVideoSdk(Request $request, ZoomVideoSdkSettingsService $zoom): RedirectResponse
    {
        $data = $request->validate([
            'sdk_key' => ['nullable', 'string', 'max:255'], 'sdk_secret' => ['nullable', 'string', 'max:1000'],
            'webhook_secret' => ['nullable', 'string', 'max:1000'], 'enabled' => ['nullable', 'boolean'],
            'monthly_participant_minute_warning' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'monthly_participant_minute_limit' => ['nullable', 'integer', 'min:1', 'max:100000000', 'gte:monthly_participant_minute_warning'],
        ]);
        $zoom->update($data, $request->user(), $request->ip());

        return to_route('staff.settings')->with('status', 'Zoom Video SDK settings saved.');
    }

    public function events(Request $request): View
    {
        $settings = MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID);
        $registrationCounts = DB::table('event_registrations')
            ->select('event_id', DB::raw('count(*) as registration_count'))
            ->groupBy('event_id');
        $events = DB::table('events')
            ->leftJoinSub($registrationCounts, 'registration_counts', fn ($join) => $join->on('registration_counts.event_id', '=', 'events.id'))
            ->select('events.*', DB::raw('coalesce(registration_counts.registration_count, 0) as registration_count'))
            ->orderByRaw("case when events.status = 'published' then 0 else 1 end")
            ->orderBy('events.starts_at')
            ->paginate(15);

        $locations = DB::table('locations')->where('is_public', true)->orderBy('name')->get(['name']);

        $imageAssets = $this->eventImageAssets();

        return view('staff.events', compact('settings', 'events', 'locations', 'imageAssets') + ['user' => $request->user()]);
    }

    public function storeEvent(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'], 'description' => ['nullable', 'string', 'max:5000'],
            'image_url' => ['nullable', 'url:https', 'max:1000'], 'image_alt' => ['nullable', 'string', 'max:255'],
            'media_asset_id' => ['nullable', 'uuid'], 'image_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
            'category' => ['nullable', 'string', 'max:80'], 'status' => ['required', Rule::in(['draft','published'])],
            'starts_at' => ['required', 'date'], 'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'location' => ['nullable', 'string', 'max:200'], 'online_url' => ['nullable', 'url', 'max:1000'], 'capacity' => ['nullable', 'integer', 'min:1'],
            'registration_deadline' => ['nullable', 'date', 'before_or_equal:starts_at'],
            'waitlist_enabled' => ['nullable', 'boolean'], 'registration_open' => ['nullable', 'boolean'],
        ]);
        $data = $this->resolveEventImage($request, $data);
        DB::table('events')->insert([...$data, 'id' => (string) Str::uuid(), 'slug' => Str::slug($data['title']).'-'.Str::lower(Str::random(6)), 'featured' => $request->boolean('featured'), 'waitlist_enabled' => $request->boolean('waitlist_enabled'), 'registration_open' => $request->boolean('registration_open'), 'created_at' => now(), 'updated_at' => now()]);

        return to_route('staff.events')->with('status', 'Event created.');
    }

    public function updateEvent(Request $request, string $event): RedirectResponse
    {
        $record = DB::table('events')->where('id', $event)->first();
        abort_unless($record, 404);
        $data = $request->validate([
            'title' => ['required','string','max:200'], 'description' => ['nullable','string','max:5000'],
            'image_url' => ['nullable','url:https','max:1000'], 'image_alt' => ['nullable','string','max:255'],
            'media_asset_id' => ['nullable', 'uuid'], 'image_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
            'category' => ['nullable','string','max:80'], 'status' => ['required',Rule::in(['draft','published'])],
            'starts_at' => ['required','date'], 'ends_at' => ['nullable','date','after_or_equal:starts_at'],
            'location' => ['nullable','string','max:200'], 'online_url' => ['nullable','url','max:1000'],
            'capacity' => ['nullable','integer','min:1'], 'registration_deadline' => ['nullable','date','before_or_equal:starts_at'],
        ]);
        $data = $this->resolveEventImage($request, $data);
        DB::table('events')->where('id', $event)->update([...$data, 'featured'=>$request->boolean('featured'), 'registration_open'=>$request->boolean('registration_open'), 'waitlist_enabled'=>$request->boolean('waitlist_enabled'), 'updated_at'=>now()]);
        return to_route('staff.events')->with('status', 'Event updated.');
    }

    /** @return \Illuminate\Support\Collection<int, object> */
    private function eventImageAssets(): \Illuminate\Support\Collection
    {
        return DB::table('media_assets')
            ->where('library', 'media_library')
            ->where('status', 'published')
            ->where('file_mime', 'like', 'image/%')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->get(['id', 'title', 'alt_text'])
            ->map(function (object $asset): object {
                $asset->url = route('public.media', $asset->id);

                return $asset;
            });
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function resolveEventImage(Request $request, array $data): array
    {
        $assetId = $data['media_asset_id'] ?? null;
        unset($data['media_asset_id'], $data['image_upload']);

        if ($request->hasFile('image_upload')) {
            $file = $request->file('image_upload');
            $assetId = (string) Str::uuid();
            $path = $file->store('media-library', 'local');
            $title = str($file->getClientOriginalName())->beforeLast('.')->replace(['-', '_'], ' ')->headline()->toString();
            DB::table('media_assets')->insert([
                'id' => $assetId, 'title' => $title, 'description' => null,
                'provider' => 'local', 'provider_key' => $path, 'library' => 'media_library', 'kind' => 'image',
                'status' => 'published', 'published_at' => now(), 'file_disk' => 'local', 'file_path' => $path,
                'file_name' => $file->getClientOriginalName(), 'file_mime' => $file->getMimeType(),
                'file_size_bytes' => $file->getSize(), 'alt_text' => $data['image_alt'] ?: null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        if ($assetId) {
            $asset = DB::table('media_assets')->where([
                'id' => $assetId,
                'library' => 'media_library',
                'status' => 'published',
            ])->where('file_mime', 'like', 'image/%')->first();
            abort_unless($asset, 422, 'Choose a published image from the ministry Media Library.');
            $data['image_url'] = route('public.media', $asset->id);
            if (blank($data['image_alt']) && filled($asset->alt_text)) {
                $data['image_alt'] = $asset->alt_text;
            }
        }

        return $data;
    }

    public function storePerson(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'], 'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:254'], 'phone' => ['nullable', 'string', 'max:40'],
            'consent_to_contact' => ['nullable', 'boolean'],
        ]);
        if (($data['email'] ?? null) && DB::table('people')->where('email', $data['email'])->exists()) {
            return back()->withInput()->withErrors(['email' => 'A person with this email already exists.']);
        }
        if (($data['phone'] ?? null) && DB::table('people')->where('phone', $data['phone'])->exists()) {
            return back()->withInput()->withErrors(['phone' => 'A person with this phone number already exists.']);
        }
        DB::table('people')->insert([...$data, 'id' => (string) Str::uuid(), 'status' => 'member', 'consent_to_contact' => $request->boolean('consent_to_contact'), 'created_at' => now(), 'updated_at' => now()]);

        return to_route('staff.people')->with('status', 'Person added.');
    }

    public function storeService(Request $request): RedirectResponse
    {
        $serviceTypes = ['broadcast', 'in_person'];
        if (FeatureSetting::query()->where(['key' => 'interactive_meetings', 'state' => 'enabled'])->exists()) {
            $serviceTypes = ['broadcast', 'meeting', 'hybrid', 'in_person'];
        }
        $data = $request->validate([
            'schedule_kind' => ['required', Rule::in(['one_time', 'weekly'])], 'title' => ['required', 'string', 'max:160'],
            'service_type' => ['required', Rule::in($serviceTypes)], 'timezone' => ['required', 'timezone'],
            'location_id' => ['nullable', 'uuid', 'exists:locations,id'], 'starts_at' => ['required_if:schedule_kind,one_time', 'nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'], 'starts_on' => ['required_if:schedule_kind,weekly', 'nullable', 'date'],
            'weekday' => ['required_if:schedule_kind,weekly', 'nullable', 'integer', 'between:0,6'], 'starts_at_local' => ['required_if:schedule_kind,weekly', 'nullable', 'date_format:H:i'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:720'], 'rehearsal_mode' => ['nullable', 'boolean'],
        ]);
        if ($data['schedule_kind'] === 'one_time') {
            ServiceOccurrence::create(['title' => $data['title'], 'service_type' => $data['service_type'], 'timezone' => $data['timezone'], 'location_id' => $data['location_id'] ?? null, 'starts_at' => $data['starts_at'], 'ends_at' => $data['ends_at'] ?? null, 'rehearsal_mode' => $request->boolean('rehearsal_mode'), 'state' => 'scheduled']);

            return to_route('staff.services')->with('status', 'One-time service scheduled.');
        }
        $scheduleId = (string) Str::uuid();
        $duration = (int) ($data['duration_minutes'] ?? 90);
        DB::transaction(function () use ($scheduleId, $data, $duration, $request): void {
            DB::table('service_schedules')->insert(['id' => $scheduleId, 'title' => $data['title'], 'service_type' => $data['service_type'], 'timezone' => $data['timezone'], 'location_id' => $data['location_id'] ?? null, 'frequency' => 'weekly', 'interval' => 1, 'weekdays' => json_encode([(int) $data['weekday']]), 'starts_on' => $data['starts_on'], 'starts_at_local' => $data['starts_at_local'], 'duration_minutes' => $duration, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
            $cursor = CarbonImmutable::parse($data['starts_on'], $data['timezone'])->startOfDay();
            while ($cursor->dayOfWeek !== (int) $data['weekday']) {
                $cursor = $cursor->addDay();
            }
            for ($week = 0; $week < 13; $week++, $cursor = $cursor->addWeek()) {
                $starts = $cursor->setTimeFromTimeString($data['starts_at_local'])->utc();
                ServiceOccurrence::create(['service_schedule_id' => $scheduleId, 'title' => $data['title'], 'service_type' => $data['service_type'], 'timezone' => $data['timezone'], 'location_id' => $data['location_id'] ?? null, 'starts_at' => $starts, 'ends_at' => $starts->addMinutes($duration), 'rehearsal_mode' => $request->boolean('rehearsal_mode'), 'state' => 'scheduled']);
            }
        });

        return to_route('staff.services')->with('status', 'Weekly service schedule created for the next 13 weeks.');
    }

    public function updateServiceSchedule(Request $request, string $schedule): RedirectResponse
    {
        $existing = DB::table('service_schedules')->where('id', $schedule)->first();
        abort_unless($existing, 404);

        $serviceTypes = ['broadcast', 'in_person'];
        if (FeatureSetting::query()->where(['key' => 'interactive_meetings', 'state' => 'enabled'])->exists()) {
            $serviceTypes = ['broadcast', 'meeting', 'hybrid', 'in_person'];
        }
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'service_type' => ['required', Rule::in($serviceTypes)],
            'timezone' => ['required', 'timezone'],
            'location_id' => ['nullable', 'uuid', 'exists:locations,id'],
            'starts_on' => ['required', 'date'],
            'weekday' => ['required', 'integer', 'between:0,6'],
            'starts_at_local' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:720'],
            'active' => ['nullable', 'boolean'],
        ]);
        $duration = (int) $data['duration_minutes'];

        DB::transaction(function () use ($schedule, $data, $duration): void {
            DB::table('service_schedules')->where('id', $schedule)->update([
                'title' => $data['title'], 'service_type' => $data['service_type'], 'timezone' => $data['timezone'],
                'location_id' => $data['location_id'] ?? null, 'weekdays' => json_encode([(int) $data['weekday']]),
                'starts_on' => $data['starts_on'], 'starts_at_local' => $data['starts_at_local'], 'duration_minutes' => $duration,
                'active' => (bool) ($data['active'] ?? false), 'updated_at' => now(),
            ]);
            DB::table('service_occurrences')->where('service_schedule_id', $schedule)->where('state', 'scheduled')->delete();

            if (! empty($data['active'])) {
                $cursor = CarbonImmutable::parse($data['starts_on'], $data['timezone'])->startOfDay();
                while ($cursor->dayOfWeek !== (int) $data['weekday']) {
                    $cursor = $cursor->addDay();
                }
                for ($week = 0; $week < 13; $week++, $cursor = $cursor->addWeek()) {
                    $starts = $cursor->setTimeFromTimeString($data['starts_at_local'])->utc();
                    if ($starts->isPast()) {
                        continue;
                    }
                    ServiceOccurrence::create(['service_schedule_id' => $schedule, 'title' => $data['title'], 'service_type' => $data['service_type'], 'timezone' => $data['timezone'], 'location_id' => $data['location_id'] ?? null, 'starts_at' => $starts, 'ends_at' => $starts->addMinutes($duration), 'state' => 'scheduled']);
                }
            }
        });

        return to_route('staff.services')->with('status', 'Recurring service updated.');
    }

    public function destroyServiceSchedule(string $schedule): RedirectResponse
    {
        DB::transaction(function () use ($schedule): void {
            DB::table('service_occurrences')->where('service_schedule_id', $schedule)->whereIn('state', ['scheduled', 'ready', 'rehearsing'])->delete();
            DB::table('service_schedules')->where('id', $schedule)->delete();
        });

        return to_route('staff.services')->with('status', 'Recurring service removed. Historical service records were kept.');
    }

    public function content(Request $request): View
    {
        $settings = MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID);
        $modules = app(\App\Services\ModuleAvailability::class);
        $availableLibraries = array_values(array_filter(
            ['books_library', 'audio_sermons', 'video_sermons', 'sermon_series'],
            fn (string $key): bool => $modules->visibleToStaff($key, $request->user())
        ));
        $assets = DB::table('media_assets')->whereIn('library', $availableLibraries)->orderByDesc('created_at')->paginate(15);

        return view('staff.content', compact('settings', 'assets') + ['user' => $request->user()]);
    }

    public function mediaLibrary(Request $request): View
    {
        $settings = MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID);
        $query = trim((string) $request->query('q'));
        $view = $request->query('view', 'grid') === 'list' ? 'list' : 'grid';
        $type = in_array($request->query('type'), ['image', 'video', 'application'], true) ? $request->query('type') : '';
        $status = in_array($request->query('status'), ['draft', 'published', 'rejected'], true) ? $request->query('status') : '';
        $sort = in_array($request->query('sort'), ['oldest', 'name', 'size'], true) ? $request->query('sort') : 'newest';
        $assets = DB::table('media_assets')->where('library', 'media_library')
            ->when($query !== '', fn ($q) => $q->where(function ($inner) use ($query): void {
                $inner->where('title', 'like', "%{$query}%")->orWhere('file_name', 'like', "%{$query}%");
            }))->when($type !== '', fn ($q) => $q->where('file_mime', 'like', $type.'/%'))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($sort === 'oldest', fn ($q) => $q->orderBy('created_at')->orderBy('published_at')->orderBy('id'))
            ->when($sort === 'name', fn ($q) => $q->orderBy('title')->orderBy('id'))
            ->when($sort === 'size', fn ($q) => $q->orderByDesc('file_size_bytes')->orderByDesc('created_at')->orderBy('id'))
            ->when($sort === 'newest', fn ($q) => $q->orderByDesc('created_at')->orderByDesc('published_at')->orderByDesc('id'))
            ->paginate(24)->withQueryString();

        return view('staff.media-library', compact('settings', 'assets', 'query', 'view', 'type', 'status', 'sort') + ['user' => $request->user()]);
    }

    public function uploadMediaLibrary(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'file' => ['required', 'array', 'min:1'],
            'file.*' => ['file', 'mimes:jpg,jpeg,png,webp,gif,svg,mp4,webm,mov,pdf,doc,docx,ppt,pptx,xls,xlsx,txt', 'max:102400'],
        ]);
        $uploaded = [];
        foreach ($request->file('file', []) as $file) {
            $path = $file->store('media-library', 'local');
            $title = ($data['title'] ?? null) ?: str($file->getClientOriginalName())->beforeLast('.')->replace(['-', '_'], ' ')->headline()->toString();
            $id = (string) Str::uuid();
            DB::table('media_assets')->insert([
            'id' => $id, 'title' => $title, 'description' => null,
            'provider' => 'local', 'provider_key' => $path, 'library' => 'media_library', 'kind' => str($file->getMimeType())->before('/')->toString(),
            'status' => 'published', 'published_at' => now(), 'file_disk' => 'local', 'file_path' => $path, 'file_name' => $file->getClientOriginalName(),
            'file_mime' => $file->getMimeType(), 'file_size_bytes' => $file->getSize(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $uploaded[] = ['id' => $id, 'title' => $title, 'url' => route('public.media', $id), 'kind' => str($file->getMimeType())->before('/')->toString()];
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Media uploaded and published.', 'assets' => $uploaded], 201);
        }

        return to_route('staff.media-library')->with('status', 'Media uploaded and published.');
    }

    public function mediaLibraryFile(Request $request, string $asset): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $record = DB::table('media_assets')->where(['id' => $asset, 'library' => 'media_library', 'status' => 'published'])->first();
        abort_unless($record && $record->provider === 'local' && $record->file_path, 404);
        $path = Storage::disk($record->file_disk ?: 'local')->path($record->file_path);
        abort_unless(is_file($path), 404);
        return response()->file($path, ['Content-Disposition' => 'inline; filename="'.addslashes($record->file_name ?: $record->title).'"']);
    }

    public function updateMediaLibrary(Request $request, string $asset): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:200'], 'file_name' => ['required', 'string', 'max:255'], 'alt_text' => ['nullable', 'string', 'max:180'], 'caption' => ['nullable', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000']]);
        abort_unless(DB::table('media_assets')->where(['id' => $asset, 'library' => 'media_library'])->exists(), 404);
        DB::table('media_assets')->where('id', $asset)->update([...$data, 'updated_at' => now()]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Media details updated.', 'asset' => DB::table('media_assets')->where('id', $asset)->first()]);
        }

        return to_route('staff.media-library')->with('status', 'Media details updated.');
    }

    public function deleteMediaLibrary(Request $request, string $asset): RedirectResponse|JsonResponse
    {
        $record = DB::table('media_assets')->where(['id' => $asset, 'library' => 'media_library'])->first();
        abort_unless($record, 404);
        if ($record->file_path && Storage::disk($record->file_disk ?: 'local')->exists($record->file_path)) {
            Storage::disk($record->file_disk ?: 'local')->delete($record->file_path);
        }
        DB::table('media_assets')->where('id', $asset)->delete();
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Media deleted.']);
        }
        return to_route('staff.media-library')->with('status', 'Media deleted.');
    }

    public function followUp(Request $request): View
    {
        $settings = MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID);
        $journeyOptions = DB::table('next_step_options')->orderBy('display_order')->orderBy('label')->get();
        $staff = DB::table('users')->orderBy('name')->get(['id', 'name']);
        $tasks = DB::table('follow_up_tasks')->leftJoin('people', 'people.id', '=', 'follow_up_tasks.person_id')
            ->select('follow_up_tasks.*', 'people.first_name', 'people.last_name')->whereIn('follow_up_tasks.status', ['open', 'in_progress'])->orderByRaw('follow_up_tasks.due_at is null')->orderBy('follow_up_tasks.due_at')->paginate(20);

        return view('staff.follow-up', compact('settings', 'tasks', 'journeyOptions', 'staff') + ['user' => $request->user()]);
    }

    public function updateFollowUp(Request $request, string $task): RedirectResponse
    {
        $data = $request->validate([
            'assigned_to' => ['nullable', 'uuid', 'exists:users,id'],
            'status' => ['required', Rule::in(['open', 'in_progress', 'completed', 'closed_no_contact'])],
            'due_at' => ['nullable', 'date'],
        ]);
        $query = DB::table('follow_up_tasks')->where('id', $task);
        abort_unless($query->exists(), 404);
        $query->update([...$data, 'completed_at' => in_array($data['status'], ['completed', 'closed_no_contact'], true) ? now() : null, 'updated_at' => now()]);

        return to_route('staff.follow-up')->with('status', 'Follow-up request updated.');
    }

    public function care(Request $request): View
    {
        $settings = MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID);
        $cases = DB::table('care_cases')->join('users', 'users.id', '=', 'care_cases.assigned_to')
            ->select('care_cases.*', 'users.name as assigned_name')->where('care_cases.assigned_to', $request->user()->id)->where('care_cases.status', '!=', 'closed')->orderByDesc('care_cases.updated_at')->paginate(20);
        DB::table('audit_events')->insert(['id' => (string) Str::uuid(), 'actor_user_id' => $request->user()->id, 'action' => 'care.case_list_viewed', 'subject_type' => 'care_cases', 'subject_id' => (string) Str::uuid(), 'changes_json' => '{}', 'ip_address' => $request->ip(), 'created_at' => now()]);

        return view('staff.care', compact('settings', 'cases') + ['user' => $request->user()]);
    }

    public function reports(Request $request): View
    {
        $settings = MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID);
        $modules = app(\App\Services\ModuleAvailability::class);
        $metrics = [];
        if ($modules->visibleToStaff('people', $request->user())) {
            $metrics[] = ['label' => 'Open follow-ups', 'value' => DB::table('follow_up_tasks')->where('status', '!=', 'completed')->count(), 'detail' => 'Awaiting a response'];
        }
        if ($modules->visibleToStaff('digital_church', $request->user())) {
            array_unshift($metrics,
                ['label' => 'Identified attendance', 'value' => DB::table('attendance_records')->where('status', 'present')->count(), 'detail' => 'Confirmed records only'],
                ['label' => 'Anonymous viewer buckets', 'value' => DB::table('anonymous_viewer_aggregates')->sum('viewer_count'), 'detail' => 'Kept separate from attendance']
            );
        }
        $metrics[] = ['label' => 'Active departments', 'value' => DB::table('departments')->where('status', 'active')->count(), 'detail' => 'Current ministry departments'];

        return view('staff.reports', compact('settings', 'metrics') + ['user' => $request->user()]);
    }

    public function communications(Request $request): View
    {
        $settings = MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID);
        $campaigns = DB::table('campaigns')->join('message_templates', 'message_templates.id', '=', 'campaigns.template_id')->select('campaigns.*', 'message_templates.channel')->orderByDesc('campaigns.created_at')->paginate(20);

        return view('staff.communications', compact('settings', 'campaigns') + ['user' => $request->user()]);
    }

    public function giving(Request $request, PermissionChecker $permissions): View
    {
        $settings = MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID);
        $canViewFinance = $permissions->allows($request->user(), 'finance.view');
        $donations = $canViewFinance
            ? DB::table('donations')->join('giving_funds', 'giving_funds.id', '=', 'donations.fund_id')->select('donations.*', 'giving_funds.name as fund_name')->orderByDesc('donations.created_at')->paginate(20)
            : new LengthAwarePaginator([], 0, 20, 1, ['path' => $request->url()]);
        $funds = DB::table('giving_funds')->orderBy('display_order')->orderBy('name')->get();
        $providers = DB::table('payment_processor_configs')->orderBy('priority')->get()->map(function ($row) { $row->configured = filled($row->credentials_encrypted); unset($row->credentials_encrypted); return $row; });
        $offlineMethods = DB::table('offline_giving_methods')->orderBy('display_order')->orderBy('label')->get();

        $countries = config('giving.countries', []);
        $currencies = config('giving.currencies', []);
        return view('staff.giving', compact('settings', 'donations','funds','providers','offlineMethods', 'canViewFinance','countries','currencies') + ['user' => $request->user()]);
    }

    public function locations(Request $request): View
    {
        $settings = MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID);
        $locations = Location::query()->orderByDesc('is_primary')->orderBy('name')->get();
        $locations->each(function (object $location): void {
            $location->latitude ??= null;
            $location->longitude ??= null;
        });

        return view('staff.locations', compact('settings', 'locations') + ['user' => $request->user()]);
    }

    public function modules(Request $request, PermissionChecker $permissions): View
    {
        $settings = MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID);
        $features = DB::table('feature_settings')->orderBy('key')->get();

        return view('staff.modules', compact('settings', 'features') + ['user' => $request->user(), 'canManageFeatures' => $permissions->allows($request->user(), 'features.manage')]);
    }

    public function updateModule(Request $request, string $key, AdministrationService $administration): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['state' => ['required', Rule::in(['disabled', 'staff_preview', 'enabled'])]]);
        $feature = FeatureSetting::query()->findOrFail($key);
        $feature = $administration->changeFeature($feature, $data['state'], $request->user(), $request->ip());

        if ($request->expectsJson()) {
            return response()->json(['data' => $feature->only(['key', 'state', 'updated_at'])]);
        }

        return to_route('staff.modules')->with('status', 'Module state updated.');
    }

    public function bulkUpdateModules(Request $request, AdministrationService $administration): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'state' => ['required', Rule::in(['disabled', 'enabled'])],
            'keys' => ['required', 'array', 'min:1'],
            'keys.*' => ['required', 'string', Rule::exists('feature_settings', 'key')],
        ]);
        $changed = $administration->changeFeatures(array_values(array_unique($data['keys'])), $data['state'], $request->user(), $request->ip());

        if ($request->expectsJson()) {
            return response()->json([
                'data' => FeatureSetting::query()->whereIn('key', $data['keys'])->orderBy('key')->get(['key', 'state', 'updated_at']),
                'meta' => ['changed' => $changed],
            ]);
        }

        return to_route('staff.modules')->with('status', "{$changed} module states updated.");
    }

    public function staffAccess(Request $request): View
    {
        $settings = MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID);
        $invitations = DB::table('staff_invitations')->join('users', 'users.id', '=', 'staff_invitations.invited_by')->select('staff_invitations.*', 'users.name as invited_by_name')->orderByDesc('staff_invitations.created_at')->paginate(20);
        $roles = DB::table('roles')->whereIn('key', \App\Support\RoleCatalog::ROUTINE_INVITATION_ROLES)->orderBy('name')->get(['key', 'name']);

        return view('staff.access', compact('settings', 'invitations', 'roles') + ['user' => $request->user()]);
    }

    public function inviteStaff(Request $request, StaffInvitationService $invitations): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:160'], 'email' => ['required', 'email', 'max:254'], 'role_key' => ['required', 'string', 'max:64']]);
        $invite = $invitations->create($data['name'], $data['email'], $data['role_key'], $request->user(), $request->ip());

        return to_route('staff.access')->with('status', "Invitation created for {$invite['email']} as ".str($invite['role_key'])->headline().'.');
    }

    public function security(Request $request, EmailDeliverySettingsService $delivery, PermissionChecker $permissions): View
    {
        $settings = MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID);
        $mfaEnabled = DB::table('mfa_credentials')->where('user_id', $request->user()->id)->whereNotNull('enabled_at')->exists();
        $sessions = DB::table('sessions')->where('user_id', $request->user()->id)->orderByDesc('last_activity')->limit(10)->get();

        return view('staff.security', compact('settings', 'mfaEnabled', 'sessions') + ['user' => $request->user(), 'emailVerified' => $request->user()->email_verified_at !== null, 'emailDeliveryActive' => $delivery->active(), 'canManageEmail' => $permissions->allows($request->user(), 'settings.read'), 'mfaSetup' => $request->session()->get('mfa_setup'), 'recoveryCodes' => $request->session()->get('mfa_recovery_codes', [])]);
    }

    public function beginMfa(Request $request, MfaService $mfa): RedirectResponse
    {
        if ($mfa->enabled($request->user())) {
            return to_route('staff.security')->with('status', 'Two-factor authentication is already enabled.');
        }

        $request->session()->put('mfa_setup', $mfa->begin($request->user()));

        return to_route('staff.security')->with('status', 'Add the displayed key to your authenticator app, then enter its current code.');
    }

    public function enableMfa(Request $request, MfaService $mfa): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $codes = $mfa->enable($request->user(), $data['code']);
        $request->session()->forget('mfa_setup');
        $request->session()->put('mfa_verified_at', now()->timestamp);

        return to_route('staff.security')->with(['status' => 'Two-factor authentication is enabled for this session.', 'mfa_recovery_codes' => $codes]);
    }

    public function requestEmailVerification(Request $request, EmailVerificationService $verification, EmailDeliverySettingsService $delivery, PermissionChecker $permissions): RedirectResponse
    {
        if ($request->user()->email_verified_at !== null) {
            return to_route('staff.security')->with('status', 'Your email address is already verified.');
        }
        if (! $delivery->active()) {
            if ($permissions->allows($request->user(), 'settings.read')) {
                return to_route('staff.email')->with('status', 'Set up and activate email delivery before sending a verification email.');
            }

            return to_route('staff.security')->with('security_notice', 'Email delivery is not active. Ask a ministry administrator to configure it before requesting verification.');
        }

        $verification->request($request->user());

        return to_route('staff.security')->with('status', 'A verification link has been sent to '.$request->user()->email.'.');
    }

    public function updateSettings(Request $request, UpdateMinistrySettings $update): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'tagline' => ['nullable', 'string', 'max:240'],
            'public_domain' => ['required', 'string', 'max:253'],
            'timezone' => ['required', 'timezone'],
            'contact_email' => ['nullable', 'email', 'max:254'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'public_address' => ['nullable', 'string', 'max:2000'],
            'website_label' => ['nullable', 'string', 'max:160'],
            'facebook_url' => ['nullable', 'url', 'max:500'],
            'instagram_url' => ['nullable', 'url', 'max:500'],
            'youtube_url' => ['nullable', 'url', 'max:500'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=2400,max_height=2400'],
            'dark_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=2400,max_height=2400'],
            'favicon' => ['nullable', 'file', 'mimes:ico,png', 'max:512'],
            'theme_preset' => ['required', Rule::in(['neutral', 'tech_blue', 'warm'])],
            'chrome_mode' => ['required', Rule::in(['preset', 'custom'])],
            'chrome_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'staff_font' => ['required', Rule::in(['modern', 'editorial', 'humanist'])],
            'staff_density' => ['required', Rule::in(['comfortable', 'compact'])],
        ]);
        if ($validated['chrome_mode'] !== 'custom') {
            $validated['chrome_color'] = null;
        }
        $settings = MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID);
        foreach (['logo' => 'logo_path', 'dark_logo' => 'dark_logo_path', 'favicon' => 'favicon_path'] as $input => $attribute) {
            if ($request->hasFile($input)) {
                if ($settings->{$attribute}) {
                    Storage::disk('public')->delete($settings->{$attribute});
                }
                $validated[$attribute] = $request->file($input)->store('ministry-brand', 'public');
            }
        }
        $validated['social_links'] = array_filter([
            'facebook' => $validated['facebook_url'] ?? null,
            'instagram' => $validated['instagram_url'] ?? null,
            'youtube' => $validated['youtube_url'] ?? null,
        ]);
        unset($validated['logo'], $validated['dark_logo'], $validated['favicon'], $validated['facebook_url'], $validated['instagram_url'], $validated['youtube_url']);
        $update->execute($validated, $request->user(), $request->ip());

        return to_route('staff.settings')->with('status', 'Brand settings saved.');
    }
}
