<x-layouts.workspace :title="$page ? 'Edit page' : 'New page'" :ministry-name="$settings->name" :user-name="$user->name" user-role="Staff workspace">
<x-slot:navigation></x-slot:navigation>
<header class="workspace-page-heading staff-page-command page-management-heading"><div><p>PUBLIC SITE · {{ $page ? str($page->status)->upper() : 'DRAFT' }}</p><h1>{{ $page ? $page->title : ($starter['label'] ?? 'New page') }}</h1><span>Build a responsive public page with visual sections, then save it as a private draft.</span></div><a class="button button-secondary" href="{{ route('staff.pages') }}" aria-label="Back to all pages" title="All pages"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14 6-6 6 6 6M8 12h10"/></svg><span>All pages</span></a></header>
@if(session('status'))<div class="workspace-notice workspace-notice-success">{{ session('status') }}</div>@endif @if($errors->any())<div class="workspace-notice workspace-notice-error">{{ $errors->first() }}</div>@endif
<form class="page-block-editor-form" method="POST" action="{{ $page ? route('staff.pages.update',$page->id) : route('staff.pages.store') }}" data-page-block-editor data-element-catalog='@json($elementCatalog ?? [])' data-async-form data-async-pending-label="Saving draft…" data-preview-endpoint="{{ route('staff.pages.preview') }}" data-object-ai-endpoint="{{ route('staff.pages.ai-object-revision') }}" data-ai-available="{{ in_array($aiDraftRoute->status ?? null, ['verified', 'degraded'], true) ? 'true' : 'false' }}" data-blocks='@json($blocks)'>@csrf @if($page)@method('PUT')@endif<input type="hidden" name="blocks" data-page-block-input>@if($page)<input type="hidden" name="document_version" value="{{ $page->updated_at }}">@endif
<section class="page-editor-meta"><label>Page title<input name="title" required value="{{ old('title',$page->title ?? $starter['label'] ?? '') }}"></label><label>Page address<input name="slug" required value="{{ old('slug',$page->slug ?? $starter['slug'] ?? '') }}"></label><span><strong>Draft workspace</strong><small>Publish only after review.</small></span></section>
@if(!$page && ($canPublish ?? false))<button class="button button-secondary" type="submit" name="publish" value="1">Publish page</button>@endif
@if($aiDraftAvailable ?? false)
<section class="ai-page-draft-panel" data-ai-page-draft data-endpoint="{{ route('staff.pages.ai-draft') }}" data-existing="{{ $page ? 'true' : 'false' }}">
    <div><p>AI PAGE DESIGNER</p><h2>What page would you like to create?</h2><span>Describe the result in your own words. AI will design responsive HTML and CSS sections, then apply the completed design directly to the editable canvas.</span></div>
    @if(in_array($aiDraftRoute->status ?? null, ['verified', 'degraded'], true))
        <p class="ai-page-route-state">Ready: {{ $aiDraftRoute->primary_provider }} · {{ $aiDraftRoute->primary_model }}</p>
    @else
        <p class="ai-page-route-state is-unavailable">AI drafting is unavailable until an administrator verifies a model route in AI model settings.</p>
    @endif
    <label class="ai-page-primary-brief">Describe your page<textarea data-ai-brief minlength="20" maxlength="12000" rows="7" placeholder="Describe any public page, its purpose, audience, content, visual direction, and desired experience."></textarea><small>Include any facts that must be accurate. Do not include private member information.</small></label>
    <details class="ai-page-advanced-direction">
        <summary><span>Advanced direction</span><small>Optional — use only when you want more control</small></summary>
        <div class="ai-page-design-brief-grid">
            <label>Page goal<input data-ai-page-goal maxlength="3000" placeholder="The outcome or experience this page should support"></label>
            <label>Primary audience<input data-ai-audience maxlength="1500" placeholder="Describe the intended visitors"></label>
            <label>Tone<input data-ai-tone maxlength="1000" placeholder="Warm, confident, technical, playful, editorial, restrained"></label>
            <label>Content depth<select data-ai-content-density><option value="balanced">Balanced complete page</option><option value="concise">Concise page</option><option value="detailed">Detailed immersive page</option></select></label>
            <label>Visual direction<textarea data-ai-visual-direction maxlength="4000" rows="3" placeholder="Editorial, cinematic, technical, minimal, expressive, bold photography, unusual composition"></textarea></label>
            <label>Primary action<input data-ai-primary-action maxlength="1500" placeholder="The main action visitors should take"></label>
            <label>Required sections or content<textarea data-ai-required-sections maxlength="6000" rows="3" placeholder="Content, capabilities, or sections that must appear"></textarea></label>
            <label>Patterns to avoid<textarea data-ai-forbidden-patterns maxlength="4000" rows="3" placeholder="Patterns, layouts, language, or visual treatments to avoid"></textarea></label>
            <label>Brand constraints<textarea data-ai-brand-constraints maxlength="4000" rows="3" placeholder="Approved visual, brand, or language constraints"></textarea></label>
            <label>Additional confirmed public facts<textarea data-ai-public-details maxlength="15000" rows="5" placeholder="Any accurate public facts the design may use"></textarea></label>
        </div>
    </details>
    <div class="ai-page-draft-actions"><button class="button" type="button" data-ai-generate @disabled(! in_array($aiDraftRoute->status ?? null, ['verified', 'degraded'], true))>Plan and design this page</button><span data-ai-status role="status" aria-live="polite"></span></div>
</section>
@endif
<button class="page-block-library-trigger" type="button" data-block-library-open aria-label="Open elements and sections" title="Add elements and sections"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg></button><div class="page-block-library-backdrop" data-block-library-close></div>
<section class="page-block-editor-shell" data-page-editor-panel="manual">
<aside class="page-block-library">
<header><p>MANUAL EDITOR</p><h2>Build your page</h2><span>Choose a structure or registered element, then edit it on the canvas.</span></header>
<div class="page-block-library-group"><small>STRUCTURE</small><button type="button" data-add="section">Single-column section</button><button type="button" data-add="grid" data-grid-count="2">Two-column grid</button><button type="button" data-add="grid" data-grid-count="3">Three-column grid</button><button type="button" data-add="grid" data-grid-count="4">Four-column grid</button></div>
@foreach(collect($elementCategories ?? [])->sortBy('order') as $categoryKey => $category)
    @php $categoryElements = collect($elementCatalog ?? [])->filter(fn($definition) => ($definition['category'] ?? 'content') === $categoryKey); @endphp
    @if($categoryElements->isNotEmpty())
        <div class="page-block-library-group" data-element-category="{{ $categoryKey }}"><small>{{ strtoupper($category['label']) }}</small>
            @foreach($categoryElements as $elementType => $definition)<button type="button" data-add="{{ $elementType }}">{{ $definition['label'] }}</button>@endforeach
        </div>
    @endif
@endforeach
</aside>
<button class="page-editor-floating-navigator-trigger" type="button" data-floating-navigator-open aria-controls="page-editor-floating-navigator" aria-expanded="false" aria-label="Open page navigator" title="Open page navigator"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 6h14M5 12h14M5 18h14"/></svg></button><aside class="page-editor-floating-navigator" id="page-editor-floating-navigator" data-floating-navigator aria-hidden="true"><header><div><p>PAGE NAVIGATOR</p><h2>Page structure</h2><span>Select a section, column, or element.</span></div><button type="button" data-floating-navigator-close aria-label="Close page navigator" title="Close page navigator">×</button></header><div class="page-editor-floating-navigator-tree" data-floating-page-navigator></div></aside>
<main class="page-block-canvas-wrap">
    <header class="page-canvas-header">
        <div><p>PAGE CANVAS</p><strong>Edit the live page</strong></div>
        <div class="page-canvas-toolbar" role="group" aria-label="Canvas preview size">
            <button type="button" class="page-canvas-mode-button is-active" data-public-preview-size="desktop" aria-pressed="true" aria-label="Desktop canvas" title="Desktop canvas"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="13" rx="1.5"></rect><path d="M8 20h8M12 17v3"></path></svg></button>
            <button type="button" class="page-canvas-mode-button" data-public-preview-size="tablet" aria-pressed="false" aria-label="Tablet canvas" title="Tablet canvas"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="6" y="3" width="12" height="18" rx="2"></rect><path d="M10 18h4"></path></svg></button>
            <button type="button" class="page-canvas-mode-button" data-public-preview-size="mobile" aria-pressed="false" aria-label="Mobile canvas" title="Mobile canvas"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="8" y="3" width="8" height="18" rx="2"></rect><path d="M11 18h2"></path></svg></button>
        </div>
    </header>
    <p class="page-editor-preview-status" data-public-preview-status role="status">Loading the editable page…</p>
    <div class="page-editor-preview-viewport"><iframe class="page-editor-public-preview" data-public-preview data-preview="desktop" title="Editable page canvas" sandbox="allow-forms allow-scripts allow-popups allow-same-origin"></iframe></div>
    <div data-canvas hidden></div>
    <button class="page-block-add-canvas" type="button" data-block-library-open>+ Add a section or element</button>
</main>
<div class="page-block-inspector-backdrop" data-inspector-close aria-hidden="true"></div><aside class="page-block-inspector" aria-hidden="true"><header><p>BLOCK SETTINGS</p><h2>Choose a block</h2><span>Change content, section design, or ordering.</span><button class="page-block-inspector-close" type="button" data-inspector-close aria-label="Close block settings" title="Close block settings"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17"/></svg></button></header><div data-inspector></div></aside></section>
<details class="page-seo-panel"><summary><span><small>DISCOVERABILITY</small><strong>Search and sharing details</strong><em>Optional title and description for search results.</em></span><span>Customize</span></summary><div class="page-editor-fields page-editor-seo"><label>Search title<input name="seo_title" value="{{ old('seo_title',$page->seo_title ?? '') }}"></label><label>Search description<textarea name="seo_description">{{ old('seo_description',$page->seo_description ?? '') }}</textarea></label></div></details>
<footer class="page-editor-actions"><div><strong>{{ $page ? 'Save as draft' : 'Create a draft' }}</strong><span>Use undo if you remove or change a block by mistake.</span></div><div class="page-editor-action-buttons"><button class="button button-secondary page-editor-icon-button" type="button" data-undo disabled aria-label="Undo" title="Undo"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 7 4 12l5 5M4 12h10a6 6 0 0 1 6 6"/></svg></button><button class="button button-secondary page-editor-icon-button" type="button" data-redo disabled aria-label="Redo" title="Redo"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 7 5 5-5 5M20 12H10a6 6 0 0 0-6 6"/></svg></button>@if($page)<a class="button button-secondary page-editor-icon-button" href="{{ route('public.page', ['slug' => $page->slug]) }}" target="_blank" rel="noopener" aria-label="View live page" title="View live page"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 5h5v5M19 5l-8 8"/><path d="M19 13v5a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h5"/></svg></a>@endif<button class="button page-editor-icon-button" type="submit" aria-label="{{ $page ? 'Save draft changes' : 'Create draft' }}" title="{{ $page ? 'Save draft changes' : 'Create draft' }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h12l2 2v14H5zM8 4v6h8V4M8 20v-6h8v6"/></svg></button>@if($canPublish ?? false)<button class="button button-secondary page-editor-icon-button" type="submit" name="publish" value="1" aria-label="{{ $page ? 'Save and publish page' : 'Create and publish page' }}" title="{{ $page ? 'Save and publish page' : 'Create and publish page' }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v11M8 8l4-4 4 4M5 14v5h14v-5"/></svg></button>@endif</div></footer></form>
@include('staff.pages.partials.structured-editor-script')
</x-layouts.workspace>
