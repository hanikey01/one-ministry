<?php

namespace App\Http\Controllers;

use App\Models\FeatureSetting;
use App\Models\MinistrySetting;
use App\Services\NavigationLayout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NavigationWorkspaceController extends Controller
{
    private const ICONS = NavigationLayout::ICONS;
    private const PUBLIC_SECTIONS = ['Main', 'Connect', 'Resources', 'About', 'Member area'];

    public function index(Request $request, NavigationLayout $layout): View
    {
        $layouts = [];
        foreach (NavigationLayout::SURFACES as $surface) {
            $saved = DB::table('navigation_layouts')->where('surface', $surface)->first();
            $layouts[$surface] = [
                'sidebar' => $saved ? json_decode($saved->sidebar_items, true) : $layout->defaultsFor($surface, 'sidebar'),
                'mobile' => $saved ? json_decode($saved->mobile_items, true) : $layout->defaultsFor($surface, 'mobile'),
            ];
        }

        return view('staff.navigation', [
            'settings' => MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID),
            'user' => $request->user(),
            'items' => DB::table('navigation_items')->orderBy('parent_id')->orderBy('position')->get(),
            'pages' => DB::table('pages')->where('status', 'published')->orderBy('title')->get(['title', 'slug']),
            'features' => FeatureSetting::query()->pluck('key')->all(),
            'icons' => self::ICONS,
            'publicSections' => self::PUBLIC_SECTIONS,
            'destinations' => $this->destinations(),
            'menuLayouts' => $layouts,
            'menuOptions' => collect(NavigationLayout::SURFACES)->mapWithKeys(fn (string $surface): array => [$surface => $layout->options($surface)])->all(),
        ]);
    }

    public function saveLayouts(Request $request, NavigationLayout $layout): RedirectResponse
    {
        $rules = ['menus' => ['required', 'array:staff,member']];
        foreach (NavigationLayout::SURFACES as $surface) {
            $rules["menus.{$surface}"] = ['required', 'array:sidebar,mobile'];
            foreach (['sidebar', 'mobile'] as $slot) {
                $rules["menus.{$surface}.{$slot}"] = ['required', 'array', 'min:1'];
                if (! ($surface === 'staff' && $slot === 'sidebar')) {
                    $rules["menus.{$surface}.{$slot}"][] = 'max:24';
                }
                $rules["menus.{$surface}.{$slot}.*"] = ['required', 'string', 'max:255'];
            }
        }
        $rules['icons'] = ['nullable', 'array'];
        $rules['positions'] = ['nullable', 'array'];
        $rules['groups'] = ['sometimes', 'array'];
        foreach (NavigationLayout::SURFACES as $surface) {
            $rules["groups.{$surface}.sidebar"] = ['sometimes', 'array'];
            $rules["groups.{$surface}.sidebar.*"] = ['sometimes', 'string', 'max:60'];
        }
        $validated = $request->validate($rules);
        $menuData = [];

        foreach (NavigationLayout::SURFACES as $surface) {
            $options = $layout->options($surface);
            foreach (['sidebar', 'mobile'] as $slot) {
                $keys = array_values(array_unique($validated['menus'][$surface][$slot]));
                $maxItems = $slot === 'mobile' ? 5 : (($surface === 'staff' && $slot === 'sidebar') ? null : 24);
                if ($maxItems !== null && count($keys) > $maxItems) {
                    throw ValidationException::withMessages(['menus' => $slot === 'mobile' ? 'A mobile footer can contain up to five destinations.' : 'A sidebar can contain up to 24 destinations.']);
                }
                $items = [];
                foreach ($keys as $key) {
                    if (! isset($options[$key])) {
                        throw ValidationException::withMessages(['menus' => 'Choose a valid destination for each menu.']);
                    }
                    $fieldKey = str_replace([':', '.'], '_', $key);
                    $icon = data_get($request->input('icons', []), "{$surface}.{$slot}.{$fieldKey}", $options[$key]['icon']);
                    if (! in_array($icon, self::ICONS, true)) {
                        throw ValidationException::withMessages(['menus' => 'Choose an available menu icon.']);
                    }
                    $position = data_get($request->input('positions', []), "{$surface}.{$slot}.{$fieldKey}", count($items) + 1);
                    if (! is_numeric($position) || (int) $position < 1 || ($maxItems !== null && (int) $position > $maxItems)) {
                        throw ValidationException::withMessages(['menus' => 'Menu order must be within the available range.']);
                    }
                    $section = $slot === 'sidebar'
                        ? data_get($validated, "groups.{$surface}.sidebar.{$fieldKey}", $options[$key]['group'])
                        : null;
                    $allowedSections = array_values(array_unique(array_column($options, 'group')));
                    if ($slot === 'sidebar' && ! in_array($section, $allowedSections, true)) {
                        throw ValidationException::withMessages(['menus' => 'Choose an available sidebar section.']);
                    }
                    $items[] = [
                        'key' => $key,
                        'icon' => $icon,
                        'position' => (int) $position,
                        'group' => $section,
                    ];
                }
                $positions = array_column($items, 'position');
                if (count($positions) !== count(array_unique($positions))) {
                    throw ValidationException::withMessages(['menus' => 'Each destination needs a different order number.']);
                }
                usort($items, fn (array $a, array $b): int => $a['position'] <=> $b['position']);
                $menuData[$surface][$slot] = array_map(fn (array $item): array => array_filter([
                    'key' => $item['key'],
                    'icon' => $item['icon'],
                    'group' => $item['group'],
                ], fn ($value): bool => $value !== null), $items);
            }
        }

        DB::transaction(function () use ($menuData, $request): void {
            foreach ($menuData as $surface => $layout) {
                $values = [
                        'sidebar_items' => json_encode($layout['sidebar']),
                        'mobile_items' => json_encode($layout['mobile']),
                        'updated_by_user_id' => $request->user()->id,
                        'updated_at' => now(),
                    ];
                if (! DB::table('navigation_layouts')->where('surface', $surface)->exists()) {
                    $values['created_at'] = now();
                }
                DB::table('navigation_layouts')->updateOrInsert(['surface' => $surface], $values);
                $this->audit($request, 'navigation.layout_updated', MinistrySetting::SINGLETON_ID, ['surface' => $surface, 'sidebar' => $layout['sidebar'], 'mobile' => $layout['mobile']], 'navigation_layouts');
            }
        });

        return to_route('staff.navigation')->with('status', 'Sidebar and mobile menu choices saved.');
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $this->data($request);
        $id = (string) Str::uuid();
        $position = 0;
        DB::transaction(function () use ($data, $id, $request, &$position): void {
            $parentId = $data['parent_id'] ?? null;
            $positionQuery = DB::table('navigation_items');
            if ($parentId === null) {
                $positionQuery->whereNull('parent_id');
            } else {
                $positionQuery->where('parent_id', $parentId);
            }
            $position = (int) $positionQuery->max('position') + 1;
            DB::table('navigation_items')->insert([...$data, 'id' => $id, 'position' => $position, 'created_at' => now(), 'updated_at' => now()]);
            $this->audit($request, 'navigation.item_created', $id, ['label' => $data['label']]);
        });

        if ($request->expectsJson()) {
            $item = (object) [...$data, 'id' => $id, 'position' => $position];

            return response()->json([
                'message' => 'Navigation link added.',
                'html' => view('staff.navigation-item', [
                    'item' => $item,
                    'icons' => self::ICONS,
                    'publicSections' => self::PUBLIC_SECTIONS,
                    'items' => DB::table('navigation_items')->orderBy('parent_id')->orderBy('position')->get(),
                    'features' => FeatureSetting::query()->pluck('key')->all(),
                ])->render(),
            ], 201);
        }

        return back()->with('status', 'Navigation link added.');
    }

    public function update(Request $request, string $item): RedirectResponse
    {
        abort_unless(DB::table('navigation_items')->where('id', $item)->exists(), 404);
        $data = $this->data($request, $item);
        DB::transaction(function () use ($data, $item, $request): void {
            DB::table('navigation_items')->where('id', $item)->update([...$data, 'updated_at' => now()]);
            $this->audit($request, 'navigation.item_updated', $item, ['label' => $data['label']]);
        });
        return back()->with('status', 'Navigation link updated.');
    }

    public function destroy(Request $request, string $item): RedirectResponse
    {
        DB::transaction(function () use ($item, $request): void {
            DB::table('navigation_items')->where('parent_id', $item)->update(['parent_id' => null, 'updated_at' => now()]);
            DB::table('navigation_items')->where('id', $item)->delete();
            $this->audit($request, 'navigation.item_deleted', $item, []);
        });
        return back()->with('status', 'Navigation link removed.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'max:80'],
            'items.*' => ['uuid', Rule::exists('navigation_items', 'id')],
            'enabled' => ['sometimes', 'array', 'max:80'],
            'enabled.*' => ['uuid', Rule::exists('navigation_items', 'id')],
            'sections' => ['sometimes', 'array'],
            'sections.*' => ['required', Rule::in(self::PUBLIC_SECTIONS)],
            'icons' => ['sometimes', 'array'],
            'icons.*' => ['required', Rule::in(self::ICONS)],
        ]);
        DB::transaction(function () use ($data, $request): void {
            $enabled = array_flip($data['enabled'] ?? []);
            foreach ($data['items'] as $position => $id) {
                $changes = ['position' => $position + 1, 'is_enabled' => isset($enabled[$id]), 'updated_at' => now()];
                if (isset($data['sections'][$id])) $changes['section'] = $data['sections'][$id];
                if (isset($data['icons'][$id])) $changes['icon'] = $data['icons'][$id];
                DB::table('navigation_items')->where('id', $id)->update($changes);
            }
            $this->audit($request, 'navigation.reordered', MinistrySetting::SINGLETON_ID, [
                'count' => count($data['items']),
                'enabled' => $data['enabled'] ?? [],
                'sections' => $data['sections'] ?? [],
                'icons' => $data['icons'] ?? [],
            ]);
        });
        return back()->with('status', 'Public menu changes saved.');
    }

    /** @return array<int, array{label: string, url: string, icon: string, group: string, required_feature: ?string}> */
    private function destinations(): array
    {
        $core = [
            ['label' => 'Home', 'url' => '/', 'icon' => 'home', 'group' => 'Public pages', 'required_feature' => null],
            ['label' => 'Events', 'url' => '/events', 'icon' => 'calendar', 'group' => 'Public pages', 'required_feature' => null],
            ['label' => 'Locations', 'url' => '/locations', 'icon' => 'location', 'group' => 'Public pages', 'required_feature' => null],
            ['label' => 'Groups', 'url' => '/groups', 'icon' => 'groups', 'group' => 'Public pages', 'required_feature' => 'groups'],
            ['label' => 'Member dashboard', 'url' => '/member', 'icon' => 'people', 'group' => 'Member area', 'required_feature' => null],
            ['label' => 'My events', 'url' => '/member/events', 'icon' => 'calendar', 'group' => 'Member area', 'required_feature' => null],
            ['label' => 'My groups', 'url' => '/member/groups', 'icon' => 'groups', 'group' => 'Member area', 'required_feature' => 'groups'],
            ['label' => 'My serving', 'url' => '/member/serving', 'icon' => 'volunteer', 'group' => 'Member area', 'required_feature' => null],
            ['label' => 'Prayer', 'url' => '/member/prayer', 'icon' => 'care', 'group' => 'Member area', 'required_feature' => null],
            ['label' => 'My profile', 'url' => '/member/profile', 'icon' => 'settings', 'group' => 'Member area', 'required_feature' => null],
        ];
        $pages = DB::table('pages')->where('status', 'published')->orderBy('title')->get()->map(fn (object $page): array => ['label' => $page->title, 'url' => '/pages/'.$page->slug, 'icon' => 'content', 'group' => 'Published pages', 'required_feature' => null])->all();

        return [...$core, ...$pages];
    }

    private function data(Request $request, ?string $item = null): array
    {
        $data = $request->validate(['label' => ['required', 'string', 'max:80'], 'url' => ['required', 'string', 'max:1200'], 'icon' => ['nullable', Rule::in(self::ICONS)], 'section' => ['nullable', Rule::in(self::PUBLIC_SECTIONS)], 'parent_id' => ['nullable', 'uuid', Rule::exists('navigation_items', 'id')->where(fn ($query) => $item ? $query->where('id', '!=', $item) : $query)], 'required_feature' => ['nullable', 'string', 'max:80', Rule::exists('feature_settings', 'key')]]);
        $url = trim($data['url']);
        abort_unless(str_starts_with($url, '/') || filter_var($url, FILTER_VALIDATE_URL), 422, 'Enter an internal path beginning with / or a valid URL.');
        $section = $data['section'] ?? ($item ? DB::table('navigation_items')->where('id', $item)->value('section') : 'Main');
        return [...$data, 'url' => $url, 'section' => $section ?: 'Main', 'parent_id' => $data['parent_id'] ?? null, 'visible_desktop' => $request->boolean('visible_desktop'), 'visible_mobile' => $request->boolean('visible_mobile'), 'open_new_tab' => $request->boolean('open_new_tab')];
    }

    private function audit(Request $request, string $action, string $subject, array $changes, string $subjectType = 'navigation_items'): void
    {
        DB::table('audit_events')->insert(['id' => (string) Str::uuid(), 'actor_user_id' => $request->user()->id, 'action' => $action, 'subject_type' => $subjectType, 'subject_id' => $subject, 'changes_json' => json_encode($changes), 'ip_address' => $request->ip(), 'created_at' => now()]);
    }
}
