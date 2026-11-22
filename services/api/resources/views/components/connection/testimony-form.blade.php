@props(['action', 'categories', 'guest' => false])

<form method="POST" action="{{ $action }}" class="connection-request-form">
    @csrf
    <x-public-form-guard route="member.testimonies.store"/>
    <x-connection.contact-fields :guest="$guest"/>
    <label>Title<input name="title" value="{{ old('title') }}" maxlength="180" required placeholder="A short title for your story"></label>
    <label>Category<select name="category" required>@foreach($categories as $category)<option value="{{ $category }}" @selected(old('category') === $category)>{{ str($category)->headline() }}</option>@endforeach</select></label>
    <label>Your testimony<textarea name="body" rows="10" minlength="20" maxlength="12000" required placeholder="Share what happened, what changed, and what this means to you.">{{ old('body') }}</textarea></label>
    @if($guest)<label class="testimony-consent"><input type="checkbox" name="consent_to_contact" value="1" required @checked(old('consent_to_contact'))><span>I agree that the ministry team may contact me about this submission.</span></label>@endif
    <label class="testimony-consent"><input type="checkbox" name="consent_public" value="1" required @checked(old('consent_public'))><span>I consent to staff reviewing this testimony and understand it may be published publicly after review.</span></label>
    <label class="testimony-consent"><input type="checkbox" name="show_author_name" value="1" @checked(old('show_author_name'))><span>Show my name if staff approve this story for public publishing.</span></label>
    <button class="button" type="submit">Submit for review</button>
</form>
