<?php

namespace App\Http\Controllers;

use App\Models\FeatureSetting;
use App\Models\MinistrySetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NavigationWorkspaceController extends Controller
{
    private const ICONS = ['home', 'worship', 'calendar', 'groups', 'people', 'services', 'content', 'giving', 'location', 'message', 'volunteer', 'external'];

    public function index(Request $request): View
    {
        return view('staff.navigation', [
            'settings' => MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID),
            'user' => $request->user(),
            'items' => DB::table('navigation_items')->orderBy('parent_id')->orderBy('position')->get(),
            'pages' => DB::table('pages')->where('status', 'published')->orderBy('title')->get(['title', 'slug']),
            'features' => FeatureSetting::query()->pluck('key')->all(),
            'icons' => self::ICONS,
            'destinations' => $this->destinations(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->data($request);
        $id = (string) Str::uuid();
        DB::transaction(function () use ($data, $id, $request): void {
            $position = (int) DB::table('navigation_items')->where('parent_id', $data['parent_id'])->max('position') + 1;
            DB::table('navigation_items')->insert([...$data, 'id' => $id, 'position' => $position, 'created_at' => now(), 'updated_at' => now()]);
            $this->audit($request, 'navigation.item_created', $id, ['label' => $data['label']]);
        });
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
        $data = $request->validate(['items' => ['required', 'array', 'max:80'], 'items.*' => ['uuid', Rule::exists('navigation_items', 'id')]]);
        DB::transaction(function () use ($data, $request): void {
            foreach ($data['items'] as $position => $id) DB::table('navigation_items')->where('id', $id)->update(['position' => $position + 1, 'updated_at' => now()]);
            $this->audit($request, 'navigation.reordered', MinistrySetting::SINGLETON_ID, ['count' => count($data['items'])]);
        });
        return back()->with('status', 'Navigation order saved.');
    }

    /** @return array<int, array{label: string, url: string, icon: string, group: string}> */
    private function destinations(): array
    {
        $core = [
            ['label' => 'Home', 'url' => '/', 'icon' => 'home', 'group' => 'Public pages'],
            ['label' => 'Events', 'url' => '/events', 'icon' => 'calendar', 'group' => 'Public pages'],
            ['label' => 'Locations', 'url' => '/locations', 'icon' => 'location', 'group' => 'Public pages'],
            ['label' => 'Groups', 'url' => '/groups', 'icon' => 'groups', 'group' => 'Public pages'],
            ['label' => 'Member dashboard', 'url' => '/member', 'icon' => 'people', 'group' => 'Member area'],
            ['label' => 'My events', 'url' => '/member/events', 'icon' => 'calendar', 'group' => 'Member area'],
            ['label' => 'My groups', 'url' => '/member/groups', 'icon' => 'groups', 'group' => 'Member area'],
            ['label' => 'My serving', 'url' => '/member/serving', 'icon' => 'volunteer', 'group' => 'Member area'],
            ['label' => 'Prayer', 'url' => '/member/prayer', 'icon' => 'care', 'group' => 'Member area'],
            ['label' => 'My profile', 'url' => '/member/profile', 'icon' => 'settings', 'group' => 'Member area'],
        ];
        $pages = DB::table('pages')->where('status', 'published')->orderBy('title')->get()->map(fn (object $page): array => ['label' => $page->title, 'url' => '/pages/'.$page->slug, 'icon' => 'content', 'group' => 'Published pages'])->all();

        return [...$core, ...$pages];
    }

    private function data(Request $request, ?string $item = null): array
    {
        $data = $request->validate(['label' => ['required', 'string', 'max:80'], 'url' => ['required', 'string', 'max:1200'], 'icon' => ['nullable', Rule::in(self::ICONS)], 'parent_id' => ['nullable', 'uuid', Rule::exists('navigation_items', 'id')->where(fn ($query) => $item ? $query->where('id', '!=', $item) : $query)], 'required_feature' => ['nullable', 'string', 'max:80', Rule::exists('feature_settings', 'key')]]);
        $url = trim($data['url']);
        abort_unless(str_starts_with($url, '/') || filter_var($url, FILTER_VALIDATE_URL), 422, 'Enter an internal path beginning with / or a valid URL.');
        return [...$data, 'url' => $url, 'visible_desktop' => $request->boolean('visible_desktop'), 'visible_mobile' => $request->boolean('visible_mobile'), 'open_new_tab' => $request->boolean('open_new_tab')];
    }

    private function audit(Request $request, string $action, string $subject, array $changes): void
    {
        DB::table('audit_events')->insert(['id' => (string) Str::uuid(), 'actor_user_id' => $request->user()->id, 'action' => $action, 'subject_type' => 'navigation_items', 'subject_id' => $subject, 'changes_json' => json_encode($changes), 'ip_address' => $request->ip(), 'created_at' => now()]);
    }
}