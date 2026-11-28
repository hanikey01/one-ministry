<?php

namespace App\Services;

use App\Support\MinistryTerminology;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class NavigationLayout
{
    public const SURFACES = ['staff', 'member'];

    public const ICONS = ['home', 'worship', 'calendar', 'departments', 'people', 'services', 'content', 'giving', 'location', 'message', 'school', 'followup', 'care', 'reports', 'modules', 'access', 'security', 'settings', 'homepage', 'menu', 'external', 'about', 'activity', 'announcement', 'archive', 'arrow-right', 'audio', 'book', 'book-open', 'briefcase', 'building', 'camera', 'chat', 'check', 'clock', 'coffee', 'compass', 'download', 'email', 'file', 'flag', 'globe', 'hand-heart', 'heart', 'help', 'image', 'info', 'link', 'list', 'lock', 'map', 'music', 'news', 'phone', 'pin', 'play', 'podcast', 'prayer', 'search', 'send', 'share', 'sparkles', 'star', 'support', 'video', 'wifi'];

    /** @return array<string, array{label:string, icon:string, route:string, match:string, group:string, permission:?string, module:?string, feature:?string}> */
    public function options(string $surface): array
    {
        $definitions = match ($surface) {
            'staff' => $this->staffOptions(),
            'member' => $this->memberOptions(),
            default => [],
        };

        foreach ($this->publishedPages() as $page) {
            $definitions['page:'.$page->slug] = [
                'label' => $page->title,
                'icon' => 'content',
                'route' => 'public.page',
                'match' => 'public.page',
                'group' => $surface === 'staff' ? 'Publishing' : 'My ministry',
                'permission' => null,
                'module' => null,
                'feature' => null,
                'parameter' => $page->slug,
            ];
        }

        return $definitions;
    }

    public function defaultsFor(string $surface, string $slot): array
    {
        return $this->defaults($surface, $slot, $this->options($surface));
    }

    /** @return array<int, array{key:string,label:string,icon:string,url:string,match:string,group:string,parent:?string}> */
    public function entries(string $surface, string $slot, ?object $user, array $features): array
    {
        abort_unless(in_array($surface, self::SURFACES, true) && in_array($slot, ['sidebar', 'mobile'], true), 500);

        $request = app(Request::class);
        $cacheKey = 'navigation_entries_'.$surface.'_'.$slot.'_'.($user?->id ?? 'guest').'_'.md5(json_encode($features));
        if ($request->attributes->has($cacheKey)) {
            return $request->attributes->get($cacheKey);
        }

        $options = $this->options($surface);
        $layout = $this->layoutFor($surface);
        $stored = $layout ? json_decode($slot === 'sidebar' ? $layout->sidebar_items : $layout->mobile_items, true) : null;
        $configured = is_array($stored) && ($slot !== 'mobile' || count($stored) >= 5) ? $stored : $this->defaults($surface, $slot, $options);
        $permissions = $surface === 'staff' && $user ? collect(app(PermissionChecker::class)->installationPermissions($user)) : collect();
        if ($surface === 'staff' && $slot === 'sidebar' && $permissions->contains('content.manage') && ! collect($configured)->contains(fn ($entry) => (is_string($entry) ? $entry : ($entry['key'] ?? null)) === 'staff.navigation')) {
            $configured[] = ['key' => 'staff.navigation', 'icon' => $options['staff.navigation']['icon']];
        }
        if ($surface === 'staff' && $slot === 'sidebar' && $permissions->contains('care.manage') && ! collect($configured)->contains(fn ($entry) => (is_string($entry) ? $entry : ($entry['key'] ?? null)) === 'staff.care')) {
            $configured[] = ['key' => 'staff.care', 'icon' => $options['staff.care']['icon']];
        }
        $featureStates = $surface === 'staff' && $user ? DB::table('feature_settings')->pluck('state', 'key') : collect();
        $canPreviewModules = $permissions->contains('settings.read');
        $terminology = app(MinistryTerminology::class);
        $entries = [];

        foreach ($configured as $configuration) {
            $key = is_string($configuration) ? $configuration : ($configuration['key'] ?? null);
            if (! is_string($key) || ! isset($options[$key])) {
                continue;
            }

            $definition = $options[$key];
            if ($surface === 'staff' && (! $user || ($definition['permission'] && ! $permissions->contains($definition['permission'])) || ($definition['module'] && ! in_array($featureStates->get($definition['module']), ['enabled', ...($canPreviewModules ? ['staff_preview'] : [])], true)))) {
                continue;
            }
            if ($key === 'staff.departments' && ! app(DepartmentService::class)->canSeeAny($user)) {
                continue;
            }
            if ($definition['feature'] && ! in_array($definition['feature'], $features, true)) {
                continue;
            }

            $label = match ($key) {
                'staff.people' => $terminology->label('person', 'plural'),
                'staff.services' => $terminology->label('service', 'plural'),
                'staff.events' => $terminology->label('event', 'plural'),
                'staff.locations' => $terminology->label('location', 'plural'),
                'staff.departments' => $terminology->label('department', 'plural'),
                'staff.testimonies' => $terminology->label('testimony', 'plural'),
                'member.departments' => 'My '.mb_strtolower($terminology->label('department', 'plural')),
                'member.events' => $terminology->label('event', 'plural'),
                'member.next-steps' => $terminology->label('next_step', 'plural'),
                'member.testimonies' => 'My '.mb_strtolower($terminology->label('testimony', 'plural')),
                default => $definition['label'],
            };
            $parameter = $definition['parameter'] ?? null;
            $url = $parameter ? route($definition['route'], $parameter) : route($definition['route']);

            $entries[] = [
                'key' => $key,
                'label' => $label,
                'icon' => is_array($configuration) && in_array($configuration['icon'] ?? null, self::ICONS, true) ? $configuration['icon'] : $definition['icon'],
                'url' => $url,
                'match' => $definition['match'],
                'group' => is_array($configuration) && in_array($configuration['group'] ?? null, array_column($options, 'group'), true)
                    ? $configuration['group']
                    : $definition['group'],
                'parent' => $slot === 'sidebar' && is_array($configuration) && is_string($configuration['parent'] ?? null)
                    ? $configuration['parent']
                    : null,
            ];
        }

        $availableKeys = array_fill_keys(array_column($entries, 'key'), true);
        foreach ($entries as &$entry) {
            if ($entry['parent'] === $entry['key'] || ! isset($availableKeys[$entry['parent'] ?? ''])) {
                $entry['parent'] = null;
            }
        }
        unset($entry);

        if ($slot === 'mobile') {
            $entries = array_slice($entries, 0, 5);
        }

        $request->attributes->set($cacheKey, $entries);

        return $entries;
    }

    /** @return array<int, array<string, mixed>> */
    public function treeEntries(string $surface, string $slot, ?object $user, array $features): array
    {
        return $this->toTree($this->entries($surface, $slot, $user, $features));
    }

    /** @return array<int, array<string, mixed>> */
    public function publicTree(array $features): array
    {
        $entries = DB::table('navigation_items')
            ->where('is_enabled', true)
            ->where('visible_desktop', true)
            ->orderBy('position')
            ->get()
            ->filter(fn (object $item): bool => ! $item->required_feature || in_array($item->required_feature, $features, true))
            ->map(fn (object $item): array => [
                'key' => (string) $item->id,
                'parent' => $item->parent_id ? (string) $item->parent_id : null,
                'label' => $item->label,
                'url' => $item->url,
                'icon' => $item->icon ?: 'external',
                'section' => $item->section ?: 'Main',
                'open_new_tab' => (bool) $item->open_new_tab,
            ])->values()->all();

        return $this->toTree($entries);
    }

    /**
     * Convert a validated flat navigation list into a stable tree. Orphaned or
     * cyclic entries remain available at the root rather than disappearing.
     *
     * @param array<int, array<string, mixed>> $entries
     * @return array<int, array<string, mixed>>
     */
    public function toTree(array $entries): array
    {
        $byKey = [];
        foreach ($entries as $entry) {
            if (! is_string($entry['key'] ?? null)) {
                continue;
            }
            $entry['children'] = [];
            $byKey[$entry['key']] = $entry;
        }

        $children = [];
        $roots = [];
        foreach ($byKey as $key => $entry) {
            $parent = is_string($entry['parent'] ?? null) ? $entry['parent'] : null;
            if ($parent && $parent !== $key && isset($byKey[$parent]) && ! $this->wouldCycle($key, $parent, $byKey)) {
                $children[$parent][] = $key;
            } else {
                $roots[] = $key;
            }
        }

        $build = function (string $key, int $depth = 0) use (&$build, $byKey, $children): array {
            $entry = $byKey[$key];
            $entry['depth'] = $depth;
            $entry['children'] = array_map(fn (string $child): array => $build($child, $depth + 1), $children[$key] ?? []);

            return $entry;
        };

        return array_map(fn (string $key): array => $build($key), $roots);
    }

    /** @param array<string, array<string, mixed>> $entries */
    private function wouldCycle(string $key, string $parent, array $entries): bool
    {
        $seen = [$key => true];
        while (isset($entries[$parent])) {
            if (isset($seen[$parent])) {
                return true;
            }
            $seen[$parent] = true;
            $parent = is_string($entries[$parent]['parent'] ?? null) ? $entries[$parent]['parent'] : '';
        }

        return false;
    }

    /** @return array<string, array{label:string, icon:string, route:string, match:string, group:string, permission:?string, module:?string, feature:?string}> */
    private function staffOptions(): array
    {
        return [
            'staff.overview' => $this->item('Overview', 'home', 'staff.overview', 'Workspace', 'settings.read'),
            'staff.people' => $this->item('People', 'people', 'staff.people', 'Workspace', 'services.manage', 'people'),
            'staff.services' => $this->item('Services', 'services', 'staff.services', 'Workspace', 'services.manage', 'digital_church'),
            'staff.follow-up' => $this->item('Follow-up', 'followup', 'staff.follow-up', 'Workspace', 'services.manage', null, 'people'),
            'staff.care' => $this->item('Care', 'care', 'staff.care', 'Workspace', 'care.manage'),
            'staff.prayer-requests' => $this->item('Prayer requests', 'prayer', 'staff.prayer-requests', 'Workspace', 'prayer.read'),
            'staff.events' => $this->item('Events', 'calendar', 'staff.events', 'Ministry', 'community.manage'),
            'staff.departments' => $this->item('Departments', 'departments', 'staff.departments', 'Ministry'),
            'staff.school.index' => $this->item('Digital school', 'school', 'staff.school.index', 'Ministry', 'school.manage', 'digital_school'),
            'staff.giving' => $this->item('Giving', 'giving', 'staff.giving', 'Ministry', 'giving.manage', null, 'giving'),
            'staff.reports' => $this->item('Reports', 'reports', 'staff.reports', 'Ministry', 'reports.view'),
            'staff.content' => $this->item('Content library', 'content', 'staff.content', 'Publishing', 'content.manage'),
            'staff.testimonies' => $this->item('Testimonies','sparkles','staff.testimonies','Publishing','content.manage','testimonies'),
            'staff.pages' => $this->item('Pages', 'content', 'staff.pages', 'Publishing', 'content.manage'),
            'staff.navigation' => $this->item('Navigation', 'menu', 'staff.navigation', 'Publishing', 'content.manage'),
            'staff.media-library' => $this->item('Media library', 'image', 'staff.media-library', 'Publishing', 'content.manage'),
            'staff.locations' => $this->item('Locations', 'location', 'staff.locations', 'Administration', 'locations.read'),
            'staff.modules' => $this->item('Modules', 'modules', 'staff.modules', 'Administration', 'features.read'),
            'staff.access' => $this->item('Staff access', 'access', 'staff.access', 'Administration', 'roles.manage'),
            'staff.ai-settings' => $this->item('AI model settings', 'settings', 'staff.ai-settings', 'Administration', 'ai.manage'),
            'staff.terminology' => $this->item('Terminology', 'message', 'staff.terminology', 'Administration', 'settings.update'),
            'staff.email' => $this->item('Email', 'email', 'staff.email', 'Administration', 'settings.read'),
            'staff.security' => $this->item('Security', 'security', 'staff.security', 'Administration'),
            'staff.settings' => $this->item('Settings', 'settings', 'staff.settings', 'Administration', 'settings.read'),
        ];
    }

    /** @return array<string, array{label:string, icon:string, route:string, match:string, group:string, permission:?string, module:?string, feature:?string}> */
    private function memberOptions(): array
    {
        return [
            'member.home' => $this->item('Overview', 'home', 'member.home', 'My ministry'),
            'member.departments' => $this->item('Departments', 'departments', 'member.departments', 'My ministry'),
            'member.events' => $this->item('Events', 'calendar', 'member.events', 'My ministry'),
            'member.school.index' => $this->item('Digital school', 'school', 'member.school.index', 'My ministry', null, null, 'digital_school'),
            'member.activity' => $this->item('Participation', 'services', 'member.activity', 'My ministry', null, null, 'attendance'),
            'member.next-steps' => $this->item('Next steps', 'followup', 'member.next-steps', 'My ministry'),
            'member.prayer.page' => $this->item('Prayer', 'care', 'member.prayer.page', 'My ministry'),
            'member.testimonies' => $this->item('My testimonies','sparkles','member.testimonies','My ministry',null,null,'testimonies'),
            'member.giving' => $this->item('Giving', 'giving', 'member.giving', 'My account', null, null, 'giving'),
            'member.profile' => $this->item('Profile', 'people', 'member.profile', 'My account'),
        ];
    }

    private function item(string $label, string $icon, string $route, string $group, ?string $permission = null, ?string $module = null, ?string $feature = null): array
    {
        $match = $route;
        return compact('label', 'icon', 'route', 'match', 'group', 'permission', 'module', 'feature');
    }

    private function defaults(string $surface, string $slot, array $options): array
    {
        $keys = match ([$surface, $slot]) {
            ['staff', 'sidebar'] => array_keys($options),
            ['staff', 'mobile'] => ['staff.overview', 'staff.people', 'staff.events', 'staff.content', 'staff.settings'],
            ['member', 'sidebar'] => array_keys($options),
            ['member', 'mobile'] => ['member.home', 'member.next-steps', 'member.prayer.page', 'member.departments', 'member.profile'],
            default => [],
        };

        return array_values(array_filter($keys, fn (string $key): bool => isset($options[$key]) && ! str_starts_with($key, 'page:')));
    }

    private function publishedPages(): iterable
    {
        $request = app(Request::class);
        if (! $request->attributes->has('navigation_published_pages')) {
            $request->attributes->set('navigation_published_pages', DB::table('pages')->where('status', 'published')->orderBy('title')->get(['title', 'slug']));
        }

        return $request->attributes->get('navigation_published_pages');
    }

    private function layoutFor(string $surface): ?object
    {
        $request = app(Request::class);
        $key = 'navigation_layout_'.$surface;
        if (! $request->attributes->has($key)) {
            $request->attributes->set($key, DB::table('navigation_layouts')->where('surface', $surface)->first());
        }

        return $request->attributes->get($key);
    }
}
