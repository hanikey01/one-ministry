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

        return $this->view('public.page', compact('page'));
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
}
