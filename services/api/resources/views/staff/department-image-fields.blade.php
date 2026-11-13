@php
    $currentImage = old('image_url', $department->image_url ?? '');
    $currentAlt = old('image_alt', $department->image_alt ?? '');
@endphp
<div class="event-image-picker department-image-picker department-field-wide" data-department-image-picker>
    <div><strong>Department cover image</strong><span>Choose a published ministry image, upload a new one, or use an external image URL.</span></div>
    <label>Media Library image<select name="media_asset_id" data-department-image-library><option value="">No library image selected</option>@foreach($imageAssets as $asset)<option value="{{ $asset->id }}" data-url="{{ $asset->url }}" data-alt="{{ $asset->alt_text }}" @selected(old('media_asset_id') === $asset->id || (! old('media_asset_id') && $currentImage === $asset->url))>{{ $asset->title }}</option>@endforeach</select></label>
    <label>Upload a new image<input name="image_upload" type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-department-image-upload><small>JPG, PNG, WebP, or GIF · maximum 10 MB. Uploads are saved to the ministry Media Library.</small></label>
    <label>External image URL <small>Optional</small><input name="image_url" type="url" maxlength="1000" value="{{ $currentImage }}" placeholder="https://…" data-department-image-url><small>Use this only for an image hosted outside the ministry library.</small></label>
    <label>Image description<input name="image_alt" maxlength="255" value="{{ $currentAlt }}" placeholder="Describe the cover image" data-department-image-alt><small>Describe the image for people using screen readers.</small></label>
    <figure class="event-image-preview" data-department-image-preview @unless($currentImage) hidden @endunless><img src="{{ $currentImage }}" alt="" data-department-image-preview-image><figcaption data-department-image-preview-copy>Current cover image</figcaption></figure>
</div>
@once
<script>
document.addEventListener('change', (event) => {
    const picker = event.target.closest('[data-department-image-picker]');
    if (!picker) return;
    const library = picker.querySelector('[data-department-image-library]');
    const upload = picker.querySelector('[data-department-image-upload]');
    const external = picker.querySelector('[data-department-image-url]');
    const alt = picker.querySelector('[data-department-image-alt]');
    const preview = picker.querySelector('[data-department-image-preview]');
    const image = picker.querySelector('[data-department-image-preview-image]');
    const caption = picker.querySelector('[data-department-image-preview-copy]');
    let url = external.value.trim();
    let label = 'External cover image';
    if (event.target === library && library.value) {
        const option = library.options[library.selectedIndex];
        url = option.dataset.url || ''; external.value = ''; upload.value = '';
        if (!alt.value.trim() && option.dataset.alt) alt.value = option.dataset.alt;
        label = option.text;
    }
    if (event.target === upload && upload.files[0]) {
        url = URL.createObjectURL(upload.files[0]); library.value = ''; external.value = ''; label = upload.files[0].name;
    }
    if (event.target === external && external.value.trim()) { library.value = ''; upload.value = ''; }
    if (url) { image.src = url; caption.textContent = label; preview.hidden = false; }
    else { image.removeAttribute('src'); preview.hidden = true; }
});
</script>
@endonce
