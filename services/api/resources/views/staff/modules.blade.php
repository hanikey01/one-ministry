<x-layouts.workspace title="Modules" :ministry-name="$settings->name" :user-name="$user->name" user-role="Administrator">
    <x-slot:navigation></x-slot:navigation>
    <header class="workspace-page-heading">
        <p>ADMINISTRATION</p>
        <h1>Modules</h1>
        <span>Choose which ministry tools are available. Changes take effect immediately.</span>
    </header>

    @if(session('status'))
        <div class="form-success" role="status">{{ session('status') }}</div>
    @endif

    <section class="data-panel module-panel" aria-label="Module settings" data-module-manager>
        <div class="module-panel-toolbar">
            <div>
                <p class="eyebrow">MODULE AVAILABILITY</p>
                <h2>Available tools</h2>
                <span>{{ $features->count() }} modules in this installation</span>
            </div>

            @if($canManageFeatures && $features->isNotEmpty())
                <form method="POST" action="{{ route('staff.modules.bulk-update') }}" class="module-bulk-actions" data-module-bulk-form>
                    @csrf
                    @method('PUT')
                    @foreach($features as $feature)
                        <input type="hidden" name="keys[]" value="{{ $feature->key }}">
                    @endforeach
                    <button class="button button-secondary" type="submit" name="state" value="disabled">Disable all</button>
                    <button class="button" type="submit" name="state" value="enabled">Enable all</button>
                </form>
            @endif
        </div>

        <div class="module-feedback" role="status" aria-live="polite" data-module-feedback></div>

        <table>
            <thead><tr><th>Module</th><th>Current state</th><th>Last updated</th>@if($canManageFeatures)<th>Control</th>@endif</tr></thead>
            <tbody>
                @forelse($features as $feature)
                    <tr data-module-row data-module-key="{{ $feature->key }}">
                        <td data-label="Module" class="module-name"><strong>{{ str($feature->key)->headline() }}</strong></td>
                        <td data-label="Current state"><span class="table-status" data-module-state>{{ str($feature->state)->headline() }}</span></td>
                        <td data-label="Last updated" data-module-updated>{{ \Illuminate\Support\Carbon::parse($feature->updated_at)->timezone($settings->timezone)->format('M j, Y') }}</td>
                        @if($canManageFeatures)
                            <td data-label="Change state" class="module-control">
                                <form class="inline-form" method="POST" action="{{ route('staff.modules.update', $feature->key) }}" data-module-form>
                                    @csrf
                                    @method('PUT')
                                    <select name="state" aria-label="State for {{ str($feature->key)->headline() }}">
                                        <option value="disabled" @selected($feature->state === 'disabled')>Disabled</option>
                                        <option value="staff_preview" @selected($feature->state === 'staff_preview')>Staff preview</option>
                                        <option value="enabled" @selected($feature->state === 'enabled')>Enabled</option>
                                    </select>
                                    <button class="button button-secondary" type="submit">Save</button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $canManageFeatures ? 4 : 3 }}" class="empty-cell">No feature settings available.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <script>
        (() => {
            const manager = document.querySelector('[data-module-manager]');
            if (!manager) return;

            const feedback = manager.querySelector('[data-module-feedback]');
            const label = (state) => state.replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase());
            const setFeedback = (message, failed = false) => {
                feedback.textContent = message;
                feedback.classList.toggle('is-error', failed);
            };
            const updateRow = (module) => {
                const row = manager.querySelector(`[data-module-key="${CSS.escape(module.key)}"]`);
                if (!row) return;
                row.querySelector('[data-module-state]').textContent = label(module.state);
                row.querySelector('[data-module-updated]').textContent = 'Just now';
                const select = row.querySelector('select[name="state"]');
                if (select) {
                    select.value = module.state;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                }
            };
            const submit = async (form, submitter) => {
                const controls = [...form.querySelectorAll('button, select')];
                const data = new FormData(form);
                if (submitter?.name) data.set(submitter.name, submitter.value);
                controls.forEach((control) => control.disabled = true);
                setFeedback('Saving module settings…');

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: data,
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const payload = await response.json().catch(() => ({}));
                    if (!response.ok) throw new Error(payload.message || 'Module settings could not be saved.');
                    const modules = Array.isArray(payload.data) ? payload.data : [payload.data];
                    modules.filter(Boolean).forEach(updateRow);
                    const changed = payload.meta?.changed;
                    setFeedback(changed === undefined ? 'Module setting saved.' : `${changed} module ${changed === 1 ? 'setting' : 'settings'} updated.`);
                    window.dispatchEvent(new CustomEvent('ministry:modules-changed', { detail: modules }));
                } catch (error) {
                    setFeedback(error.message, true);
                } finally {
                    controls.forEach((control) => control.disabled = false);
                }
            };

            manager.addEventListener('submit', (event) => {
                const form = event.target.closest('[data-module-form], [data-module-bulk-form]');
                if (!form) return;
                event.preventDefault();
                submit(form, event.submitter);
            });
        })();
    </script>
</x-layouts.workspace>
