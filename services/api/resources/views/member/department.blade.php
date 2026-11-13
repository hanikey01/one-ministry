<x-layouts.member :title="$department->name" :ministry-name="$settings->name" :features="$features">
    <header class="department-chat-heading">
        <a href="{{ route('member.departments') }}">← My departments</a>
        <div><span class="member-tile-icon"><x-ui.icon name="departments"/></span><div><p>DEPARTMENT CHAT</p><h1>{{ $department->name }}</h1><small>{{ str($department->role)->headline() }} · Private to active members</small></div></div>
    </header>
    @if(session('status'))<div class="member-notice member-notice-success">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="member-notice member-notice-error">{{ $errors->first() }}</div>@endif

    <section class="department-chat-shell" data-department-chat data-messages-url="{{ route('member.departments.messages.index', $department->id) }}">
        <header><div><strong>Team conversation</strong><span>{{ $department->description ?: 'A private space for this department.' }}</span></div><div class="department-chat-presence"><span aria-hidden="true"></span><b>Live conversation</b><small>Members only</small></div></header>
        @if($department->discussion_enabled)
            <div class="department-chat-feed" data-chat-feed aria-live="polite">@include('member.partials.department-messages')</div>
            <div class="department-chat-feedback" data-chat-feedback role="status" aria-live="polite"></div>
            <form class="department-chat-composer" method="POST" action="{{ route('member.departments.messages.store', $department->id) }}" data-chat-composer>
                @csrf
                <div class="department-chat-avatar">{{ mb_strtoupper(mb_substr((string) $person->first_name, 0, 1).mb_substr((string) ($person->last_name ?? ''), 0, 1)) }}</div>
                <label><span class="sr-only">Message the department</span><textarea name="content" rows="1" maxlength="2000" required placeholder="Write a message…" data-chat-composer-input></textarea><small>Enter to send · Shift + Enter for a new line</small></label>
                <button class="button" type="submit" aria-label="Send message"><span>Send</span><x-ui.icon name="arrow-right" :size="16"/></button>
            </form>
        @else
            <div class="member-empty-state"><strong>Chat is unavailable</strong><p>Department leaders have turned off discussion for this space.</p></div>
        @endif
    </section>

    @if($department->role === 'member')
        <form class="department-leave-form" method="POST" action="{{ route('member.departments.leave', $department->id) }}">@csrf @method('DELETE')<button type="submit">Leave department</button></form>
    @endif
</x-layouts.member>
