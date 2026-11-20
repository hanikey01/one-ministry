@php($terminology = app(\App\Support\MinistryTerminology::class))
@php($departmentSingular = $terminology->label('department'))
@php($departmentPlural = $terminology->label('department', 'plural'))
<x-layouts.member :title="$department->name" :ministry-name="$settings->name" :features="$features">
    <header class="member-page-heading">
        <a href="{{ route('member.departments') }}">← My {{ $departmentPlural }}</a>
        <div><span class="member-tile-icon"><x-ui.icon name="departments"/></span><div><p>MY {{ str($departmentSingular)->upper() }}</p><h1>{{ $department->name }}</h1><small>{{ str($department->role)->headline() }} · Active membership</small></div></div>
    </header>
    @if(session('status'))<div class="member-notice member-notice-success">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="member-notice member-notice-error">{{ $errors->first() }}</div>@endif

    <section class="member-panel">
        <header><div><strong>{{ $departmentSingular }} membership</strong><span>{{ $department->description ?: 'You are an active member of this '.str($departmentSingular)->lower().'.' }}</span></div></header>
        <div class="member-empty-state"><strong>Your membership is active</strong><p>{{ $departmentSingular }} leaders will contact you through the ministry’s configured communication channels when there is an update.</p>@if($department->contact_email)<a href="mailto:{{ $department->contact_email }}">Contact {{ str($departmentSingular)->lower() }} leadership</a>@endif</div>
    </section>

    @if($department->role === 'member')
        <form class="department-leave-form" method="POST" action="{{ route('member.departments.leave', $department->id) }}">@csrf @method('DELETE')<button type="submit">Leave {{ str($departmentSingular)->lower() }}</button></form>
    @endif
</x-layouts.member>
