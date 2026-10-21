<x-layouts.app title="Launch your ministry">
    <main class="setup-shell" data-setup-wizard>
        <aside class="setup-aside">
            <a class="setup-brand" href="{{ route('setup.create') }}"><span>✦</span><strong>Ministry Platform</strong></a>
            <div class="setup-aside-copy"><p>WELCOME</p><h1>Build a home for your ministry.</h1><span>A guided start for your public presence, people, and everyday ministry work.</span></div>
            <ol class="setup-progress" aria-label="Setup progress">
                <li class="is-active" data-setup-progress="0"><span>01</span><div><strong>Ministry</strong><small>Your identity</small></div></li>
                <li data-setup-progress="1"><span>02</span><div><strong>Home base</strong><small>Time and location</small></div></li>
                <li data-setup-progress="2"><span>03</span><div><strong>Appearance</strong><small>Starting colours</small></div></li>
                <li data-setup-progress="3"><span>04</span><div><strong>Administrator</strong><small>Secure access</small></div></li>
            </ol>
            <p class="setup-aside-foot">Your choices remain editable after launch.</p>
        </aside>
        <section class="setup-workspace">
            <header class="setup-topbar"><span data-setup-count>Step 1 of 4</span><span class="setup-save-note"><i></i> Setup takes about 3 minutes</span></header>
            <form method="POST" action="{{ route('setup.store') }}" class="setup-form" data-setup-form novalidate>
                @csrf
                @if($errors->any())<div class="setup-error" role="alert"><strong>Review the highlighted details</strong><span>{{ $errors->first() }}</span></div>@endif
                <section class="setup-step is-active" data-setup-step="0" aria-labelledby="setup-ministry-title">
                    <p class="setup-eyebrow">START HERE</p><h2 id="setup-ministry-title">Tell us about your ministry.</h2><span class="setup-intro">This becomes the starting point for your public site and workspace.</span>
                    <div class="setup-fields"><label class="setup-field-wide">Ministry name<input name="name" value="{{ old('name') }}" maxlength="160" autocomplete="organization" required placeholder="e.g. Grace Community Church"></label><label class="setup-field-wide">Tagline <em>Optional</em><input name="tagline" value="{{ old('tagline') }}" maxlength="240" placeholder="e.g. A place to belong and grow"></label><label>Public website domain<input name="domain" value="{{ old('domain') }}" maxlength="253" inputmode="url" autocomplete="url" required placeholder="yourministry.org"></label><label>Public contact email <em>Optional</em><input type="email" name="contact_email" value="{{ old('contact_email') }}" maxlength="254" autocomplete="email" placeholder="hello@yourministry.org"></label></div>
                </section>
                <section class="setup-step" data-setup-step="1" aria-labelledby="setup-location-title" hidden>
                    <p class="setup-eyebrow">YOUR HOME BASE</p><h2 id="setup-location-title">Where does your ministry gather?</h2><span class="setup-intro">Choose the time zone for service times, events, reminders, and birthdays.</span>
                    <div class="setup-fields"><label class="setup-field-wide">Primary location or online campus<input name="location" value="{{ old('location') }}" maxlength="160" required placeholder="e.g. Main Campus or Online Church"></label><label class="setup-field-wide">Time zone<div class="setup-timezone-picker" data-setup-timezone-picker><input type="hidden" name="timezone" value="{{ old('timezone', config('app.timezone')) }}"><button class="setup-timezone-trigger" type="button" aria-expanded="false" aria-controls="setup-timezone-options"><span data-setup-timezone-label>{{ old('timezone', config('app.timezone')) }}</span><x-ui.icon name="chevron-down" :size="17"/></button><div class="setup-timezone-options" id="setup-timezone-options" hidden><div class="setup-timezone-search"><x-ui.icon name="search" :size="16"/><input type="search" data-setup-timezone-search placeholder="Search city or time zone" autocomplete="off"></div><div class="setup-timezone-list" role="listbox" aria-label="Time zones" data-setup-timezone-list>@foreach($timezones as $timezone)<button type="button" role="option" data-timezone-option data-value="{{ $timezone }}" aria-selected="{{ old('timezone', config('app.timezone')) === $timezone ? 'true' : 'false' }}">{{ $timezone }}</button>@endforeach</div></div></div></label></div>
                    <aside class="setup-tip"><span>✦</span><div><strong>You can add campuses later.</strong><p>Start with one primary location. Additional campuses, service schedules, and online locations are managed in the workspace.</p></div></aside>
                </section>
                <section class="setup-step" data-setup-step="2" aria-labelledby="setup-brand-title" hidden>
                    <p class="setup-eyebrow">MAKE IT YOURS</p><h2 id="setup-brand-title">Choose a confident starting palette.</h2><span class="setup-intro">These colours shape calls to action and highlights. You can refine the full brand system later.</span>
                    <div class="setup-colour-preview"><div class="setup-preview-mark" data-setup-preview-mark>M</div><div><strong data-setup-preview-name>Your ministry</strong><span>Faith · Community · Purpose</span></div><button type="button" class="setup-preview-action" data-setup-preview-action>Welcome home</button></div>
                    <div class="setup-colours"><label><span>Primary colour</span><input type="color" name="primary_color" value="{{ old('primary_color','#343A40') }}" data-setup-colour="primary"><b data-setup-primary-value>#343A40</b></label><label><span>Accent colour</span><input type="color" name="accent_color" value="{{ old('accent_color','#697386') }}" data-setup-colour="accent"><b data-setup-accent-value>#697386</b></label></div>
                </section>
                <section class="setup-step" data-setup-step="3" aria-labelledby="setup-admin-title" hidden>
                    <p class="setup-eyebrow">YOUR SECURE WORKSPACE</p><h2 id="setup-admin-title">Create the first administrator account.</h2><span class="setup-intro">This account manages ministry settings, staff access, and launch configuration.</span>
                    <div class="setup-fields"><label>Full name<input name="admin_name" value="{{ old('admin_name') }}" maxlength="160" autocomplete="name" required placeholder="Your name"></label><label>Email address<input type="email" name="admin_email" value="{{ old('admin_email') }}" maxlength="254" autocomplete="email" required placeholder="you@yourministry.org"></label><label class="setup-field-wide">Create a password<input type="password" name="password" minlength="12" autocomplete="new-password" required placeholder="At least 12 characters"><small>Use a long, unique password. You can add multi factor authentication after launch.</small></label></div>
                    <aside class="setup-tip setup-tip-security"><span>⌁</span><div><strong>Your ministry remains independent.</strong><p>This installation has its own database, settings, and staff accounts. Nothing is shared with another ministry.</p></div></aside>
                </section>
                <footer class="setup-actions"><button class="setup-back" type="button" data-setup-back hidden>Back</button><span data-setup-validation aria-live="polite"></span><button class="setup-next" type="button" data-setup-next>Continue <span>→</span></button><button class="setup-finish" type="submit" data-setup-finish hidden>Launch workspace <span>→</span></button></footer>
            </form>
        </section>
    </main>
</x-layouts.app>


