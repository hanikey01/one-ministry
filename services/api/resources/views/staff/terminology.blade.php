<x-layouts.workspace title="Ministry terminology" :ministry-name="$settings->name" :primary-color="$settings->primary_color" :accent-color="$settings->accent_color" :user-name="$user->name" user-role="Administrator">
    <header class="workspace-page-heading staff-page-command">
        <div><p>ADMINISTRATION</p><h1>Ministry terminology</h1><span>Use the words your community recognizes across the platform.</span></div>
    </header>
    @if(session('status'))<div class="form-success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="form-errors" role="alert">{{ $errors->first() }}</div>@endif
    <form class="appearance-stack" method="POST" action="{{ route('staff.terminology.update') }}" data-async-form data-async-pending-label="Saving terminology…" x-data="{ preview: @js(old('terminology', $values)) }">
        @csrf @method('PUT')
        <section class="appearance-panel">
            <div class="appearance-panel-heading"><span><x-ui.icon name="message"/></span><div><h2>Core terms</h2><p>These overrides change interface labels. They do not change URLs, permissions, stored records, or ministry data.</p></div></div>
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
            <div class="terminology-preview" aria-live="polite"><strong>Preview</strong><span x-text="`${preview.service.plural} · ${preview.meeting.plural} · ${preview.group.plural} · ${preview.location.plural}`">{{ $values['service']['plural'] }} · {{ $values['meeting']['plural'] }} · {{ $values['group']['plural'] }} · {{ $values['location']['plural'] }}</span></div>
            <div class="appearance-actions"><button class="button" type="submit">Save terminology</button></div>
        </section>
    </form>
</x-layouts.workspace>
