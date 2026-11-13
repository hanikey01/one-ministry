@props(['action', 'guest' => false])

<form method="POST" action="{{ $action }}" class="connection-request-form">
    @csrf
    <x-connection.contact-fields :guest="$guest"/>
    <label>Prayer request<textarea name="prayer_text" rows="7" maxlength="4000" required>{{ old('prayer_text') }}</textarea></label>
    <label>Who may read it?<select name="visibility" required><option value="prayer_team" @selected(old('visibility') === 'prayer_team')>Prayer team</option><option value="assigned_care_only" @selected(old('visibility', 'assigned_care_only') === 'assigned_care_only')>Assigned care staff only</option></select></label>
    <label class="member-consent"><input type="checkbox" name="consent_to_contact" value="1" @checked(old('consent_to_contact'))><span>The care team may contact me about this request.</span></label>
    <button class="button" type="submit">Submit privately</button>
</form>
