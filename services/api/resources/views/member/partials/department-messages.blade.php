@php($departmentId = is_object($department) ? $department->id : $department)
@forelse($messages as $message)
    @php($isOwn = $message->person_id === $person->id)
    <article @class(['department-chat-message', 'is-own' => $isOwn]) id="message-{{ $message->id }}" data-chat-message="{{ $message->id }}">
        <div class="department-chat-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr((string) $message->first_name, 0, 1).mb_substr((string) ($message->last_name ?? ''), 0, 1)) }}</div>
        <div class="department-chat-bubble">
            @if($message->parent_content)<blockquote><span>Replying to</span>{{ str($message->parent_content)->limit(120) }}</blockquote>@endif
            <header><strong>{{ $isOwn ? 'You' : trim($message->first_name.' '.($message->last_name ?? '')) }}</strong><time datetime="{{ $message->created_at }}">{{ \Illuminate\Support\Carbon::parse($message->created_at)->diffForHumans() }}@if($message->edited_at) · edited @endif</time></header>
            <p>{{ $message->content }}</p>
            <footer>
                <button type="button" data-chat-reply aria-label="Reply to {{ $message->first_name }}"><x-ui.icon name="message" :size="13"/> Reply</button>
                @if($isOwn)
                    <button type="button" data-chat-edit aria-label="Edit message"><x-ui.icon name="edit" :size="13"/> Edit</button>
                    <form method="POST" action="{{ route('member.departments.messages.delete', [$departmentId, $message->id]) }}" data-chat-delete-form>@csrf @method('DELETE')<button type="submit" aria-label="Delete message"><x-ui.icon name="trash" :size="13"/> Delete</button></form>
                @endif
            </footer>
            <form hidden class="department-chat-inline" method="POST" action="{{ route('member.departments.messages.store', $departmentId) }}" data-chat-reply-form>
                @csrf<input type="hidden" name="parent_message_id" value="{{ $message->id }}">
                <label><span>Replying to {{ $message->first_name }}</span><textarea name="content" rows="2" maxlength="2000" required placeholder="Write a reply…"></textarea></label>
                <div><button type="button" data-chat-inline-cancel>Cancel</button><button class="button" type="submit">Send reply</button></div>
            </form>
            @if($isOwn)
                <form hidden class="department-chat-inline" method="POST" action="{{ route('member.departments.messages.update', [$departmentId, $message->id]) }}" data-chat-edit-form>
                    @csrf @method('PUT')
                    <label><span>Edit message</span><textarea name="content" rows="2" maxlength="2000" required>{{ $message->content }}</textarea></label>
                    <div><button type="button" data-chat-inline-cancel>Cancel</button><button class="button" type="submit">Save</button></div>
                </form>
            @endif
        </div>
    </article>
@empty
    <div class="department-chat-empty"><span><x-ui.icon name="message" :size="24"/></span><strong>Start the conversation</strong><p>Share an update, ask a question, or encourage your department.</p></div>
@endforelse
