<?php

namespace App\Http\Controllers;

use App\Models\FeatureSetting;
use App\Models\Location;
use App\Models\MinistrySetting;
use App\Models\ServiceOccurrence;
use App\Services\LiveStreamService;
use App\Support\PublicLibraryRoute;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicWorkspaceController extends Controller
{
    public function locations(): View
    {
        return $this->view('public.locations', ['locations' => Location::query()->where('is_public', true)->orderByDesc('is_primary')->orderBy('name')->get()]);
    }

    public function location(string $slug): View
    {
        $location = Location::query()->where('slug', $slug)->where('is_public', true)->firstOrFail();
        return $this->view('public.location', compact('location'));
    }

    public function serviceTimes(Request $request): View
    {
        $services = DB::table('service_occurrences')
            ->leftJoin('locations', function ($join): void {
                $join->on('locations.id', '=', 'service_occurrences.location_id')
                    ->where('locations.is_public', true);
            })
            ->where('service_occurrences.rehearsal_mode', false)
            ->whereIn('service_occurrences.state', ['scheduled', 'ready', 'live', 'interrupted'])
            ->where('service_occurrences.starts_at', '>=', now()->subMinutes(30))
            ->orderBy('service_occurrences.starts_at')
            ->limit(60)
            ->get(['service_occurrences.id', 'service_occurrences.service_schedule_id', 'service_occurrences.title', 'service_occurrences.service_type', 'service_occurrences.state', 'service_occurrences.starts_at', 'service_occurrences.timezone', 'locations.name as location_name', 'locations.address as location_address']);

        $reminders = collect();
        $remindersAvailable = DB::table('email_delivery_settings')
            ->where('enabled', true)
            ->whereNotNull('from_address')
            ->whereNotNull('from_name')
            ->whereNotNull('host')
            ->whereNotNull('port')
            ->whereNotNull('username')
            ->whereNotNull('password_encrypted')
            ->exists();
        if ($request->user()) {
            $personId = DB::table('people')->where('user_id', $request->user()->id)->value('id');
            if ($personId) {
                $reminders = DB::table('service_reminder_subscriptions')
                    ->where('person_id', $personId)
                    ->where('active', true)
                    ->get()
                    ->keyBy('id');
            }
        }

        return $this->view('public.service-times', compact('services', 'reminders', 'remindersAvailable'));
    }

    public function events(): View
    {
        return $this->view('public.events', ['events' => DB::table('events')->where('status', 'published')->whereNull('cancelled_at')->where('starts_at', '>=', now())->orderByDesc('featured')->orderBy('starts_at')->paginate(18)]);
    }

    public function event(string $slug): View
    {
        $event = DB::table('events')->where('slug', $slug)->where('status', 'published')->first();
        abort_unless($event, 404);
        $registered = DB::table('event_registrations')->where('event_id', $event->id)->whereIn('status', ['registered','checked_in'])->count();
        return $this->view('public.event', compact('event', 'registered'));
    }

    public function departments(): View
    {
        return $this->view('public.departments', ['departments' => DB::table('departments')->where('status', 'active')->where('is_public', true)->where('accepting_requests', true)->orderByDesc('featured')->orderBy('name')->paginate(18)]);
    }

    public function department(string $slug): View
    {
        $department = DB::table('departments')->where('slug', $slug)->where('status', 'active')->where('is_public', true)->first();
        abort_unless($department, 404);
        $memberCount = DB::table('department_memberships')->where('department_id', $department->id)->where('status', 'active')->count();
        $relationship = null;
        if (auth()->check()) {
            $personId = DB::table('people')->where('user_id', auth()->id())->value('id');
            if ($personId && DB::table('department_memberships')->where(['department_id' => $department->id, 'person_id' => $personId, 'status' => 'active'])->exists()) {
                $relationship = 'member';
            } elseif ($personId && DB::table('department_join_requests')->where(['department_id' => $department->id, 'person_id' => $personId, 'status' => 'pending'])->exists()) {
                $relationship = 'pending';
            }
        }
        return $this->view('public.department', compact('department', 'memberCount', 'relationship'));
    }

    public function giving(): View
    {
        $this->enabled('giving');

        return $this->view('public.giving', [
            'funds' => DB::table('giving_funds')->where('active', true)->where('public_visible',true)->orderBy('display_order')->orderBy('name')->get(),
            'givingMarkets' => DB::table('payment_processor_configs')->where('enabled',true)->whereNotNull('credentials_encrypted')->select('country','currency')->distinct()->orderBy('country')->orderBy('currency')->get(),
            'offlineGivingMethods' => DB::table('offline_giving_methods')->where('active',true)->orderBy('display_order')->orderBy('label')->get(),
        ]);
    }

    public function contact(): View
    {
        return $this->view('public.contact', []);
    }

    public function library(Request $request, string $library): View
    {
        $libraryKey = PublicLibraryRoute::keyFor($library);
        abort_unless($libraryKey !== null, 404);
        $this->enabled($libraryKey);
        $query = trim((string) $request->query('q'));
        $category = trim((string) $request->query('category'));
        if ($libraryKey === 'sermon_series') {
            $counts = DB::table('media_assets')->where('status', 'published')->selectRaw('sermon_series_id, count(*) as message_count')->groupBy('sermon_series_id');
            $items = DB::table('sermon_series')->leftJoinSub($counts, 'message_counts', fn ($join) => $join->on('message_counts.sermon_series_id', '=', 'sermon_series.id'))
                ->where('sermon_series.status', 'published')->when($query !== '', fn ($builder) => $builder->where(fn ($inner) => $inner->where('sermon_series.title', 'like', "%{$query}%")->orWhere('sermon_series.description', 'like', "%{$query}%")))
                ->orderByDesc('sermon_series.created_at')->select('sermon_series.*', DB::raw('coalesce(message_counts.message_count, 0) as message_count'))->paginate(18)->withQueryString();
        } else {
            $items = DB::table('media_assets')->leftJoin('library_categories', 'library_categories.id', '=', 'media_assets.library_category_id')->leftJoin('sermon_series', 'sermon_series.id', '=', 'media_assets.sermon_series_id')
                ->where(['media_assets.library' => $libraryKey, 'media_assets.status' => 'published'])
                ->when($query !== '', fn ($builder) => $builder->where(fn ($inner) => $inner->where('media_assets.title', 'like', "%{$query}%")->orWhere('media_assets.description', 'like', "%{$query}%")->orWhere('media_assets.speaker', 'like', "%{$query}%")->orWhere('media_assets.author', 'like', "%{$query}%")))
                ->when($category !== '', fn ($builder) => $builder->where('library_categories.slug', $category))
                ->orderByDesc('media_assets.published_at')->select('media_assets.*', 'library_categories.name as category_name', 'library_categories.slug as category_slug', 'sermon_series.title as series_title')->paginate(18)->withQueryString();
        }

        $categories = $libraryKey === 'sermon_series' ? collect() : DB::table('library_categories')->where(fn ($builder) => $builder->whereNull('library')->orWhere('library', $libraryKey))->orderBy('name')->get();
        return $this->view('public.library', ['library' => $libraryKey, 'librarySlug' => $library, 'items' => $items, 'categories' => $categories, 'query' => $query, 'category' => $category]);
    }

    public function sermonSeries(string $series): View
    {
        $this->enabled('sermon_series');
        $item = DB::table('sermon_series')->where(['id' => $series, 'status' => 'published'])->first();
        abort_unless($item, 404);
        $enabledLibraries = FeatureSetting::query()->where('state', 'enabled')->whereIn('key', ['audio_sermons', 'video_sermons'])->pluck('key');
        $messages = DB::table('media_assets')->where('sermon_series_id', $series)->where('status', 'published')->whereIn('library', $enabledLibraries)->orderBy('position')->orderByDesc('published_at')->get();

        return $this->view('public.sermon-series', compact('item', 'messages'));
    }

    public function page(string $slug): View
    {
        $page = DB::table('pages')->where('slug', $slug)->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now())->first();
        abort_unless($page, 404);

        $blocks = json_decode($page->blocks ?: '[]', true);
        $features = FeatureSetting::query()->where('state', 'enabled')->pluck('key')->all();

        return $this->view('public.page', ['page' => $page, 'pageElementData' => $this->pageElementData(is_array($blocks) ? $blocks : [], $features)]);
    }

    public function worship(ServiceOccurrence $service, LiveStreamService $streams): View
    {
        $this->enabled('digital_church');
        abort_unless(! $service->rehearsal_mode && in_array($service->state, ['live', 'interrupted'], true), 404);

        $interactiveMeetingsEnabled = FeatureSetting::query()->where(['key' => 'interactive_meetings', 'state' => 'enabled'])->exists();
        $meeting = $interactiveMeetingsEnabled && in_array($service->service_type, ['meeting', 'hybrid'], true)
            ? DB::table('meetings')->where('service_occurrence_id', $service->id)->whereIn('status', ['scheduled', 'live'])->first(['id', 'title', 'entry_policy'])
            : null;
        abort_if($service->service_type === 'meeting' && ! $meeting, 404);

        return $this->view('public.worship', ['service' => $service, 'media' => $streams->publicDescriptor($service), 'meeting' => $meeting]);
    }

    private function view(string $view, array $data): View
    {
        $settings = MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID);
        $features = FeatureSetting::query()->where('state', 'enabled')->pluck('key')->all();

        return view($view, [...$data, 'settings' => $settings, 'features' => $features]);
    }

    private function enabled(string $key): void
    {
        abort_unless(FeatureSetting::query()->where(['key' => $key, 'state' => 'enabled'])->exists(), 404);
    }

    /** @param array<string, mixed> $blocks @param array<int, string> $features */
    private function pageElementData(array $blocks, array $features): array
    {
        $types = collect($blocks['sections'] ?? [])->flatMap(fn ($section) => collect($section['columns'] ?? [])->flatMap(fn ($column) => collect($column['elements'] ?? [])->pluck('type')))->filter()->unique();
        $data = [];

        if ($types->contains('events')) $data['events'] = DB::table('events')->whereNull('cancelled_at')->where('starts_at', '>=', now())->orderBy('starts_at')->limit(3)->get(['id', 'title', 'description', 'starts_at', 'location']);
        if ($types->contains('locations')) $data['locations'] = Location::query()->where('is_public', true)->orderByDesc('is_primary')->orderBy('name')->limit(3)->get(['id', 'name', 'address']);
        if ($types->contains('announcements')) $data['announcements'] = DB::table('announcements')->where('published', true)->where(fn ($query) => $query->whereNull('publish_at')->orWhere('publish_at', '<=', now()))->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->latest()->limit(3)->get(['id', 'title', 'body']);
        if ($types->contains('departments')) $data['departments'] = DB::table('departments')->where('status', 'active')->where('is_public', true)->orderBy('name')->limit(3)->get(['id', 'name', 'slug', 'description']);
        if ($types->contains('service_times')) $data['services'] = DB::table('service_occurrences')->leftJoin('locations', 'locations.id', '=', 'service_occurrences.location_id')->where('service_occurrences.rehearsal_mode', false)->whereIn('service_occurrences.state', ['scheduled', 'ready'])->where('service_occurrences.starts_at', '>=', now())->orderBy('service_occurrences.starts_at')->limit(24)->get(['service_occurrences.id', 'service_occurrences.service_schedule_id', 'service_occurrences.title', 'service_occurrences.starts_at', 'service_occurrences.timezone', 'locations.name as location_name'])->unique(fn (object $service): string => $service->service_schedule_id ?: $service->id)->take(3)->values();
        if ($types->contains('live_service') && in_array('digital_church', $features, true)) $data['liveService'] = DB::table('service_occurrences')->where('rehearsal_mode', false)->whereIn('state', ['live', 'interrupted'])->orderByDesc('starts_at')->first(['id', 'title', 'state']);
        if ($types->contains('giving') && in_array('giving', $features, true)) $data['funds'] = DB::table('giving_funds')->where('active', true)->orderBy('name')->limit(3)->get(['id', 'name', 'description']);

        $resourceLibraries = array_values(array_intersect($features, ['books_library', 'audio_sermons', 'video_sermons']));
        if (($types->contains('books') || $types->contains('sermon_spotlight')) && $resourceLibraries !== []) {
            $resources = DB::table('media_assets')->where('status', 'published')->whereIn('library', $resourceLibraries)->orderByDesc('published_at')->limit(3)->get(['id', 'title', 'description', 'library', 'speaker']);
            if ($types->contains('books')) $data['books'] = $resources->where('library', 'books_library')->values();
            if ($types->contains('sermon_spotlight')) $data['sermon'] = $resources->first(fn (object $resource): bool => in_array($resource->library, ['audio_sermons', 'video_sermons'], true));
        }

        return $data;
    }
}
