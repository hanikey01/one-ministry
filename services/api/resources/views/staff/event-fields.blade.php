@php($editing = (bool) $event)
<header><div><p>EVENT STUDIO</p><h2>{{ $editing ? 'Edit event' : 'Create an event' }}</h2><span>Give people a clear, welcoming picture of what to expect.</span></div><button type="button" class="workspace-dialog-close" data-workspace-dialog-close aria-label="Close"><x-ui.icon name="close" :size="18"/></button></header>
<div class="event-form-body">
    <section><h3>Event identity</h3><div class="event-form-grid">
        <label class="span-2">Title<input name="title" required maxlength="200" value="{{ old('title', $event->title ?? '') }}"></label>
        <label>Category<input name="category" maxlength="80" value="{{ old('category', $event->category ?? '') }}"></label>
        <label>Visibility<select name="status"><option value="draft" @selected(old('status', $event->status ?? 'draft') === 'draft')>Draft</option><option value="published" @selected(old('status', $event->status ?? '') === 'published')>Published</option></select></label>
        <label class="span-2">Description<textarea name="description" rows="4" maxlength="5000">{{ old('description', $event->description ?? '') }}</textarea></label>
        <div class="event-image-picker span-2" data-event-image-picker>
            <div><strong>Event cover image</strong><span>Choose a published ministry image, upload a new image, or use an HTTPS image URL.</span></div>
            <label>Media Library image<select name="media_asset_id" data-event-image-library><option value="">No library image selected</option>@foreach($imageAssets as $asset)<option value="{{ $asset->id }}" data-url="{{ $asset->url }}" data-alt="{{ $asset->alt_text }}" @selected(old('media_asset_id') === $asset->id || (! old('media_asset_id') && ($event->image_url ?? '') === $asset->url))>{{ $asset->title }}</option>@endforeach</select></label>
            <label>Upload a new image<input name="image_upload" type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-event-image-upload><small>JPG, PNG, WebP, or GIF · maximum 10 MB. The image is saved to the ministry Media Library.</small></label>
            <label>External image URL <small>Optional</small><input name="image_url" type="url" maxlength="1000" value="{{ old('image_url', $event->image_url ?? '') }}" placeholder="https://…" data-event-image-url><small>Use only when the image is hosted outside the ministry library.</small></label>
            <label>Image description<input name="image_alt" maxlength="255" value="{{ old('image_alt', $event->image_alt ?? '') }}" data-event-image-alt><small>Describe the image for people using screen readers.</small></label>
            <figure class="event-image-preview" data-event-image-preview @unless(old('image_url', $event->image_url ?? '')) hidden @endunless><img src="{{ old('image_url', $event->image_url ?? '') }}" alt="" data-event-image-preview-image><figcaption data-event-image-preview-copy>Current cover image</figcaption></figure>
        </div>
    </div></section>
    <section><h3>Time, place and registration</h3><div class="event-form-grid"><label>Starts<input type="datetime-local" name="starts_at" required value="{{ old('starts_at', isset($event->starts_at) ? \Illuminate\Support\Carbon::parse($event->starts_at)->format('Y-m-d\TH:i') : '') }}"></label><label>Ends<input type="datetime-local" name="ends_at" value="{{ old('ends_at', isset($event->ends_at) ? \Illuminate\Support\Carbon::parse($event->ends_at)->format('Y-m-d\TH:i') : '') }}"></label><label>Location<input name="location" list="event-locations" value="{{ old('location', $event->location ?? '') }}"><datalist id="event-locations">@foreach($locations as $location)<option value="{{ $location->name }}"></option>@endforeach</datalist></label><label>Online link<input type="url" name="online_url" value="{{ old('online_url', $event->online_url ?? '') }}"></label><label>Capacity<input type="number" name="capacity" min="1" value="{{ old('capacity', $event->capacity ?? '') }}"></label><label>Registration deadline<input type="datetime-local" name="registration_deadline" value="{{ old('registration_deadline', isset($event->registration_deadline) ? \Illuminate\Support\Carbon::parse($event->registration_deadline)->format('Y-m-d\TH:i') : '') }}"></label></div><div class="event-options">@foreach(['registration_open' => 'Registration open', 'waitlist_enabled' => 'Enable waitlist', 'featured' => 'Feature event'] as $key => $label)<label><input type="checkbox" name="{{ $key }}" value="1" @checked(old($key, $event?->{$key} ?? ($key !== 'featured')))><span><strong>{{ $label }}</strong></span></label>@endforeach</div></section>
</div>

@once
    <script>
        document.addEventListener('change', (event) => {
            const picker = event.target.closest('[data-event-image-picker]');
            if (!picker) return;
            const library = picker.querySelector('[data-event-image-library]');
            const external = picker.querySelector('[data-event-image-url]');
            const alt = picker.querySelector('[data-event-image-alt]');
            const preview = picker.querySelector('[data-event-image-preview]');
            const image = picker.querySelector('[data-event-image-preview-image]');
            const caption = picker.querySelector('[data-event-image-preview-copy]');
            let url = external.value.trim();
            let label = 'External cover image';
            if (event.target === library && library.value) {
                const option = library.options[library.selectedIndex];
                url = option.dataset.url || '';
                external.value = '';
                if (!alt.value.trim() && option.dataset.alt) alt.value = option.dataset.alt;
                label = option.text;
            }
            if (event.target.matches('[data-event-image-upload]') && event.target.files[0]) {
                url = URL.createObjectURL(event.target.files[0]);
                library.value = '';
                external.value = '';
                label = event.target.files[0].name;
            }
            if (event.target === external && external.value.trim()) library.value = '';
            if (url) { image.src = url; caption.textContent = label; preview.hidden = false; }
            else { image.removeAttribute('src'); preview.hidden = true; }
        });
    </script>
@endonce
