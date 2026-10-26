<?php

namespace App\Http\Controllers;

use App\Models\FeatureSetting;
use App\Models\Location;
use App\Models\MinistrySetting;
use App\Models\ServiceOccurrence;
use App\Services\LiveStreamService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class PublicWorkspaceController extends Controller
{
    public function locations(): View
    {
        return $this->view('public.locations', ['locations' => Location::query()->where('is_public', true)->orderByDesc('is_primary')->orderBy('name')->get()]);
    }

    public function events(): View
    {
        return $this->view('public.events', ['events' => DB::table('events')->where('starts_at', '>=', now())->orderBy('starts_at')->paginate(18)]);
    }

    public function groups(): View
    {
        $this->enabled('groups');

        return $this->view('public.groups', ['groups' => DB::table('groups')->where('status', 'active')->orderBy('name')->paginate(18)]);
    }

    public function giving(): View
    {
        $this->enabled('giving');

        return $this->view('public.giving', [
            'funds' => DB::table('giving_funds')->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function library(string $library): View
    {
        abort_unless(in_array($library, ['books_library', 'audio_sermons', 'video_sermons', 'sermon_series'], true), 404);
        $this->enabled($library);
        $items = $library === 'sermon_series'
            ? DB::table('sermon_series')->where('status', 'published')->orderByDesc('starts_on')->paginate(18)
            : DB::table('media_assets')->leftJoin('library_categories', 'library_categories.id', '=', 'media_assets.library_category_id')->where(['media_assets.library' => $library, 'media_assets.status' => 'published'])->orderByDesc('media_assets.published_at')->select('media_assets.*', 'library_categories.name as category_name')->paginate(18);

        return $this->view('public.library', compact('library', 'items'));
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
        if ($types->contains('groups') && in_array('groups', $features, true)) $data['groups'] = DB::table('groups')->where('status', 'active')->orderBy('name')->limit(3)->get(['id', 'name', 'description']);
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
