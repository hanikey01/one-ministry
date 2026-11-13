@props(['action', 'journeyOptions', 'guest' => false])

<form method="POST" action="{{ $action }}" class="connection-request-form">
    @csrf
    <x-connection.contact-fields :guest="$guest"/>
    <label>I am interested in<select name="type" required><option value="">Choose an option</option>@foreach($journeyOptions as $option)<option value="{{ $option->key }}" @selected(old('type') === $option->key)>{{ $option->label }}</option>@endforeach</select></label>
    <label class="member-consent"><input type="checkbox" name="consent_to_contact" value="1" required @checked(old('consent_to_contact'))><span>I agree that the ministry team may contact me about this request.</span></label>
    <button class="button" type="submit">Send request</button>
</form>
