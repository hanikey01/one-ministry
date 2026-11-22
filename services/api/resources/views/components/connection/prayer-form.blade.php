@props(['action', 'guest' => false])

<form method="POST" action="{{ $action }}" class="connection-request-form">
    @csrf
    <x-public-form-guard route="member.prayer"/>
    <x-connection.contact-fields :guest="$guest"/>
    <label>Prayer request<textarea name="prayer_text" rows="7" maxlength="4000" required>{{ old('prayer_text') }}</textarea></label>
    <p class="member-form-help">Your request is shared only with authorized administrators and prayer-team staff.</p>
    <label class="member-consent"><input type="checkbox" name="consent_to_contact" value="1" @checked(old('consent_to_contact'))><span>The ministry may contact me about this request.</span></label>
    <button class="button" type="submit">Submit privately</button>
</form>
