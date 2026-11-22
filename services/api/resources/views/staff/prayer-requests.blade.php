<x-layouts.workspace title="Prayer requests" :ministry-name="$settings->name" :user-name="$user->name" user-role="Restricted prayer workspace">
    <header class="workspace-page-heading staff-page-command">
        <div><p>PRAYER TEAM</p><h1>Prayer requests</h1><span>Visible only to authorized administrators and prayer-team staff. Every access is audited.</span></div>
        <span class="care-sensitivity-label"><x-ui.icon name="lock" :size="14"/> Protected information</span>
    </header>
    <section class="data-panel responsive-records care-queue-panel">
        <header class="follow-up-panel-heading"><div><p>ACTIVE REQUESTS</p><h2>Prayer queue</h2><span>Requests remain private and are not assigned to individual care staff.</span></div><span class="follow-up-count">{{ $prayerRequests->total() }} active</span></header>
        <table><thead><tr><th>Request</th><th>Prayer</th><th>Received</th><th>Status</th></tr></thead><tbody>
        @forelse($prayerRequests as $prayer)
            <tr><td data-label="Request"><strong>{{ trim($prayer->first_name.' '.$prayer->last_name) ?: 'Guest request' }}</strong><br><small>{{ $prayer->email ?: $prayer->phone ?: 'No contact details shared' }}</small></td><td data-label="Prayer" class="care-prayer-copy">@if($prayer->unreadable)<em>This request cannot be decrypted with the current installation key.</em>@else{{ $prayer->text }}@endif</td><td data-label="Received">{{ \Illuminate\Support\Carbon::parse($prayer->created_at)->timezone($settings->timezone)->format('M j, Y · g:i A') }}</td><td data-label="Status"><span class="table-status">{{ str($prayer->status)->headline() }}</span></td></tr>
        @empty
            <tr><td colspan="4" class="empty-cell">No active prayer requests.</td></tr>
        @endforelse
        </tbody></table><div class="pagination-wrap">{{ $prayerRequests->links() }}</div>
    </section>
</x-layouts.workspace>
