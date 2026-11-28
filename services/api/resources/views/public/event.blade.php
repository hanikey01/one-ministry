<x-layouts.public :title="$event->title" :settings="$settings" :features="$features">
    <main class="event-detail">
        <a class="public-back-link" href="{{ route('public.events') }}"><x-ui.icon name="arrow-left" :size="16"/> <span>All events</span></a>
        <section class="event-detail-hero">
            <div class="event-detail-image" @if($event->image_url) style="background-image:linear-gradient(180deg,transparent,rgb(7 20 41 / 68%)),url('{{ $event->image_url }}')" role="img" aria-label="{{ $event->image_alt }}" @endif></div>
            <div>
                <p>{{ $event->category ?: 'MINISTRY EVENT' }}</p>
                <h1>{{ $event->title }}</h1>
                <span>{{ \Illuminate\Support\Carbon::parse($event->starts_at)->timezone($settings->timezone)->format('l, F j · g:i A') }}</span>
                <span>{{ $event->location ?: 'Location to be announced' }}</span>
            </div>
        </section>
        <div class="event-detail-grid">
            <article>
                <h2>About this event</h2>
                <p>{{ $event->description ?: 'More information will be shared soon.' }}</p>
                @if($event->online_url)<a href="{{ $event->online_url }}" target="_blank" rel="noopener">Open online event link ↗</a>@endif
            </article>
            <aside>
                <h2>{{ $event->registration_open ? 'Reserve your place' : 'Join us' }}</h2>
                @if(session('status'))<div class="workspace-notice workspace-notice-success">{{ session('status') }}</div>@endif
                @if($errors->any())<div class="workspace-notice workspace-notice-error">{{ $errors->first() }}</div>@endif
                @if($event->cancelled_at)
                    <p>This event was cancelled. {{ $event->cancellation_reason }}</p>
                @elseif(!$event->registration_open)
                    <p>Registration is not required for this event. We look forward to seeing you there.</p>
                @else
                    <form method="POST" action="{{ route('public.events.register',$event->id) }}">
                        @csrf
                        <label>Name<input name="name" required value="{{ old('name',auth()->user()?->name) }}"></label>
                        <label>Email<input type="email" name="email" value="{{ old('email',auth()->user()?->email) }}"></label>
                        <button class="button">{{ $event->capacity && $registered >= $event->capacity ? 'Join waitlist' : 'Register now' }}</button>
                    </form>
                @endif
            </aside>
        </div>
    </main>
</x-layouts.public>
