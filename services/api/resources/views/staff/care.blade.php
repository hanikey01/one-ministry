<x-layouts.workspace title="Care" :ministry-name="$settings->name" :user-name="$user->name" user-role="Restricted care workspace">
    <x-slot:navigation></x-slot:navigation>

    <div class="care-workspace">
        <header class="workspace-page-heading staff-page-command">
            <div>
                <p>RESTRICTED CARE</p>
                <h1>Pastoral care</h1>
                <span>Restricted care cases and private notes remain separated from the prayer-team queue.</span>
            </div>
            <span class="care-sensitivity-label"><x-ui.icon name="lock" :size="14"/> Protected information</span>
        </header>

        @if(session('status'))<div class="workspace-notice workspace-notice-success" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="workspace-notice workspace-notice-error" role="alert">{{ $errors->first() }}</div>@endif

    <section class="data-panel responsive-records care-cases-panel">
        <header class="follow-up-panel-heading"><div><p>CARE CASES</p><h2>{{ $canSeeAllCare ? 'Open care cases' : 'My open care cases' }}</h2><span>Private notes remain inside the protected case workflow.</span></div></header>
        <table><thead><tr><th>Case</th><th>Assigned to</th><th>Status</th><th>Last updated</th></tr></thead><tbody>@forelse($cases as $case)<tr><td data-label="Case"><strong>Care case {{ str($case->id)->substr(0, 8) }}</strong></td><td data-label="Assigned to">{{ $case->assigned_name ?: 'Unassigned' }}</td><td data-label="Status"><span class="table-status">{{ str($case->status)->headline() }}</span></td><td data-label="Last updated">{{ \Illuminate\Support\Carbon::parse($case->updated_at)->timezone($settings->timezone)->format('M j, Y') }}</td></tr>@empty<tr><td colspan="4" class="empty-cell">No open care cases.</td></tr>@endforelse</tbody></table>
        <div class="pagination-wrap">{{ $cases->links() }}</div>
    </section>
    </div>
</x-layouts.workspace>
