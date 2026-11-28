@php($terminology = app(\App\Support\MinistryTerminology::class))
@php($departmentSingular = $terminology->label('department'))
@php($departmentPlural = $terminology->label('department', 'plural'))
<x-layouts.public :title="$department->name" :settings="$settings" :features="$features">
    <article class="department-public-detail">
        <a class="public-back-link" href="{{ route('public.departments') }}"><x-ui.icon name="arrow-left" :size="16"/> <span>All {{ $departmentPlural }}</span></a>
        <section class="department-detail-hero"><div class="department-detail-cover" @if($department->image_url) style="background-image:linear-gradient(180deg,transparent,rgb(7 20 41 / 70%)),url('{{ $department->image_url }}')" role="img" aria-label="{{ $department->image_alt }}" @endif></div><div><p>{{ $department->category ?: 'MINISTRY '.str($departmentSingular)->upper() }}</p><h1>{{ $department->name }}</h1><span>{{ $department->description ?: 'Connect with this ministry '.str($departmentSingular)->lower().'.' }}</span><small>{{ number_format($memberCount) }} {{ Str::plural('member',$memberCount) }}</small></div></section>
        <div class="department-public-action"><h2>Take your next step</h2>
            @auth
                @if($relationship === 'member')<p>You are already a member of this {{ str($departmentSingular)->lower() }}.</p><a class="button" href="{{ route('member.departments.show', $department->id) }}">Open member space</a>
                @elseif($department->accepting_requests && $relationship !== 'pending')
                    <form method="POST" action="{{ route('member.departments.request', $department->id) }}">@csrf<label>Why would you like to join?<textarea name="message" rows="4" maxlength="2000"></textarea></label><button class="button" type="submit">Request to join</button></form>
                @elseif($relationship === 'pending')<p>Your request is waiting for review.</p>
                @else<p>This {{ str($departmentSingular)->lower() }} is not accepting requests right now.</p>@endif
            @else
                <p>Sign in with your member account to request to join.</p><a class="button" href="{{ route('login') }}">Sign in</a>
            @endauth
        </div>
    </article>
</x-layouts.public>
