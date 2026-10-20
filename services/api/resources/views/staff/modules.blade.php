<x-layouts.workspace title="Modules" :ministry-name="$settings->name" :primary-color="$settings->primary_color" :accent-color="$settings->accent_color" :user-name="$user->name" user-role="Administrator">
    <x-slot:navigation></x-slot:navigation>
    <header class="workspace-page-heading"><p>ADMINISTRATION</p><h1>Modules</h1><span>Release modules in staff preview before enabling them publicly.</span></header>
    @if(session('status'))<div class="form-success" role="status">{{ session('status') }}</div>@endif
    <section class="data-panel module-panel" aria-label="Module settings">
        <table><thead><tr><th>Module</th><th>Current state</th><th>Last updated</th>@if($canManageFeatures)<th>Control</th>@endif</tr></thead><tbody>
            @forelse($features as $feature)<tr>
                <td data-label="Module" class="module-name"><strong>{{ str($feature->key)->headline() }}</strong></td>
                <td data-label="Current state"><span class="table-status">{{ str($feature->state)->headline() }}</span></td>
                <td data-label="Last updated">{{ \Illuminate\Support\Carbon::parse($feature->updated_at)->timezone($settings->timezone)->format('M j, Y') }}</td>
                @if($canManageFeatures)<td data-label="Change state" class="module-control"><form class="inline-form" method="POST" action="{{ route('staff.modules.update', $feature->key) }}">@csrf @method('PUT')<select name="state" aria-label="State for {{ str($feature->key)->headline() }}"><option value="disabled" @selected($feature->state === 'disabled')>Disabled</option><option value="staff_preview" @selected($feature->state === 'staff_preview')>Staff preview</option><option value="enabled" @selected($feature->state === 'enabled')>Enabled</option></select><button class="button button-secondary">Save</button></form></td>@endif
            </tr>@empty<tr><td colspan="{{ $canManageFeatures ? 4 : 3 }}" class="empty-cell">No feature settings available.</td></tr>@endforelse
        </tbody></table>
    </section>
</x-layouts.workspace>
