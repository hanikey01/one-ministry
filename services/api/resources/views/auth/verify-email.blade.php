<x-layouts.app title="Verify email" :ministry-name="$settings->name">
    <main class="auth-shell">
        <section class="auth-intro"><a href="{{ route('public.home') }}" class="auth-brand"><span class="auth-mark">{{ mb_strtoupper(mb_substr($settings->name, 0, 1)) }}</span><strong>{{ $settings->name }}</strong></a><div class="auth-story"><p>ACCOUNT SECURITY</p><h1>Confirm your email address.</h1><span>A verified email helps protect your ministry’s sensitive staff actions.</span></div><small>Private access · Protected by your ministry</small></section>
        <section class="auth-panel"><div class="auth-panel-inner"><a href="{{ route('public.home') }}" class="auth-mobile-brand"><span class="auth-mark">{{ mb_strtoupper(mb_substr($settings->name, 0, 1)) }}</span><strong>{{ $settings->name }}</strong></a><div><p class="auth-eyebrow">VERIFY EMAIL</p><h2>One final security step.</h2><span>Confirm this email address to unlock the staff actions assigned to your role.</span></div>
            <form method="POST" action="{{ route('email.verify') }}" class="auth-form">@csrf
                <input type="hidden" name="token" value="{{ old('token', $token) }}">
                @if($errors->any())<div class="form-errors" role="alert">{{ $errors->first() }}</div>@endif
                <button class="button auth-submit" type="submit">Verify email address <span aria-hidden="true">→</span></button>
            </form><p class="auth-help">This link expires after 30 minutes and can only be used once.</p></div>
        </section>
    </main>
</x-layouts.app>
