<x-layouts.workspace title="Security" :ministry-name="$settings->name" :user-name="$user->name" user-role="Administrator">
    <x-slot:navigation></x-slot:navigation>
    <header class="workspace-page-heading"><p>SECURITY</p><h1>Your account security</h1><span>Protect elevated ministry actions and review the sessions connected to your account.</span></header>
    @if(session('security_notice'))<div class="workspace-notice workspace-notice-error" role="alert">{{ session('security_notice') }}</div>@endif
    @if(session('status'))<div class="workspace-notice workspace-notice-success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="workspace-notice workspace-notice-error" role="alert">{{ $errors->first() }}</div>@endif
    <section class="security-layout">
        <article class="security-card security-card-emphasis"><span class="security-card-icon"><x-ui.icon name="security" :size="20"/></span><p>TWO-FACTOR AUTHENTICATION</p><h2>{{ $mfaEnabled ? 'Your account is protected.' : 'Protect staff access.' }}</h2><span>{{ $mfaEnabled ? 'This session has access to security-sensitive staff controls for up to 12 hours after verification.' : 'Staff invitations, finance, care, and security operations require your authenticator app.' }}</span>
            @if(! $mfaEnabled && ! $mfaSetup)<form method="POST" action="{{ route('staff.security.mfa.begin') }}">@csrf<button class="button" type="submit">Set up two-factor authentication <x-ui.icon name="external" :size="15"/></button></form>@endif
        </article>
        <article class="security-card"><span class="security-card-icon {{ $emailVerified ? '' : 'muted' }}"><x-ui.icon name="message" :size="20"/></span><p>EMAIL VERIFICATION</p><h2>{{ $emailVerified ? 'Email verified.' : 'Verify your staff email.' }}</h2><span>{{ $emailVerified ? 'Your email address is ready for elevated ministry actions.' : ($emailDeliveryActive ? 'Verification protects invitations, finance, care, publishing, and other elevated actions.' : 'Set up an email provider before you can receive a verification link.') }}</span>
            @if(! $emailVerified && $emailDeliveryActive)<form method="POST" action="{{ route('staff.security.email-verification.request') }}">@csrf<button class="button button-secondary" type="submit">Send verification email <x-ui.icon name="external" :size="15"/></button></form>@elseif(! $emailVerified)<a class="button button-secondary" href="{{ route('staff.settings') }}#email-delivery">Set up email delivery <x-ui.icon name="settings" :size="15"/></a>@endif
        </article>
        <article class="security-card"><span class="security-card-icon muted"><x-ui.icon name="people" :size="20"/></span><p>ACTIVE SESSIONS</p><h2>{{ $sessions->count() }} {{ str('session')->plural($sessions->count()) }}</h2><span>Review your account access regularly and sign out from unfamiliar devices.</span></article>
    </section>
    @if($mfaSetup && ! $mfaEnabled)<section class="security-enrollment" aria-labelledby="mfa-enrollment-title"><div><p>STEP 1 OF 2</p><h2 id="mfa-enrollment-title">Add this key to your authenticator app.</h2><span>Choose “enter a setup key” in your app, then use the current six-digit code to confirm it here.</span></div><div class="security-copy-field"><code id="mfa-setup-key">{{ $mfaSetup['secret'] }}</code><button class="copy-button" type="button" data-copy-target="mfa-setup-key" aria-label="Copy authenticator setup key">Copy key</button></div><form method="POST" action="{{ route('staff.security.mfa.enable') }}">@csrf<label>Authenticator code<input name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required placeholder="000000"></label><button class="button" type="submit">Verify and enable</button></form></section>@endif
    @if($recoveryCodes)<section class="security-recovery" aria-labelledby="recovery-title"><div><p>SAVE THESE NOW</p><h2 id="recovery-title">Recovery codes</h2><span>Each code works once. Store them in a password manager or another private place.</span></div><div class="security-recovery-codes">@foreach($recoveryCodes as $code)<code>{{ $code }}</code>@endforeach<button class="copy-button" type="button" data-copy-target="recovery-codes" aria-label="Copy all recovery codes">Copy all codes</button><textarea id="recovery-codes" class="visually-hidden" readonly>{{ implode("\n", $recoveryCodes) }}</textarea></div></section>@endif
    <section class="data-panel security-sessions-panel"><table><thead><tr><th>Session</th><th>Last active</th></tr></thead><tbody>@forelse($sessions as $session)<tr><td data-label="Session"><strong>{{ $session->id }}</strong></td><td data-label="Last active">{{ \Illuminate\Support\Carbon::createFromTimestamp($session->last_activity)->timezone($settings->timezone)->format('M j, Y · g:i A') }}</td></tr>@empty<tr><td colspan="2" class="empty-cell">No sessions recorded.</td></tr>@endforelse</tbody></table></section>
    <script>
        document.querySelectorAll('[data-copy-target]').forEach((button) => button.addEventListener('click', async () => {
            const target = document.getElementById(button.dataset.copyTarget);
            if (!target) return;
            const value = target.value ?? target.textContent.trim();
            try { await navigator.clipboard.writeText(value); } catch { target.select?.(); document.execCommand('copy'); }
            const label = button.textContent;
            button.textContent = 'Copied';
            setTimeout(() => { button.textContent = label; }, 1800);
        }));
    </script>
</x-layouts.workspace>
