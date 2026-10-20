<x-layouts.workspace title="Homepage" :ministry-name="$settings->name" :primary-color="$settings->primary_color" :accent-color="$settings->accent_color" :user-name="$user->name" :user-initials="str($user->name)->substr(0, 2)" user-role="Staff workspace">
    <header class="workspace-page-heading"><p>PUBLIC EXPERIENCE</p><h1>Homepage</h1><span>Start with a complete design, then make it your ministry’s own.</span></header>
    @if(session('status'))<div class="form-success">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="form-errors">{{ $errors->first() }}</div>@endif

    <details class="homepage-theme-gallery" @if($slides->isEmpty() && $sections->isEmpty()) open @endif>
        <summary class="homepage-theme-trigger">
            <span class="homepage-trigger-copy">
                <small>HOMEPAGE THEME</small>
                <strong>{{ $slides->isEmpty() && $sections->isEmpty() ? 'Choose a starting point' : str($homepage->theme_family)->headline().' theme' }}</strong>
                <em>{{ $slides->isEmpty() && $sections->isEmpty() ? 'Select a complete, editable homepage design.' : 'Browse themes and apply a fresh starting point.' }}</em>
            </span>
            <span class="homepage-trigger-action"><span>Change theme</span><x-ui.icon name="chevron-down" :size="16" /></span>
        </summary>
        <div class="homepage-theme-gallery-heading">
            <div>
                <p>DESIGN LIBRARY</p>
                <h2>Choose a homepage starting point</h2>
                <span>Preview a complete design before using it. Applying a theme replaces only homepage slides and sections with editable starter content, including hero media, headings, buttons, and section copy.</span>
            </div>
            <small class="homepage-gallery-note">Each theme includes three lead slides and curated sections.</small>
        </div>
        <div class="homepage-theme-cards">@foreach($starterThemes as $theme)
            <article class="homepage-theme-card theme-card-{{ $theme['key'] }}" @if($homepage->theme_family === $theme['key']) data-active-theme @endif>
                <div class="homepage-theme-visual" aria-hidden="true"><div class="homepage-mini-nav"></div><div class="homepage-mini-hero"><b>{{ $theme['name'] }}</b><i></i><i></i></div><div class="homepage-mini-sections">@foreach(array_slice($theme['sections'], 0, 3) as $section)<span>{{ str($section)->headline() }}</span>@endforeach</div></div>
                <div class="homepage-theme-copy">
                    <div><strong>{{ $theme['name'] }}</strong>@if($homepage->theme_family === $theme['key'])<em>Current</em>@endif</div>
                    <p>{{ $theme['description'] }}</p>
                    <span>{{ $theme['section_count'] }} available starter sections · 3 lead slides@if($theme['key'] === 'sanctuary') · Current premium design@endif</span>
                    <details class="homepage-inline-preview">
                        <summary>Preview this theme</summary>
                        <div class="homepage-inline-preview-body">
                            <div class="homepage-preview-hero">
                                <p>{{ $theme['name'] }}</p>
                                <h3>{{ $theme['description'] }}</h3>
                                <span>An editable starter homepage for your ministry.</span>
                            </div>
                            <div class="homepage-preview-sections">
                                @foreach($theme['sections'] as $section)
                                    <article>
                                        <small>{{ str($section)->replace('_', ' ')->upper() }}</small>
                                        <strong>{{ str($section)->headline() }}</strong>
                                    </article>
                                @endforeach
                            </div>
                            <form method="post" action="{{ route('staff.homepage.themes.apply', $theme['key']) }}" onsubmit="return confirm('Apply the {{ $theme['name'] }} theme? This replaces the current homepage slides and sections with editable starter content.')">
                                @csrf
                                <button class="button" type="submit">Use {{ $theme['name'] }} theme</button>
                            </form>
                        </div>
                    </details>
                </div>
            </article>
        @endforeach</div>
    </details>

    <section class="homepage-editor-grid">
        <details class="homepage-advanced">
            <summary class="homepage-advanced-trigger">
                <span class="homepage-trigger-copy">
                    <small>FINE TUNING</small>
                    <strong>Advanced appearance</strong>
                    <em>Adjust the theme family and the lead section composition.</em>
                </span>
                <span class="homepage-trigger-action"><span>Customize</span><x-ui.icon name="chevron-down" :size="16" /></span>
            </summary>
            <form class="homepage-form" method="post" action="{{ route('staff.homepage.appearance') }}">
                @csrf @method('PUT')
                <label>Theme family<select name="theme_family">@foreach($themes as $theme)<option value="{{ $theme }}" @selected($homepage->theme_family === $theme)>{{ str($theme)->headline() }}</option>@endforeach</select></label>
                <label>Lead layout<select name="hero_layout">@foreach($layouts as $layout)<option value="{{ $layout }}" @selected($homepage->hero_layout === $layout)>{{ str($layout)->headline() }}</option>@endforeach</select></label>
                <button class="button" type="submit">Save appearance</button>
            </form>
        </details>
        <aside class="homepage-help"><strong>Customize your current homepage</strong><ol><li>Edit the sample copy and media below.</li><li>Drag items to set their order.</li><li>Use Change homepage theme any time to begin again.</li></ol></aside>
    </section>

    <section class="homepage-editor-section"><div><p>LEAD CAROUSEL</p><h2>Slides</h2><span>Use an image, hosted video, or an HTTPS YouTube/Vimeo video link. Public motion remains user controlled.</span></div>
        @if($slides->isNotEmpty())<form id="slide-order" class="homepage-order-form" method="post" action="{{ route('staff.homepage.slides.order') }}" data-homepage-order>@csrf @method('PUT')<div data-order-inputs></div><button class="button button-secondary" type="submit">Save slide order</button></form><div class="homepage-records homepage-editable-records" data-order-list>@foreach($slides as $slide)<article draggable="true" data-order-item data-order-id="{{ $slide->id }}"><div class="homepage-record-heading"><strong>{{ $slide->title }}</strong><span>{{ str($slide->media_type)->headline() }} · {{ $slide->is_published ? 'Published' : 'Draft' }}</span></div><span class="homepage-drag-handle" aria-hidden="true">Drag to reorder</span><details><summary>Edit slide</summary><x-homepage.slide-form :slide="$slide" /></details><form class="homepage-delete-form" method="post" action="{{ route('staff.homepage.slides.destroy', $slide) }}" onsubmit="return confirm('Remove this carousel slide?')">@csrf @method('DELETE')<button type="submit">Remove slide</button></form></article>@endforeach</div>@else<p class="member-empty">No slides yet. Add the first one below.</p>@endif
        @if($slides->count() < 4)<details class="homepage-create"><summary>Add carousel slide</summary><x-homepage.slide-form /></details>@endif
    </section>
    <section class="homepage-editor-section"><div><p>BODY COMPOSITION</p><h2>Homepage sections</h2><span>Connected content is loaded from its module and remains server-hidden when that module is disabled.</span></div>
        @if($sections->isNotEmpty())<form id="section-order" class="homepage-order-form" method="post" action="{{ route('staff.homepage.sections.order') }}" data-homepage-order>@csrf @method('PUT')<div data-order-inputs></div><button class="button button-secondary" type="submit">Save section order</button></form><div class="homepage-records homepage-editable-records" data-order-list>@foreach($sections as $section)<article draggable="true" data-order-item data-order-id="{{ $section->id }}"><div class="homepage-record-heading"><strong>{{ str($section->section_type)->headline() }}</strong><span>{{ $section->is_enabled ? 'Visible' : 'Hidden' }}</span></div><span class="homepage-drag-handle" aria-hidden="true">Drag to reorder</span><details><summary>Edit section</summary><x-homepage.section-form :section="$section" :section-types="$sectionTypes" /></details><form class="homepage-delete-form" method="post" action="{{ route('staff.homepage.sections.destroy', $section) }}" onsubmit="return confirm('Remove this homepage section?')">@csrf @method('DELETE')<button type="submit">Remove section</button></form></article>@endforeach</div>@else<p class="member-empty">No sections selected yet.</p>@endif
        <details class="homepage-create"><summary>Add homepage section</summary><x-homepage.section-form :section-types="$sectionTypes" /></details>
    </section>
</x-layouts.workspace>
