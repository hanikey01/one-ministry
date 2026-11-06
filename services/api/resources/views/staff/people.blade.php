<x-layouts.workspace title="People" :ministry-name="$settings->name" :user-name="$user->name" :user-initials="str($user->name)->explode(' ')->map(fn ($part) => str($part)->substr(0, 1))->take(2)->implode('')" user-role="Staff workspace">
    <x-slot:navigation></x-slot:navigation>
    <header class="workspace-page-heading staff-page-command people-command-header">
        <div><p>PEOPLE</p><h1>People and connections</h1><span>Keep contact details, relationships, and follow-up work connected to the right person.</span></div>
        <div class="people-command-actions"><span class="people-command-count"><strong>{{ number_format($people->total()) }}</strong> {{ str('person')->plural($people->total()) }}</span><button class="button people-add-button" type="button" data-workspace-dialog-open="person-create"><x-ui.icon name="people" :size="18"/><span>Add person</span></button></div>
    </header>
    @if(session('status'))<div class="workspace-notice workspace-notice-success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="workspace-notice workspace-notice-error" role="alert">{{ $errors->first() }}</div>@endif
    <dialog class="workspace-dialog" id="person-create" data-workspace-dialog @if($errors->any()) data-workspace-dialog-auto-open @endif aria-labelledby="person-create-title">
        <form method="POST" action="{{ route('staff.people.store') }}" class="workspace-dialog-card people-create-dialog-card">
            @csrf
            <header class="people-dialog-header"><div class="people-dialog-heading"><span class="people-dialog-mark" aria-hidden="true"><x-ui.icon name="people" :size="20"/></span><div><p>PEOPLE DIRECTORY</p><h2 id="person-create-title">Add a person</h2><span>Create a clear contact record for care, communication, and follow-up.</span></div></div><button type="button" class="workspace-dialog-close people-dialog-close" data-workspace-dialog-close aria-label="Close add person form"><x-ui.icon name="close" :size="18"/></button></header>
            <div class="content-edit-fields people-form-fields">
                <div class="people-form-section-heading"><strong>Personal details</strong><span>Only a first name is required. Add contact information when it is available.</span></div>
                <label>First name <em>Required</em><input name="first_name" value="{{ old('first_name') }}" required maxlength="100" autocomplete="given-name" placeholder="First name"></label>
                <label>Last name <em>Optional</em><input name="last_name" value="{{ old('last_name') }}" maxlength="100" autocomplete="family-name" placeholder="Last name"></label>
                <label>Email <em>Optional</em><input type="email" name="email" value="{{ old('email') }}" maxlength="254" autocomplete="email" placeholder="name@example.com"></label>
                <label>Phone <em>Optional</em><input type="tel" name="phone" value="{{ old('phone') }}" maxlength="40" autocomplete="tel" placeholder="Phone number"></label>
                <label class="check-field people-consent-field"><input type="checkbox" name="consent_to_contact" value="1" @checked(old('consent_to_contact'))><span><strong>Contact consent confirmed</strong><small>Use this only when the person has agreed that the ministry may contact them.</small></span></label>
            </div>
            <footer class="people-dialog-actions"><button class="button button-secondary people-dialog-cancel" type="button" data-workspace-dialog-close>Cancel</button><button class="button people-dialog-submit" type="submit"><x-ui.icon name="add" :size="16"/><span>Add person</span></button></footer>
        </form>
    </dialog>
    <section class="people-directory people-directory-panel" aria-labelledby="people-directory-heading">
        <div class="people-directory-heading"><div><p>DIRECTORY</p><h2 id="people-directory-heading">People records</h2><span>Find a person by name, email address, or phone number.</span></div></div>
        <form class="table-toolbar people-search" method="GET" role="search"><label class="people-search-field"><x-ui.icon name="search" :size="17"/><input type="search" name="q" value="{{ $search }}" placeholder="Search people, email, or phone" aria-label="Search people, email, or phone" autocomplete="off"></label><button class="button people-search-submit" type="submit"><x-ui.icon name="search" :size="16"/><span>Search</span></button>@if($search !== '')<a class="people-search-clear" href="{{ route('staff.people') }}">Clear search</a>@endif</form>
        <section class="data-panel people-results-panel"><table><thead><tr><th>Person</th><th>Contact</th><th>Added</th></tr></thead><tbody>
            @forelse ($people as $person)<tr><td data-label="Person"><a class="people-person-link" href="{{ route('staff.people.person', $person->id) }}"><strong>{{ $person->first_name }} {{ $person->last_name }}</strong><x-ui.icon name="arrow-right" :size="14"/></a></td><td data-label="Contact">{{ $person->email ?: $person->phone ?: 'No contact detail' }}</td><td data-label="Added">{{ \Illuminate\Support\Carbon::parse($person->created_at)->format('M j, Y') }}</td></tr>@empty<tr><td colspan="3" class="empty-cell">No people match this search. Use <strong>Add person</strong> to create a record.</td></tr>@endforelse
        </tbody></table></section>
        <div class="pagination-wrap">{{ $people->links() }}</div>
    </section>
</x-layouts.workspace>
