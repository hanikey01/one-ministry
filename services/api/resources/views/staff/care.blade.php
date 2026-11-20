<x-layouts.workspace title="Care" :ministry-name="$settings->name" :user-name="$user->name" user-role="Restricted care workspace">
    <x-slot:navigation></x-slot:navigation>

    <div class="care-workspace">
        <header class="workspace-page-heading staff-page-command">
            <div>
                <p>RESTRICTED CARE</p>
                <h1>Prayer and care</h1>
                <span>Every active prayer request remains in this protected queue until it is assigned and handled.</span>
            </div>
            <span class="care-sensitivity-label"><x-ui.icon name="lock" :size="14"/> Protected information</span>
        </header>

        @if(session('status'))<div class="workspace-notice workspace-notice-success" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="workspace-notice workspace-notice-error" role="alert">{{ $errors->first() }}</div>@endif

    <section class="data-panel responsive-records care-queue-panel">
        <header class="follow-up-panel-heading">
            <div><p>ACTIVE PRAYER REQUESTS</p><h2>Prayer queue</h2><span>Visible to Care Staff and Super Administrators. Assignment is explicit and audited.</span></div>
            <span class="follow-up-count">{{ $prayerRequests->total() }} active</span>
        </header>
        <table>
            <thead><tr><th>Request</th><th>Prayer</th><th>Owner</th><th>Received</th><th>Action</th></tr></thead>
            <tbody>
                @forelse($prayerRequests as $prayer)
                    <tr>
                        <td data-label="Request"><strong>{{ trim($prayer->first_name.' '.$prayer->last_name) ?: 'Guest request' }}</strong><br><small>{{ $prayer->email ?: $prayer->phone ?: 'No contact details shared' }}</small></td>
                        <td data-label="Prayer" class="care-prayer-copy">@if($prayer->unreadable ?? false)<em>This request was encrypted with an unavailable key and cannot be recovered from this installation.</em>@else{{ $prayer->text }}@endif</td>
                        <td data-label="Owner"><span class="table-status care-owner-status {{ $prayer->assigned_name ? 'is-assigned' : 'is-unassigned' }}">{{ $prayer->assigned_name ?: 'Unassigned' }}</span></td>
                        <td data-label="Received">{{ \Illuminate\Support\Carbon::parse($prayer->created_at)->timezone($settings->timezone)->format('M j, Y · g:i A') }}</td>
                        <td data-label="Action">
                            @if($canSeeAllCare)
                                <form class="care-assignment-form" method="POST" action="{{ route('staff.care.prayer-requests.claim', $prayer->id) }}">@csrf
                                    <label class="sr-only" for="prayer-owner-{{ $prayer->id }}">Assign care owner</label>
                                    <select id="prayer-owner-{{ $prayer->id }}" name="assigned_to">@foreach($careWorkers as $careWorker)<option value="{{ $careWorker->id }}" @selected($prayer->assigned_to === $careWorker->id)>{{ $careWorker->name }}</option>@endforeach</select>
                                    <button class="button button-quiet" type="submit">Assign</button>
                                </form>
                            @elseif($prayer->assigned_to === null || $prayer->assigned_to === $user->id)
                                <form class="care-assignment-form" method="POST" action="{{ route('staff.care.prayer-requests.claim', $prayer->id) }}">@csrf<button class="button button-quiet" type="submit">{{ $prayer->assigned_to ? 'Assigned to you' : 'Claim request' }}</button></form>
                            @else
                                <small>Assigned to {{ $prayer->assigned_name }}</small>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty-cell">No active prayer requests. New requests will stay here until care staff handle them.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="pagination-wrap">{{ $prayerRequests->links() }}</div>
    </section>

    <section class="data-panel responsive-records care-cases-panel">
        <header class="follow-up-panel-heading"><div><p>CARE CASES</p><h2>{{ $canSeeAllCare ? 'Open care cases' : 'My open care cases' }}</h2><span>Private notes remain inside the protected case workflow.</span></div></header>
        <table><thead><tr><th>Case</th><th>Assigned to</th><th>Status</th><th>Last updated</th></tr></thead><tbody>@forelse($cases as $case)<tr><td data-label="Case"><strong>Care case {{ str($case->id)->substr(0, 8) }}</strong></td><td data-label="Assigned to">{{ $case->assigned_name ?: 'Unassigned' }}</td><td data-label="Status"><span class="table-status">{{ str($case->status)->headline() }}</span></td><td data-label="Last updated">{{ \Illuminate\Support\Carbon::parse($case->updated_at)->timezone($settings->timezone)->format('M j, Y') }}</td></tr>@empty<tr><td colspan="4" class="empty-cell">No open care cases.</td></tr>@endforelse</tbody></table>
        <div class="pagination-wrap">{{ $cases->links() }}</div>
    </section>
    </div>
</x-layouts.workspace>
