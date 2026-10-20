<?php

namespace App\Services;

use App\Models\FeatureSetting;
use App\Models\HomepageSection;
use App\Models\HomepageSetting;
use App\Models\HomepageSlide;
use App\Models\ServiceOccurrence;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HomepageService
{
    public const THEMES = ['sanctuary', 'community', 'editorial', 'modern'];
    public const LAYOUTS = ['immersive', 'split', 'cinematic', 'editorial'];
    public const SECTIONS = ['welcome', 'service_times', 'live_service', 'locations', 'events', 'groups', 'next_steps', 'giving', 'sermon_spotlight', 'books', 'audio', 'video', 'announcements', 'prayer', 'volunteer', 'newsletter', 'custom', 'faq'];

    public const STARTER_THEMES = [
        'sanctuary' => ['name' => 'Sanctuary', 'description' => 'The complete worship-led public design: a cinematic hero, guided next steps, and warm ministry storytelling.', 'layout' => 'immersive', 'eyebrows' => ['WELCOME HOME', 'WORSHIP TOGETHER', 'GROW IN COMMUNITY'], 'slides' => [['eyebrow' => 'WELCOME HOME', 'title' => 'A place to encounter God and find family.', 'body' => 'Join us as we worship, grow, and serve together.', 'media_type' => 'image', 'media_url' => '/images/home/worship-hero.png', 'alt_text' => 'Worship leaders and congregation gathered in praise.'], ['eyebrow' => 'WORSHIP TOGETHER', 'title' => 'Come as you are. Leave renewed.', 'body' => 'A sample worship video shows how ministry-owned YouTube or Vimeo media can live in the homepage carousel.', 'media_type' => 'external_video', 'media_url' => 'https://www.youtube.com/watch?v=6CKCThJB5w0', 'alt_text' => 'Worship music video preview.'], ['eyebrow' => 'GROW IN COMMUNITY', 'title' => 'Faith is stronger when we walk together.', 'body' => 'Discover worship, community, prayer, and meaningful ways to serve.', 'media_type' => 'image', 'media_url' => '/images/home/community-groups.png', 'alt_text' => 'A ministry group gathering together.']], 'sections' => ['service_times' => ['Make plans to worship with us', 'The next upcoming gathering appears here automatically.'], 'events' => ['There is always a next step', 'Explore upcoming moments to learn, connect, and serve.'], 'sermon_spotlight' => ['Current sermon series', 'Teaching for real life, with space to think, grow, and respond.'], 'groups' => ['Life is better in community', 'Find people who can encourage you through every season.'], 'prayer' => ['How can we pray for you?', 'Share a prayer need. Our care team will handle it with respect and compassion.'], 'next_steps' => ['Your next step matters', 'Take a simple step toward connection, growth, and purpose.'], 'books' => ['Practical resources for real life.', 'Books, sermons, and resources to help you keep growing.'], 'giving' => ['Generosity helps the gospel go further', 'Your giving supports ministry in our church, city, and beyond.'], 'locations' => ['Find your way here', 'We would love to welcome you in person.']]],
        'community' => ['name' => 'Community', 'description' => 'A warm, local invitation built around belonging and connection.', 'layout' => 'split', 'eyebrows' => ['YOU BELONG HERE', 'LIFE TOGETHER', 'THIS WEEK'], 'slides' => [['A church family for every season.', 'Meet people, find your place, and build a life of faith together.'], ['Make a difference with your gifts.', 'There is a meaningful place for you to serve.'], ['A community that shows up.', 'See what is happening and take your next step.']], 'sections' => ['service_times' => ['Join us this Sunday', 'Find a service and plan your visit.'], 'locations' => ['A place near you', 'Find a gathering location and directions.'], 'groups' => ['Find your people', 'Small groups make a big church feel like home.'], 'events' => ['Connect this week', 'Explore gatherings for every age and stage.'], 'volunteer' => ['Serve with purpose', 'Use your gifts to make a difference.'], 'next_steps' => ['Start here', 'We will help you find your next step.']]],
        'editorial' => ['name' => 'Editorial', 'description' => 'A content-first home for teaching, sermons, and resources.', 'layout' => 'editorial', 'eyebrows' => ['A WORD FOR TODAY', 'TEACHING THAT FORMS US', 'KEEP EXPLORING'], 'slides' => [['Wisdom for faithful everyday life.', 'Watch, listen, and return to messages that meet you where you are.'], ['Go deeper in the Word.', 'Explore teaching series and resources at your own pace.'], ['Share what is shaping you.', 'Invite someone into a message that has encouraged you.']], 'sections' => ['sermon_spotlight' => ['Featured teaching', 'Start with a message chosen for this season.'], 'video' => ['Watch recent messages', 'Teaching you can return to whenever you need it.'], 'audio' => ['Listen on the go', 'Take encouragement with you through the week.'], 'books' => ['Keep learning', 'Resources for a life of growing faith.'], 'newsletter' => ['Stay in the conversation', 'Receive thoughtful updates and new teaching.']]],
        'modern' => ['name' => 'Modern', 'description' => 'A bold, action-oriented home for outreach and new connections.', 'layout' => 'cinematic', 'eyebrows' => ['FAITH IN MOTION', 'YOUR STORY MATTERS', 'TAKE THE NEXT STEP'], 'slides' => [['Hope changes everything.', 'Discover a welcoming community with room for your questions.'], ['We are here for our city.', 'Join practical love in action, right where you are.'], ['Start a new chapter.', 'Come explore faith with honest people and open hearts.']], 'sections' => ['welcome' => ['There is room for you', 'Show up exactly as you are.'], 'events' => ['What is next', 'Find a gathering, conversation, or way to get involved.'], 'groups' => ['Grow with others', 'You do not have to navigate life alone.'], 'prayer' => ['We are listening', 'Let us stand with you in prayer.'], 'giving' => ['Fuel the mission', 'Help make hope tangible in our community.'], 'next_steps' => ['Begin here', 'A simple first step can change the direction of a life.']]],
    ];

    public function settings(): HomepageSetting
    {
        return HomepageSetting::firstOrCreate(['id' => HomepageSetting::SINGLETON_ID], ['theme_family' => 'sanctuary', 'hero_layout' => 'immersive']);
    }

    public function updateAppearance(array $data, User $user, ?string $ip): HomepageSetting
    {
        return DB::transaction(function () use ($data, $user, $ip): HomepageSetting {
            $settings = $this->settings();
            $settings->fill([...$data, 'updated_by' => $user->id])->save();
            $this->audit($user, 'homepage.appearance_updated', 'homepage_settings', $settings->id, $data, $ip);
            return $settings;
        });
    }

    public function starterThemes(): array
    {
        $features = FeatureSetting::query()->where('state', 'enabled')->pluck('key')->all();

        return collect(self::STARTER_THEMES)->map(function (array $theme, string $key) use ($features): array {
            $sections = collect(array_keys($theme['sections']))->filter(fn (string $section) => $this->sectionIsAvailable($section, $features))->values()->all();

            return ['key' => $key, 'name' => $theme['name'], 'description' => $theme['description'], 'layout' => $theme['layout'], 'sections' => $sections, 'section_count' => count($sections)];
        })->values()->all();
    }

    public function applyStarterTheme(string $key, User $user, ?string $ip): void
    {
        abort_unless(isset(self::STARTER_THEMES[$key]), 422, 'That homepage theme is unavailable.');
        $theme = self::STARTER_THEMES[$key];

        DB::transaction(function () use ($key, $theme, $user, $ip): void {
            HomepageSlide::query()->delete();
            HomepageSection::query()->delete();
            $settings = $this->settings();
            $settings->fill(['theme_family' => $key, 'hero_layout' => $theme['layout'], 'updated_by' => $user->id])->save();

            foreach ($theme['slides'] as $position => $slide) {
                $slide = is_array($slide) && array_is_list($slide)
                    ? ['title' => $slide[0], 'body' => $slide[1]]
                    : $slide;

                HomepageSlide::query()->create([
                    'position' => $position + 1,
                    'is_published' => true,
                    'media_type' => $slide['media_type'] ?? 'image',
                    'media_url' => $slide['media_url'] ?? null,
                    'eyebrow' => $slide['eyebrow'] ?? $theme['eyebrows'][$position],
                    'title' => $slide['title'],
                    'body' => $slide['body'] ?? null,
                    'button_label' => $slide['button_label'] ?? null,
                    'button_url' => $slide['button_url'] ?? null,
                    'alt_text' => $slide['alt_text'] ?? $theme['name'].' starter slide',
                    'updated_by' => $user->id,
                ]);
            }
            foreach ($theme['sections'] as $position => $section) {
                HomepageSection::query()->create(['section_type' => $position, 'position' => array_search($position, array_keys($theme['sections']), true) + 1, 'is_enabled' => true, 'settings' => ['title' => $section[0], 'body' => $section[1]], 'updated_by' => $user->id]);
            }
            $this->audit($user, 'homepage.starter_theme_applied', 'homepage_settings', $settings->id, ['theme' => $key, 'replaced_slides' => true, 'replaced_sections' => true], $ip);
        });
    }

    public function saveSlide(array $data, User $user, ?string $ip): HomepageSlide
    {
        return DB::transaction(function () use ($data, $user, $ip): HomepageSlide {
            $id = $data['id'] ?? null;
            $slide = $id ? HomepageSlide::query()->findOrFail($id) : new HomepageSlide();
            if (! $id && HomepageSlide::query()->count() >= 4) {
                abort(422, 'A homepage supports up to four carousel slides.');
            }
            $position = (int) ($data['position'] ?? (HomepageSlide::max('position') ?? 0) + 1);
            $occupied = HomepageSlide::query()->where('position', $position)->when($id, fn ($query) => $query->where('id', '!=', $id))->exists();
            if ($occupied) {
                $position = (HomepageSlide::max('position') ?? 0) + 1;
            }
            $slide->fill([...$data, 'position' => $position, 'updated_by' => $user->id]);
            $slide->save();
            $this->audit($user, 'homepage.slide_saved', 'homepage_slides', $slide->id, ['position' => $slide->position, 'published' => $slide->is_published], $ip);
            return $slide;
        });
    }

    public function saveSection(array $data, User $user, ?string $ip): HomepageSection
    {
        return DB::transaction(function () use ($data, $user, $ip): HomepageSection {
            $section = HomepageSection::firstOrNew(['section_type' => $data['section_type']]);
            $section->fill([...$data, 'position' => $section->exists ? $section->position : (HomepageSection::max('position') ?? 0) + 1, 'updated_by' => $user->id]);
            $section->save();
            $this->audit($user, 'homepage.section_saved', 'homepage_sections', $section->id, ['type' => $section->section_type, 'enabled' => $section->is_enabled], $ip);
            return $section;
        });
    }

    public function deleteSlide(HomepageSlide $slide, User $user, ?string $ip): void
    {
        DB::transaction(function () use ($slide, $user, $ip): void {
            $title = $slide->title;
            $id = $slide->id;
            $slide->delete();
            $this->normalizePositions(HomepageSlide::query());
            $this->audit($user, 'homepage.slide_deleted', 'homepage_slides', $id, ['title' => $title], $ip);
        });
    }

    public function deleteSection(HomepageSection $section, User $user, ?string $ip): void
    {
        DB::transaction(function () use ($section, $user, $ip): void {
            $type = $section->section_type;
            $id = $section->id;
            $section->delete();
            $this->normalizePositions(HomepageSection::query());
            $this->audit($user, 'homepage.section_deleted', 'homepage_sections', $id, ['type' => $type], $ip);
        });
    }

    public function reorderSlides(array $ids, User $user, ?string $ip): void
    {
        $this->reorder(HomepageSlide::class, $ids, $user, 'homepage.slides_reordered', 'homepage_slides', $ip);
    }

    public function reorderSections(array $ids, User $user, ?string $ip): void
    {
        $this->reorder(HomepageSection::class, $ids, $user, 'homepage.sections_reordered', 'homepage_sections', $ip);
    }

    public function publicConfiguration(LiveStreamService $streams): array
    {
        $settings = $this->settings();
        $features = FeatureSetting::query()->where('state', 'enabled')->pluck('key')->all();
        $live = in_array('digital_church', $features, true)
            ? ServiceOccurrence::query()->where('rehearsal_mode', false)->where('state', 'live')->orderBy('starts_at')->get()->first(function (ServiceOccurrence $service) use ($streams): bool {
                if ($service->service_type === 'meeting') {
                    return DB::table('meetings')->where('service_occurrence_id', $service->id)->whereIn('status', ['scheduled', 'live'])->exists();
                }

                return $service->manual_stream_override || $streams->usable($service);
            })
            : null;
        return [
            'settings' => $settings,
            'features' => $features,
            'liveService' => $live,
            'slides' => HomepageSlide::query()->where('is_published', true)->orderBy('position')->get(),
            'sections' => $this->visibleSections($features),
            'homepageContent' => $this->homepageContent($features),
        ];
    }

    /** @return array<string, Collection<int, object>> */
    private function homepageContent(array $features): array
    {
        $services = DB::table('service_occurrences')
            ->leftJoin('locations', 'locations.id', '=', 'service_occurrences.location_id')
            ->where('service_occurrences.rehearsal_mode', false)
            ->whereIn('service_occurrences.state', ['scheduled', 'ready'])
            ->where('service_occurrences.starts_at', '>=', now())
            ->orderBy('service_occurrences.starts_at')
            // Fetch enough occurrences to find the first upcoming item for each recurring schedule.
            // The public homepage should never render a row for every future repetition.
            ->limit(24)
            ->get(['service_occurrences.id', 'service_occurrences.service_schedule_id', 'service_occurrences.title', 'service_occurrences.starts_at', 'service_occurrences.timezone', 'service_occurrences.service_type', 'locations.name as location_name'])
            ->unique(fn (object $service): string => $service->service_schedule_id ?: $service->id)
            ->take(3)
            ->values();

        $events = DB::table('events')
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->limit(4)
            ->get(['id', 'title', 'description', 'starts_at', 'location', 'registration_open']);

        $groups = in_array('groups', $features, true)
            ? DB::table('groups')->where('status', 'active')->orderBy('name')->limit(3)->get(['id', 'name', 'description'])
            : collect();

        $resources = DB::table('media_assets')
            ->where('status', 'published')
            ->when($features !== [], fn ($query) => $query->whereIn('library', array_values(array_intersect($features, ['books_library', 'audio_sermons', 'video_sermons', 'sermon_series']))))
            ->orderByDesc('published_at')
            ->limit(5)
            ->get(['id', 'title', 'description', 'library', 'kind', 'speaker', 'duration_seconds']);

        return [
            'services' => $services,
            'events' => $events,
            'groups' => $groups,
            'resources' => $resources,
            'locations' => DB::table('locations')->where('is_public', true)->orderByDesc('is_primary')->orderBy('name')->limit(3)->get(['name', 'address', 'timezone']),
        ];
    }

    private function visibleSections(array $features): Collection
    {
        return HomepageSection::query()->where('is_enabled', true)->orderBy('position')->get()->filter(fn (HomepageSection $section) => $this->sectionIsAvailable($section->section_type, $features));
    }

    private function sectionIsAvailable(string $section, array $features): bool
    {
        $requirements = ['live_service' => 'digital_church', 'groups' => 'groups', 'giving' => 'giving', 'books' => 'books_library', 'audio' => 'audio_sermons', 'video' => 'video_sermons'];

        return ! isset($requirements[$section]) || in_array($requirements[$section], $features, true);
    }

    private function audit(User $user, string $action, string $type, string $id, array $changes, ?string $ip): void
    {
        DB::table('audit_events')->insert(['id' => (string) Str::uuid(), 'actor_user_id' => $user->id, 'action' => $action, 'subject_type' => $type, 'subject_id' => $id, 'changes_json' => json_encode($changes), 'ip_address' => $ip, 'created_at' => now()]);
    }

    private function reorder(string $model, array $ids, User $user, string $action, string $type, ?string $ip): void
    {
        DB::transaction(function () use ($model, $ids, $user, $action, $type, $ip): void {
            $records = $model::query()->lockForUpdate()->orderBy('position')->get();
            $existing = $records->pluck('id')->all();
            abort_unless(count($ids) === count($existing) && count(array_unique($ids)) === count($ids) && empty(array_diff($ids, $existing)) && empty(array_diff($existing, $ids)), 422, 'The homepage order is out of date. Refresh and try again.');

            // Positions are unique. Move all current values outside the final range before assigning their new order.
            foreach ($records as $record) {
                $record->update(['position' => $record->position + 1000]);
            }
            foreach (array_values($ids) as $position => $id) {
                $model::query()->whereKey($id)->update(['position' => $position + 1, 'updated_by' => $user->id]);
            }
            $this->audit($user, $action, $type, 'collection', ['order' => $ids], $ip);
        });
    }

    private function normalizePositions($query): void
    {
        $records = $query->lockForUpdate()->orderBy('position')->get();
        foreach ($records as $record) {
            $record->update(['position' => $record->position + 1000]);
        }
        foreach ($records->values() as $position => $record) {
            $record->update(['position' => $position + 1]);
        }
    }
}
