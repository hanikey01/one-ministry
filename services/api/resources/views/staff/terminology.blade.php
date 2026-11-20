<x-layouts.workspace title="Terminology" :ministry-name="$settings->name" :user-name="$user->name" user-role="Administrator">
    <header class="workspace-page-heading staff-page-command">
        <div><p>PLATFORM LANGUAGE</p><h1>Terminology</h1><span>Use familiar words across the public, member, and staff workspaces.</span></div>
    </header>
    @if(session('status'))<div class="form-success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="form-errors" role="alert">{{ $errors->first() }}</div>@endif
    <form class="appearance-stack" method="POST" action="{{ route('staff.terminology.update') }}" data-async-form data-async-pending-label="Saving terminology…" x-data="{ preview: @js(old('terminology', $values)) }">
        @csrf @method('PUT')
        <section class="appearance-panel">
            <div class="appearance-panel-heading"><span><x-ui.icon name="message"/></span><div><h2>Platform terms</h2><p>Customize the main gathering, people, community, follow-up, and publishing labels. Internal URLs, permissions, feature keys, and stored values stay unchanged.</p></div></div>
            <div class="terminology-grid">
                @foreach($definitions as $term => $definition)
                    <fieldset class="terminology-item">
                        <legend>{{ $definition['singular'] }} / {{ $definition['plural'] }}</legend>
                        <p>{{ $definition['description'] }}</p>
                        <div class="appearance-fields">
                            <label>Singular
                                <input name="terminology[{{ $term }}][singular]" maxlength="48" value="{{ old("terminology.{$term}.singular", $values[$term]['singular']) }}" x-model="preview['{{ $term }}'].singular" required>
                            </label>
                            <label>Plural
                                <input name="terminology[{{ $term }}][plural]" maxlength="48" value="{{ old("terminology.{$term}.plural", $values[$term]['plural']) }}" x-model="preview['{{ $term }}'].plural" required>
                            </label>
                        </div>
                        <small>Defaults: {{ $definition['singular'] }} / {{ $definition['plural'] }}. Enter these defaults again to reset.</small>
                    </fieldset>
                @endforeach
            </div>
            <div class="terminology-preview" aria-live="polite"><strong>Workspace label preview</strong><span x-text="Object.values(preview).map(term => term.plural).join(' · ')">{{ collect($values)->pluck('plural')->implode(' · ') }}</span></div>
            <div class="appearance-actions"><button class="button" type="submit">Save terminology</button></div>
        </section>
    </form>
</x-layouts.workspace>
