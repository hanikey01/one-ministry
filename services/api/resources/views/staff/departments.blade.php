<x-layouts.workspace :title="app(\App\Support\MinistryTerminology::class)->label('department', 'plural')" :ministry-name="$settings->name" :user-name="$user->name" user-role="Staff workspace">
    <x-slot:navigation></x-slot:navigation>
    <header class="workspace-page-heading staff-page-command">
        <div><p>MINISTRY TEAMS</p><h1>{{ app(\App\Support\MinistryTerminology::class)->label('department', 'plural') }}</h1><span>Organize people into teams. A person may serve in more than one department.</span></div>
        @if($canManageDepartments)<details data-dismiss-on-outside class="workspace-create"><summary class="button"><x-ui.icon name="groups" :size="16"/> New department</summary><form method="POST" action="{{ route('staff.departments.store') }}" class="workspace-create-form">@csrf<label>Department name<input name="name" required maxlength="160" value="{{ old('name') }}"></label><label class="field-wide">Purpose<textarea name="description" rows="3" maxlength="2000">{{ old('description') }}</textarea></label><button class="button" type="submit">Create department</button></form></details>@endif
    </header>
    @if(session('status'))<div class="workspace-notice workspace-notice-success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="workspace-notice workspace-notice-error" role="alert">{{ $errors->first() }}</div>@endif
    <section class="department-grid">
        @forelse($departments as $department)
            <a class="department-card" href="{{ route('staff.departments.show', $department->id) }}"><span class="department-card-icon"><x-ui.icon name="groups" :size="19"/></span><div><p>ACTIVE TEAM</p><h2>{{ $department->name }}</h2><span>{{ $department->description ?: 'No team description yet.' }}</span></div><strong>{{ number_format($department->member_count) }} {{ str('member')->plural($department->member_count) }}</strong><x-ui.icon name="external" :size="16"/></a>
        @empty
            <div class="empty-state"><h2>No departments available</h2><p>Create the first department to begin organizing teams.</p></div>
        @endforelse
    </section>
</x-layouts.workspace>

