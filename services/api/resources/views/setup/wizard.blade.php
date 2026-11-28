<x-layouts.app title="Launch your ministry">
    <main class="setup-shell" data-setup-wizard>
        <style>
            .setup-brand { color: var(--brand-foreground, #fff) !important; }
            .setup-brand > span { color: var(--brand-foreground, #fff) !important; }
        </style>
        <aside class="setup-aside">
            <a class="setup-brand" href="{{ route('setup.create') }}"><span>✦</span><strong>Ministry Platform</strong></a>
            <div class="setup-aside-copy"><p>WELCOME</p><h1>Build a home for your ministry.</h1><span>A guided start for your public presence, people, and everyday ministry work.</span></div>
            <ol class="setup-progress" aria-label="Setup progress">
                <li class="is-active" data-setup-progress="0"><span>02</span><div><strong>Ministry</strong><small>Your identity</small></div></li>
                <li data-setup-progress="1"><span>03</span><div><strong>Home base</strong><small>Time and location</small></div></li>
                <li data-setup-progress="2"><span>04</span><div><strong>Theme</strong><small>Starting preset</small></div></li>
                <li data-setup-progress="3"><span>05</span><div><strong>Administrator</strong><small>Secure access</small></div></li>
            </ol>
            <p class="setup-aside-foot">Your choices remain editable after launch.</p>
        </aside>
        <section class="setup-workspace">
            <header class="setup-topbar"><span data-setup-count>Step 2 of 5</span><span class="setup-save-note"><i></i> Setup takes about 3 minutes</span></header>
            <form method="POST" action="{{ route('setup.store') }}" class="setup-form" data-setup-form data-appearance-settings novalidate>
                @csrf
                @if($errors->any())<div class="setup-error" role="alert"><strong>Review the highlighted details</strong><span>{{ $errors->first() }}</span></div>@endif
                <section class="setup-step is-active" data-setup-step="0" aria-labelledby="setup-ministry-title">
                    <p class="setup-eyebrow">START HERE</p><h2 id="setup-ministry-title">Tell us about your ministry.</h2><span class="setup-intro">This becomes the starting point for your public site and workspace.</span>
                    <div class="setup-fields"><label class="setup-field-wide">Ministry name<input name="name" value="{{ old('name') }}" maxlength="160" autocomplete="organization" required placeholder="e.g. Grace Community Church"></label><label class="setup-field-wide">Tagline <em>Optional</em><input name="tagline" value="{{ old('tagline') }}" maxlength="240" placeholder="e.g. A place to belong and grow"></label><label>Public website domain<input name="domain" value="{{ old('domain') }}" maxlength="253" inputmode="url" autocomplete="url" required placeholder="yourministry.org"></label><label>Public contact email <em>Optional</em><input type="email" name="contact_email" value="{{ old('contact_email') }}" maxlength="254" autocomplete="email" placeholder="hello@yourministry.org"></label></div>
                </section>
                <section class="setup-step" data-setup-step="1" aria-labelledby="setup-location-title" hidden>
                    <p class="setup-eyebrow">YOUR HOME BASE</p><h2 id="setup-location-title">Where does your ministry gather?</h2><span class="setup-intro">Add a complete primary location so visitors, events, service times, reminders, and local dates start with accurate details.</span>
                    <div class="setup-fields" data-address-fields data-subdivisions-endpoint="{{ route('address-catalog.subdivisions', ['country' => '__COUNTRY__']) }}">
                        <label class="setup-field-wide">Location name<input name="location" value="{{ old('location') }}" maxlength="160" autocomplete="organization" required placeholder="e.g. Main Campus"></label>
                        <label>Country<x-ui.country-select name="location_country_code" :selected="old('location_country_code')" :required="true"/></label>
                        <label>State, province, or region <em>Optional</em><x-ui.subdivision-select name="location_region_code" :country="old('location_country_code')" :selected="old('location_region_code')"/><small>Available choices update when you select a country.</small></label>
                        <label>City or locality<input name="location_city" value="{{ old('location_city') }}" maxlength="120" autocomplete="address-level2" required placeholder="e.g. Lagos"></label>
                        <label>Postal code <em>Optional</em><input name="location_postal_code" value="{{ old('location_postal_code') }}" maxlength="32" autocomplete="postal-code"></label>
                        <label class="setup-field-wide">Street address<input name="location_address_line_1" value="{{ old('location_address_line_1') }}" maxlength="255" autocomplete="address-line1" required placeholder="Building number and street"></label>
                        <label class="setup-field-wide">Address line 2 <em>Optional</em><input name="location_address_line_2" value="{{ old('location_address_line_2') }}" maxlength="255" autocomplete="address-line2" placeholder="Suite, floor, landmark, or district"></label>
                        <label class="setup-field-wide">Time zone<x-ui.timezone-select :selected="config('app.timezone')" :required="true" aria-label="Time zone"/><small>Search by city, region, or UTC offset. City-based zones adjust automatically for daylight-saving changes.</small></label>
                    </div>
                    <aside class="setup-tip"><span>✦</span><div><strong>You can add campuses later.</strong><p>Start with one primary location. Additional campuses, service schedules, and online locations are managed in the workspace.</p></div></aside>
                </section>
                <section class="setup-step" data-setup-step="2" aria-labelledby="setup-brand-title" hidden>
                    <p class="setup-eyebrow">YOUR APPLICATION THEME</p><h2 id="setup-brand-title">Choose a platform theme.</h2><span class="setup-intro">Start with a designed preset or generate the accessible interface palette from your ministry colour. Both light and dark mode remain available.</span>
                    <fieldset class="setup-theme-mode"><legend>Colour source</legend><label><input type="radio" name="chrome_mode" value="preset" data-chrome-mode="preset" @checked(old('chrome_mode', 'preset') === 'preset')><span><strong>Designed preset</strong><small>Use one of the platform colour systems.</small></span></label><label><input type="radio" name="chrome_mode" value="custom" data-chrome-mode="custom" @checked(old('chrome_mode') === 'custom')><span><strong>Custom ministry colour</strong><small>Generate an accessible palette from one colour.</small></span></label></fieldset>
                    <div class="setup-colours" data-appearance-preset-options @if(old('chrome_mode', 'preset') === 'custom') hidden @endif><label><span>Tech Blue</span><input type="radio" name="theme_preset" value="tech_blue" data-theme-preset @checked(old('theme_preset', 'tech_blue') === 'tech_blue')><b>Clean blue, bright actions, and deep navy navigation.</b></label><label><span>Neutral</span><input type="radio" name="theme_preset" value="neutral" data-theme-preset @checked(old('theme_preset') === 'neutral')><b>White, charcoal, and balanced gray.</b></label><label><span>Warm</span><input type="radio" name="theme_preset" value="warm" data-theme-preset @checked(old('theme_preset') === 'warm')><b>Soft stone with grounded contrast.</b></label></div>
                    <section class="setup-custom-colour" data-appearance-custom-colour @unless(old('chrome_mode') === 'custom') hidden @endunless aria-labelledby="setup-custom-colour-title"><div><strong id="setup-custom-colour-title">Ministry colour</strong><small>The sidebar uses a darker generated variation while actions and active states keep the selected hue.</small></div><label><span>Custom colour</span><div><input type="color" value="{{ old('chrome_color', '#0B1F3A') }}" data-chrome-colour-picker aria-label="Choose custom ministry colour"><input type="text" name="chrome_color" value="{{ old('chrome_color', '#0B1F3A') }}" data-chrome-colour-value inputmode="text" pattern="#[0-9A-Fa-f]{6}" maxlength="7" spellcheck="false" aria-describedby="setup-colour-help"></div><small id="setup-colour-help">Enter a six-digit hexadecimal colour, for example #0B1F3A.</small></label></section>
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
<script>
(() => {
    const wizard = document.querySelector('[data-setup-wizard]');
    if (!wizard) return;
    const sync = () => {
        const steps = [...wizard.querySelectorAll('[data-setup-step]')];
        const next = wizard.querySelector('[data-setup-next]');
        const finish = wizard.querySelector('[data-setup-finish]');
        const count = wizard.querySelector('[data-setup-count]');
        let active = steps.findIndex((step) => !step.hidden);
        if (active < 0) { active = steps.length - 1; steps[active].hidden = false; }
        const last = active === steps.length - 1;
        if (count) count.textContent = `Step ${active + 2} of ${steps.length + 1}`;
        if (next) { next.hidden = last; next.style.display = last ? 'none' : ''; }
        if (finish) { finish.hidden = !last; finish.style.display = last ? '' : 'none'; }
    };
    wizard.addEventListener('click', () => window.setTimeout(sync, 0));

    sync();
})();
</script>
</x-layouts.app>


