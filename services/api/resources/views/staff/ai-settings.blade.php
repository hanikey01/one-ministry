<x-layouts.workspace title="AI model settings" :ministry-name="$settings->name" :primary-color="$settings->primary_color" :accent-color="$settings->accent_color" :user-name="$user->name" :user-initials="str($user->name)->explode(' ')->map(fn ($part) => str($part)->substr(0, 1))->take(2)->implode('')" user-role="Administrator">
    <header class="workspace-page-heading staff-page-command"><div><p>ADMINISTRATION</p><h1>AI model settings</h1><span>Connect models owned by your ministry and choose the tested route for page drafting.</span></div><a class="button button-secondary" href="{{ route('staff.pages') }}">Open Pages</a></header>
    @if(session('status'))<div class="form-success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="form-errors" role="alert">{{ $errors->first() }}</div>@endif
    <section class="appearance-panel ai-settings-intro">
        <div class="appearance-panel-heading"><span><x-ui.icon name="settings"/></span><div><h2>Installation-owned AI</h2><p>Connect Google Gemini directly, OpenRouter, or both. Credentials are encrypted and never shown again after saving. OpenRouter can provide access to eligible Google, Anthropic, and OpenAI models through one connection.</p></div></div>
        <div class="ai-safety-note"><strong>Public page drafting only</strong><span>This model route is reserved for the page editor. It will receive only the prompt and public page context that staff choose to provide.</span></div>
    </section>

    <div class="ai-provider-grid">
        @foreach($providerLabels as $providerKey => $providerLabel)
            @php
                $provider = $providers[$providerKey];
            @endphp
            <section class="appearance-panel ai-provider-card">
                <div class="appearance-panel-heading"><span><x-ui.icon name="settings"/></span><div><h2>{{ $providerLabel }}</h2><p>{{ $providerKey === 'gemini' ? 'Connect a ministry-owned Google Gemini API key.' : 'Connect a ministry-owned OpenRouter API key for its model catalog.' }}</p></div></div>
                <div class="ai-provider-state"><span class="status-badge" data-ai-provider-status="{{ $providerKey }}">{{ ! $provider['configured'] ? 'Not configured' : (! $provider['enabled'] ? 'Disabled' : ($provider['status'] === 'connected' ? 'Connected' : ucfirst($provider['status']))) }}</span><span><span data-ai-provider-count="{{ $providerKey }}">{{ $provider['model_count'] }}</span> available models</span>@if($provider['last_checked_at'])<span>Checked {{ \Illuminate\Support\Carbon::parse($provider['last_checked_at'])->diffForHumans() }}</span>@endif</div>
                <form method="POST" action="{{ route('staff.ai-settings.provider.update', $providerKey) }}" class="homepage-form" data-async-form data-pending-label="Saving provider…">@csrf @method('PUT')
                    <label class="field-wide">API key <input type="password" name="api_key" autocomplete="new-password" placeholder="{{ $provider['configured'] ? 'Saved securely · enter a new key to replace it' : 'Paste the provider API key' }}" spellcheck="false"></label>
                    @if($provider['configured'])<label class="ai-toggle"><input type="checkbox" name="clear_api_key" value="1"><span>Remove the saved key (disable this provider too)</span></label>@endif
                    <label class="ai-toggle"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $provider['enabled']))><span>Enable this provider for Ministry</span></label>
                    <button class="button" type="submit">Save provider</button>
                </form>
                <form method="POST" action="{{ route('staff.ai-settings.catalog.refresh', $providerKey) }}" data-async-form data-pending-label="Refreshing catalog…">@csrf
                    <button class="button button-secondary" type="submit" @disabled(! $provider['enabled'] || ! $provider['configured'])>Refresh model catalog</button>
                </form>
            </section>
        @endforeach
    </div>

    <section class="appearance-panel ai-route-panel">
        <div class="appearance-panel-heading"><span><x-ui.icon name="content"/></span><div><h2>Page editor route</h2><p>Select a primary model and one or two ordered fallbacks. Every option must support structured output so the editor can validate a safe page-block proposal.</p></div></div>
        @php
            $route = $route ?? null;
            $currentPrimary = $route && $route->primary_provider && $route->primary_model ? $route->primary_provider.'|'.$route->primary_model : '';
            $selectedFallbacks = old('fallbacks', $fallbackSelections);
            $hasEnabledProvider = collect($providers)->contains(fn ($provider): bool => (bool) ($provider['enabled'] ?? false));
            $modelOptions = $models->filter(function ($model) use ($providers): bool {
                $inputs = json_decode($model->input_modalities ?: '[]', true);
                $outputs = json_decode($model->output_modalities ?: '[]', true);
                return (bool) $model->supports_structured_output
                    && is_array($inputs) && in_array('text', $inputs, true)
                    && is_array($outputs) && in_array('text', $outputs, true)
                    && ($providers[$model->provider_key]['enabled'] ?? false);
            });
        @endphp
        <div class="ai-route-state"><span class="status-badge" data-ai-route-status>{{ ucfirst($route->status ?? 'unconfigured') }}</span>@if($route?->last_verified_at)<span>Verified {{ \Illuminate\Support\Carbon::parse($route->last_verified_at)->diffForHumans() }}</span>@elseif(($route->status ?? '') === 'unavailable')<span>A selected model has disappeared or no longer meets the route requirements. Choose a replacement.</span>@else<span data-ai-route-help>The route cannot be used until it passes the live check.</span>@endif</div>
        @if(! $hasEnabledProvider)<p class="ai-empty-state">Enable a provider and refresh its model catalog to choose models.</p>@else
            <form method="POST" action="{{ route('staff.ai-settings.route.save') }}" class="homepage-form ai-route-form" data-async-form data-pending-label="Saving route…">@csrf @method('PUT')
                <label class="field-wide">Primary model<select name="primary_model" data-searchable-select data-ai-model-select data-remote-options="{{ route('staff.ai-settings.models') }}" required><option value="">Choose a model</option>@foreach($modelOptions as $model)<option value="{{ $model->provider_key.'|'.$model->model_id }}" data-search-text="{{ strtolower($providerLabels[$model->provider_key].' '.$model->display_name.' '.$model->model_id) }}" @selected(old('primary_model',$currentPrimary) === $model->provider_key.'|'.$model->model_id)>{{ $providerLabels[$model->provider_key] }} · {{ $model->display_name }} ({{ $model->model_id }})</option>@endforeach</select></label>
                <label>Fallback model 1<select name="fallbacks[]" data-fallback-index="0" data-searchable-select data-ai-model-select data-remote-options="{{ route('staff.ai-settings.models') }}" required><option value="">Choose a fallback</option>@foreach($modelOptions as $model)<option value="{{ $model->provider_key.'|'.$model->model_id }}" data-search-text="{{ strtolower($providerLabels[$model->provider_key].' '.$model->display_name.' '.$model->model_id) }}" @selected(($selectedFallbacks[0] ?? '') === $model->provider_key.'|'.$model->model_id)>{{ $providerLabels[$model->provider_key] }} · {{ $model->display_name }}</option>@endforeach</select></label>
                <label>Fallback model 2 <small>Optional</small><select name="fallbacks[]" data-fallback-index="1" data-searchable-select data-ai-model-select data-remote-options="{{ route('staff.ai-settings.models') }}"><option value="">No second fallback</option>@foreach($modelOptions as $model)<option value="{{ $model->provider_key.'|'.$model->model_id }}" data-search-text="{{ strtolower($providerLabels[$model->provider_key].' '.$model->display_name.' '.$model->model_id) }}" @selected(($selectedFallbacks[1] ?? '') === $model->provider_key.'|'.$model->model_id)>{{ $providerLabels[$model->provider_key] }} · {{ $model->display_name }}</option>@endforeach</select></label>
                <button class="button" type="submit">Save route</button>
            </form>
        @endif
        <form method="POST" action="{{ route('staff.ai-settings.route.test') }}" data-async-form data-pending-label="Testing models…">@csrf
            <button class="button button-secondary" type="submit" @disabled(! $route || ! $route->primary_model)>Test and activate route</button>
        </form>
        <p class="ai-route-footnote">Changing a key or model invalidates the previous check. Catalog refreshes never switch the selected model automatically.</p>
    </section>
</x-layouts.workspace>
