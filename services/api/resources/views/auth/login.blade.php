<x-layouts.app title="Sign in" :ministry-name="$settings->name">
    <main class="auth-shell">
        <section class="auth-intro"><a href="{{ route('public.home') }}" class="auth-brand"><span class="auth-mark">{{ mb_strtoupper(mb_substr($settings->name, 0, 1)) }}</span><strong>{{ $settings->name }}</strong></a><div class="auth-story"><p>WELCOME HOME</p><h1>A meaningful place to belong, grow, and serve.</h1><span>{{ $settings->tagline ?: 'One secure account for your ministry community and team.' }}</span></div><small>Private access · Protected by your ministry</small></section>
        <section class="auth-panel"><div class="auth-panel-inner"><a href="{{ route('public.home') }}" class="auth-mobile-brand"><span class="auth-mark">{{ mb_strtoupper(mb_substr($settings->name, 0, 1)) }}</span><strong>{{ $settings->name }}</strong></a><div><p class="auth-eyebrow">WELCOME BACK</p><h2>Sign in to your account</h2><span>Use the email address and password connected to your ministry profile.</span></div>
            @if (session('status')) <div class="form-success" role="status">{{ session('status') }}</div> @endif
            <form method="POST" action="{{ route('login.store') }}" class="auth-form">@csrf
                <label>Email address<input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus></label>
                <label>Password<input type="password" name="password" autocomplete="current-password" required></label><a class="auth-inline-link" href="{{ route('password.forgot') }}">Forgot password?</a>
                <label class="check-field"><input type="checkbox" name="remember" value="1"> Keep me signed in on this device</label>
                @if ($errors->any()) <div class="form-errors" role="alert">{{ $errors->first() }}</div> @endif
                <button class="button auth-submit" type="submit">Sign in securely <span aria-hidden="true">→</span></button>
            </form><p class="auth-help">New here? <a href="{{ route('register') }}">Create a member account</a><br>Need staff access? Contact your ministry administrator.</p></div>
        </section>
    </main>
</x-layouts.app>
