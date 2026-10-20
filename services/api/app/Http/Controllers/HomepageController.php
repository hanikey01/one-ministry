<?php

namespace App\Http\Controllers;

use App\Models\MinistrySetting;
use App\Models\HomepageSection;
use App\Models\HomepageSlide;
use App\Services\HomepageService;
use App\Services\LiveStreamService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class HomepageController extends Controller
{
    public function show(HomepageService $homepage, LiveStreamService $streams): View|RedirectResponse
    {
        if (! MinistrySetting::query()->exists()) {
            return to_route('setup.create');
        }

        return view('public.home', ['ministry' => MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID), ...$homepage->publicConfiguration($streams)]);
    }

    public function editor(Request $request, HomepageService $homepage): View
    {
        return view('staff.homepage', ['settings' => MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID), 'user' => $request->user(), 'homepage' => $homepage->settings(), 'slides' => \App\Models\HomepageSlide::query()->orderBy('position')->get(), 'sections' => \App\Models\HomepageSection::query()->orderBy('position')->get(), 'starterThemes' => $homepage->starterThemes(), 'themes' => HomepageService::THEMES, 'layouts' => HomepageService::LAYOUTS, 'sectionTypes' => HomepageService::SECTIONS]);
    }

    public function updateAppearance(Request $request, HomepageService $homepage): RedirectResponse
    {
        $data = $request->validate(['theme_family' => ['required', Rule::in(HomepageService::THEMES)], 'hero_layout' => ['required', Rule::in(HomepageService::LAYOUTS)]]);
        $homepage->updateAppearance($data, $request->user(), $request->ip());
        return back()->with('status', 'Homepage theme saved.');
    }

    public function applyStarterTheme(Request $request, string $theme, HomepageService $homepage): RedirectResponse
    {
        $homepage->applyStarterTheme($theme, $request->user(), $request->ip());
        return back()->with('status', 'Starter theme applied. Review the editable sections, then publish your changes when ready.');
    }

    public function saveSlide(Request $request, HomepageService $homepage): RedirectResponse
    {
        $data = $request->validate(['id' => ['nullable', 'uuid'], 'media_type' => ['required', Rule::in(['image', 'video', 'external_video'])], 'media_url' => ['nullable', 'url', 'max:1200'], 'media_upload' => ['nullable', 'file', 'max:102400'], 'eyebrow' => ['nullable', 'string', 'max:80'], 'title' => ['required', 'string', 'max:180'], 'body' => ['nullable', 'string', 'max:2000'], 'button_label' => ['nullable', 'string', 'max:60'], 'button_url' => ['nullable', 'url', 'max:1200'], 'alt_text' => ['nullable', 'string', 'max:180'], 'is_published' => ['nullable', 'boolean']]);
        if ($data['media_type'] === 'external_video' && filled($data['media_url'] ?? null)) {
            $host = strtolower((string) parse_url($data['media_url'], PHP_URL_HOST));
            abort_unless(in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be', 'vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true), 422, 'External hero video must use YouTube or Vimeo.');
        }
        if ($request->hasFile('media_upload')) {
            $upload = $request->file('media_upload');
            $allowed = $data['media_type'] === 'image' ? ['image/jpeg', 'image/png', 'image/webp', 'image/gif'] : ['video/mp4', 'video/webm', 'video/ogg'];
            abort_unless(in_array($upload->getMimeType(), $allowed, true), 422, 'The uploaded file does not match the selected media type.');
            $data['media_url'] = Storage::disk('public')->url($upload->store('homepage', 'public'));
        }
        unset($data['media_upload']);
        $data['is_published'] = $request->boolean('is_published');
        $homepage->saveSlide($data, $request->user(), $request->ip());
        return back()->with('status', 'Carousel slide saved.');
    }

    public function saveSection(Request $request, HomepageService $homepage): RedirectResponse
    {
        $data = $request->validate(['section_type' => ['required', Rule::in(HomepageService::SECTIONS)], 'is_enabled' => ['nullable', 'boolean'], 'title' => ['nullable', 'string', 'max:160'], 'body' => ['nullable', 'string', 'max:1600'], 'button_label' => ['nullable', 'string', 'max:60'], 'button_url' => ['nullable', 'url', 'max:1200']]);
        $homepage->saveSection(['section_type' => $data['section_type'], 'is_enabled' => $request->boolean('is_enabled'), 'settings' => collect($data)->only(['title', 'body', 'button_label', 'button_url'])->filter()->all()], $request->user(), $request->ip());
        return back()->with('status', 'Homepage section saved.');
    }

    public function destroySlide(Request $request, HomepageSlide $slide, HomepageService $homepage): RedirectResponse
    {
        $homepage->deleteSlide($slide, $request->user(), $request->ip());
        return back()->with('status', 'Carousel slide removed.');
    }

    public function destroySection(Request $request, HomepageSection $section, HomepageService $homepage): RedirectResponse
    {
        $homepage->deleteSection($section, $request->user(), $request->ip());
        return back()->with('status', 'Homepage section removed.');
    }

    public function reorderSlides(Request $request, HomepageService $homepage): RedirectResponse
    {
        $data = $request->validate(['order' => ['present', 'array'], 'order.*' => ['uuid']]);
        $homepage->reorderSlides($data['order'], $request->user(), $request->ip());
        return back()->with('status', 'Carousel order updated.');
    }

    public function reorderSections(Request $request, HomepageService $homepage): RedirectResponse
    {
        $data = $request->validate(['order' => ['present', 'array'], 'order.*' => ['uuid']]);
        $homepage->reorderSections($data['order'], $request->user(), $request->ip());
        return back()->with('status', 'Homepage section order updated.');
    }
}
