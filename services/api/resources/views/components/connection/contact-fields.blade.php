@props(['guest' => false])

@if($guest)
    <fieldset class="connection-contact-fields">
        <legend>Your details</legend>
        <p>We ask for an email so the ministry team can respond to your request. Creating an account is optional.</p>
        <div class="connection-contact-grid">
            <label>First name<input name="first_name" value="{{ old('first_name') }}" maxlength="100" autocomplete="given-name" required></label>
            <label>Last name <small>Optional</small><input name="last_name" value="{{ old('last_name') }}" maxlength="100" autocomplete="family-name"></label>
            <label>Email address<input type="email" name="email" value="{{ old('email') }}" maxlength="254" autocomplete="email" required></label>
            <label>Phone <small>Optional</small><input type="tel" name="phone" value="{{ old('phone') }}" maxlength="40" autocomplete="tel"></label>
        </div>
    </fieldset>
@endif
<div class="connection-honeypot" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
