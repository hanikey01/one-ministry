@php
    $content = $element['content'] ?? $element['data'] ?? [];
@endphp
@switch($element['type'] ?? '')
    @case('custom_code')@case('html')
        @php
            $customFrameId = 'custom-frame-'.preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($element['id'] ?? 'content'));
            $isAiCanvasDesign = str_contains((string) ($element['settings']['custom_class'] ?? ''), 'ai-canvas-design');
            $themeBridge = $isAiCanvasDesign ? '' : "addEventListener('message',e=>{if(e.data?.type!=='ai-canvas-theme')return;for(const [key,value] of Object.entries(e.data.tokens||{}))document.documentElement.style.setProperty(key,value);send()});";
            $frameBridge = "(()=>{const id=".json_encode($customFrameId).";const send=()=>parent.postMessage({type:'ai-canvas-height',id,height:Math.ceil(document.documentElement.scrollHeight)},'*');".$themeBridge."new ResizeObserver(send).observe(document.documentElement);addEventListener('load',send);send()})();";
            $canvasBodyClass = $isAiCanvasDesign ? ' class="ai-canvas-sandbox"' : '';
            $canvasWidthGuard = $isAiCanvasDesign ? 'body.ai-canvas-sandbox>section,body.ai-canvas-sandbox>section>:only-child{width:100%!important;inline-size:100%!important;max-width:none!important;max-inline-size:none!important;min-width:0!important;min-inline-size:0!important;flex:1 1 100%!important;align-self:stretch!important;justify-self:stretch!important}' : '';
            $renderedCustomHtml = $isAiCanvasDesign
                ? \App\Services\Ai\AiCanvasSectionService::neutraliseLinks((string) ($content['html'] ?? ''))
                : (string) ($content['html'] ?? '');
        $customSource = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="Content-Security-Policy" content="default-src \'none\'; style-src \'unsafe-inline\'; script-src \'unsafe-inline\'; img-src data: blob: http: https:; media-src data: blob: http: https:; frame-src http: https:; connect-src \'none\'; object-src \'none\'; form-action \'none\'; base-uri \'none\';"><style>:root{color-scheme:light dark;--font-display:Georgia,"Times New Roman",serif;--font-body:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;--action:#2563eb;--action-foreground:#fff;--brand:#1e3a5f;--canvas:#f8fafc;--surface:#fff;--surface-muted:#f1f5f9;--ink:#0f172a;--muted:#64748b;--line:#e2e8f0;--line-strong:#cbd5e1}*{box-sizing:border-box}html,body{width:100%;margin:0;overflow:hidden;background:transparent;color:var(--ink);font-family:var(--font-body)}body{min-width:0}a{color:inherit}a[data-ai-link-placeholder]{cursor:default}img,video{max-width:100%;height:auto}</style><style>'.($content['css'] ?? '').'</style><style>html,body{width:100%!important;min-width:0!important}body>:not(script){width:100%!important;max-width:none!important;min-width:0!important}'.$canvasWidthGuard.'</style></head><body'.$canvasBodyClass.'>'.$renderedCustomHtml.'<script>'.str_ireplace('</script', '<\\/script', $frameBridge).'</script></body></html>';
            $customFallbackId = 'custom-fallback-'.preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($element['id'] ?? 'content'));
        @endphp
        <section class="public-page-custom-code"><p id="{{ $customFallbackId }}" class="public-page-custom-code-fallback">{{ $content['fallback'] ?? '' }}</p><iframe id="{{ $customFrameId }}" data-ai-canvas-frame @if($isAiCanvasDesign) data-ai-canvas-design @endif sandbox="{{ $isAiCanvasDesign ? 'allow-scripts' : 'allow-scripts allow-top-navigation-by-user-activation' }}" title="{{ $content['reason'] ?? 'Custom page section' }}" aria-describedby="{{ $customFallbackId }}" srcdoc="{{ $customSource }}"></iframe></section>
    @break
    @case('hero')<section class="public-page-hero"><small>{{ $content['eyebrow'] ?? '' }}</small><h1>{{ $content['title'] ?? '' }}</h1><p>{{ $content['body'] ?? '' }}</p>@if(!empty($content['button_label']) && !empty($content['button_url']))<a class="button" href="{{ $content['button_url'] }}">{{ $content['button_label'] }}</a>@endif</section>@break
    @case('heading')<section class="public-page-copy"><h2>{{ $content['text'] ?? '' }}</h2></section>@break
    @case('paragraph')<section class="public-page-copy"><p>{!! nl2br(e($content['text'] ?? '')) !!}</p></section>@break
    @case('rich_text')<section class="public-page-copy public-page-rich-text">@if(!empty($content['title']))<h2>{{ $content['title'] }}</h2>@endif<p>{!! nl2br(e($content['text'] ?? '')) !!}</p></section>@break
    @case('button')@if(!empty($content['label']))<div class="public-page-button">@if(!empty($content['url']))<a class="button" href="{{ $content['url'] }}">{{ $content['label'] }}</a>@else<button class="button" type="button">{{ $content['label'] }}</button>@endif</div>@endif @break
    @case('feature_list')
        @php $featureItems = array_slice(array_values(array_filter(preg_split('/\R/', (string) ($content['items'] ?? '')) ?: [])), 0, 12); @endphp
        <section class="public-page-feature-list"><h2>{{ $content['title'] ?? '' }}</h2><ul>@foreach($featureItems as $item)<li>{{ trim($item) }}</li>@endforeach</ul></section>
    @break
    @case('testimonial')<figure class="public-page-testimonial"><blockquote>“{{ $content['quote'] ?? '' }}”</blockquote><figcaption><strong>{{ $content['name'] ?? '' }}</strong>@if(!empty($content['role']))<span>{{ $content['role'] }}</span>@endif</figcaption></figure>@break
    @case('statistics')<section class="public-page-statistics"><h2>{{ $content['title'] ?? '' }}</h2><div>@foreach(['one','two','three'] as $slot)@if(!empty($content['stat_'.$slot.'_value']))<article><strong>{{ $content['stat_'.$slot.'_value'] }}</strong><span>{{ $content['stat_'.$slot.'_label'] ?? '' }}</span></article>@endif@endforeach</div></section>@break
    @case('alert')<section class="public-page-alert public-page-alert-{{ in_array($content['tone'] ?? '', ['info', 'success', 'warning'], true) ? $content['tone'] : 'info' }}" role="note"><strong>{{ $content['title'] ?? '' }}</strong><p>{{ $content['body'] ?? '' }}</p></section>@break
    @case('progress')
        @php $progressValue = min(100, max(0, (int) ($content['value'] ?? 0))); @endphp
        <section class="public-page-progress"><div><strong>{{ $content['title'] ?? '' }}</strong><span>{{ $content['label'] ?? '' }}</span></div><progress max="100" value="{{ $progressValue }}">{{ $progressValue }}%</progress><small>{{ $progressValue }}%</small></section>
    @break
    @case('table')
        @php
            $tableHeaders = array_values(array_filter(array_map('trim', explode('|', (string) ($content['headers'] ?? '')))));
            $tableRows = array_values(array_filter(preg_split('/\R/', (string) ($content['rows'] ?? '')) ?: []));
        @endphp
        <section class="public-page-table">
            @if(!empty($content['title']))<h2>{{ $content['title'] }}</h2>@endif
            @if($tableHeaders)
                <div><table><thead><tr>@foreach($tableHeaders as $header)<th scope="col">{{ $header }}</th>@endforeach</tr></thead><tbody>@foreach($tableRows as $row)<tr>@foreach(explode('|', $row) as $cell)<td>{{ trim($cell) }}</td>@endforeach</tr>@endforeach</tbody></table></div>
            @endif
        </section>
    @break
    @case('icon')@php $iconName = in_array($content['icon'] ?? '', ['heart', 'people', 'calendar', 'location', 'message', 'book', 'star', 'check', 'hand-heart', 'globe'], true) ? $content['icon'] : 'star'; @endphp<div class="public-page-icon" aria-label="{{ $content['label'] ?? '' }}"><x-ui.icon :name="$iconName" :size="28"/><span>{{ $content['label'] ?? '' }}</span></div>@break
    @case('icon_box')@php $iconName = in_array($content['icon'] ?? '', ['heart', 'people', 'calendar', 'location', 'message', 'book', 'star', 'check', 'hand-heart', 'globe'], true) ? $content['icon'] : 'star'; @endphp<section class="public-page-icon-box"><x-ui.icon :name="$iconName" :size="28"/><h2>{{ $content['title'] ?? '' }}</h2><p>{{ $content['body'] ?? '' }}</p>@if(!empty($content['button_label']) && !empty($content['button_url']))<a class="button" href="{{ $content['button_url'] }}">{{ $content['button_label'] }}</a>@endif</section>@break
    @case('icon_list')@php $iconItems = array_slice(array_values(array_filter(preg_split('/\R/', (string) ($content['items'] ?? '')) ?: [])), 0, 12); @endphp<section class="public-page-icon-list"><h2>{{ $content['title'] ?? '' }}</h2><ul>@foreach($iconItems as $item)<li><x-ui.icon name="check" :size="17"/><span>{{ trim($item) }}</span></li>@endforeach</ul></section>@break
    @case('image')<figure class="public-page-image">@if(!empty($content['url']))<img src="{{ $content['url'] }}" alt="{{ $content['alt'] ?? '' }}" loading="lazy" decoding="async">@elseif($editorPreview ?? false)<div class="public-page-image-placeholder">Choose an image: {{ $content['image_hint'] ?? 'Add a suitable ministry image' }}</div>@endif@if(!empty($content['caption']))<figcaption>{{ $content['caption'] }}</figcaption>@endif</figure>@break
    @case('image_card')<article class="public-page-image-card">@if(!empty($content['url']))<img src="{{ $content['url'] }}" alt="{{ $content['alt'] ?? '' }}" loading="lazy" decoding="async">@endif<div><h2>{{ $content['title'] ?? '' }}</h2><p>{{ $content['body'] ?? '' }}</p>@if(!empty($content['button_label']) && !empty($content['button_url']))<a class="button" href="{{ $content['button_url'] }}">{{ $content['button_label'] }}</a>@endif</div></article>@break
    @case('gallery')
        @php $galleryImages = array_slice(array_values(array_filter(preg_split('/\R/', (string) ($content['images'] ?? '')) ?: [])), 0, 12); $galleryCarousel = ($content['display'] ?? 'grid') === 'carousel'; @endphp
        @if($galleryCarousel)
            <figure class="public-page-gallery-carousel" data-public-carousel tabindex="0" aria-label="Image gallery">
                <div class="public-page-gallery-carousel-stage">
                    @foreach($galleryImages as $index => $image)<button type="button" class="public-page-gallery-slide" data-carousel-slide @unless($editorPreview ?? false) data-gallery-lightbox-open data-gallery-index="{{ $index }}" data-gallery-image="{{ trim($image) }}" aria-label="Open image {{ $index + 1 }} of {{ count($galleryImages) }} full screen" @endunless @if($index > 0) hidden aria-hidden="true" @endif><img src="{{ trim($image) }}" alt="Gallery image {{ $index + 1 }} of {{ count($galleryImages) }}" loading="{{ $index === 0 ? 'eager' : 'lazy' }}" decoding="async" draggable="false"></button>@endforeach
                    @if($galleryImages === [] && ($editorPreview ?? false))<div class="public-page-image-placeholder">Add gallery images from the Media Library.</div>@endif
                </div>
                @if(count($galleryImages) > 1)<div class="public-page-gallery-carousel-controls"><button type="button" data-carousel-previous aria-label="Previous image"><x-ui.icon class="carousel-arrow-icon" name="chevron-left" :size="28"/></button><span data-carousel-status aria-live="polite">1 of {{ count($galleryImages) }}</span><button type="button" data-carousel-next aria-label="Next image"><x-ui.icon class="carousel-arrow-icon" name="chevron-right" :size="28"/></button></div>@endif
                @if(!empty($content['caption']))<figcaption>{{ $content['caption'] }}</figcaption>@endif
                @unless($editorPreview ?? false)
                    <dialog class="public-page-gallery-lightbox" data-gallery-lightbox aria-label="Full-screen image viewer">
                        <div class="public-page-gallery-lightbox-shell">
                            <button class="public-page-gallery-lightbox-close" type="button" data-gallery-lightbox-close aria-label="Close full-screen image viewer">×</button>
                            <div class="public-page-gallery-lightbox-stage"><img data-gallery-lightbox-image alt=""></div>
                            <div class="public-page-gallery-lightbox-controls">
                                <button type="button" data-gallery-lightbox-previous aria-label="Previous full-screen image"><x-ui.icon class="carousel-arrow-icon" name="chevron-left" :size="28"/></button>
                                <span data-gallery-lightbox-status aria-live="polite"></span>
                                <button type="button" data-gallery-lightbox-next aria-label="Next full-screen image"><x-ui.icon class="carousel-arrow-icon" name="chevron-right" :size="28"/></button>
                            </div>
                        </div>
                    </dialog>
                @endunless
            </figure>
        @else
            <figure class="public-page-gallery public-gallery-{{ $content['columns'] ?? 'three' }}">@foreach($galleryImages as $image)<img src="{{ trim($image) }}" alt="">@endforeach@if(!empty($content['caption']))<figcaption>{{ $content['caption'] }}</figcaption>@endif</figure>
        @endif
    @break
    @case('video_embed')
        @php $videoUrl = (string) ($content['url'] ?? ''); $youtubeId = preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/))([A-Za-z0-9_-]{6,})~', $videoUrl, $match) ? $match[1] : null; $vimeoId = preg_match('~vimeo\.com/(?:video/)?([0-9]+)~', $videoUrl, $match) ? $match[1] : null; $isDirectVideo = str_starts_with($videoUrl, '/') || preg_match('~\.(?:mp4|webm|ogv|ogg|m3u8)(?:\?.*)?$~i', $videoUrl); $videoHero = ($content['presentation'] ?? 'standard') === 'hero'; @endphp
        <figure class="public-page-video {{ $videoHero ? 'public-page-video-hero' : '' }}">
            @if($youtubeId)<iframe src="https://www.youtube-nocookie.com/embed/{{ $youtubeId }}?rel=0" @if($videoHero) data-video-hero-embed data-autoplay-src="https://www.youtube-nocookie.com/embed/{{ $youtubeId }}?autoplay=1&amp;mute=1&amp;controls=0&amp;playsinline=1&amp;loop=1&amp;playlist={{ $youtubeId }}&amp;rel=0" @endif title="{{ $content['title'] ?? 'Video' }}" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>
            @elseif($vimeoId)<iframe src="https://player.vimeo.com/video/{{ $vimeoId }}?dnt=1" @if($videoHero) data-video-hero-embed data-autoplay-src="https://player.vimeo.com/video/{{ $vimeoId }}?autoplay=1&amp;muted=1&amp;controls=0&amp;loop=1&amp;playsinline=1&amp;dnt=1" @endif title="{{ $content['title'] ?? 'Video' }}" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>
            @elseif($isDirectVideo)<video @if($videoHero) muted loop playsinline data-video-hero @else controls @endif preload="metadata" src="{{ $videoUrl }}">Your browser does not support video playback.</video>
            @elseif($videoUrl)<iframe src="{{ $videoUrl }}" title="{{ $content['title'] ?? 'Embedded media' }}" loading="lazy" referrerpolicy="no-referrer" sandbox="allow-scripts allow-same-origin allow-presentation" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>
            @else
                @if($videoHero)
                    @if($editorPreview ?? false)<div class="public-page-image-placeholder">Choose the full-width crusade hero video.</div>@endif
                @else
                    <div class="public-page-callout"><strong>Video unavailable</strong><p>Add an internal media path or external HTTP(S) media URL.</p></div>
                @endif
            @endif
            @if(!$videoHero && !empty($content['caption']))<figcaption>{{ $content['caption'] }}</figcaption>@endif
        </figure>
    @break
    @case('audio')<figure class="public-page-audio"><figcaption><strong>{{ $content['title'] ?? 'Listen' }}</strong>@if(!empty($content['caption']))<span>{{ $content['caption'] }}</span>@endif</figcaption>@if(!empty($content['url']))<audio controls preload="metadata" src="{{ $content['url'] }}">Your browser does not support audio playback.</audio>@endif</figure>@break
    @case('file')<section class="public-page-file"><div><small>DOWNLOAD</small><h2>{{ $content['label'] ?? 'Download resource' }}</h2><p>{{ $content['description'] ?? '' }}</p></div>@if(!empty($content['url']))<a class="button" href="{{ $content['url'] }}" target="_blank" rel="noopener">Download</a>@endif</section>@break
    @case('media_text')<section class="public-page-media-text public-page-media-{{ $content['placement'] ?? 'left' }}"><div class="public-page-media-visual">@if(!empty($content['media_url']))<img src="{{ $content['media_url'] }}" alt="{{ $content['media_alt'] ?? '' }}" loading="lazy" decoding="async">@else<span>Media</span>@endif</div><div><h2>{{ $content['title'] ?? '' }}</h2><p>{{ $content['body'] ?? '' }}</p>@if(!empty($content['button_label']) && !empty($content['button_url']))<a class="button" href="{{ $content['button_url'] }}">{{ $content['button_label'] }}</a>@endif</div></section>@break
    @case('list')<section class="public-page-list"><h2>{{ $content['title'] ?? '' }}</h2>@php $listItems = array_values(array_filter(preg_split('/\R/', (string) ($content['items'] ?? '')) ?: [])); @endphp@if(($content['ordered'] ?? 'no') === 'yes')<ol>@foreach($listItems as $item)<li>{{ trim($item) }}</li>@endforeach</ol>@else<ul>@foreach($listItems as $item)<li>{{ trim($item) }}</li>@endforeach</ul>@endif</section>@break
    @case('accordion')<section class="public-page-accordion"><h2>{{ $content['title'] ?? '' }}</h2>@foreach(['one', 'two', 'three'] as $slot)@if(!empty($content['question_'.$slot]))<details><summary>{{ $content['question_'.$slot] }}</summary><p>{{ $content['answer_'.$slot] ?? '' }}</p></details>@endif@endforeach</section>@break
    @case('tabs')<section class="public-page-tabs"><h2>{{ $content['title'] ?? '' }}</h2><div>@foreach(['one', 'two', 'three'] as $slot)@if(!empty($content['tab_'.$slot.'_label']))<details @if($slot === 'one') open @endif><summary>{{ $content['tab_'.$slot.'_label'] }}</summary><p>{{ $content['tab_'.$slot.'_content'] ?? '' }}</p></details>@endif@endforeach</div></section>@break
    @case('social_links')<section class="public-page-social-links"><h2>{{ $content['title'] ?? 'Stay connected' }}</h2><div>@foreach(['facebook_url' => 'Facebook', 'instagram_url' => 'Instagram', 'youtube_url' => 'YouTube'] as $field => $label)@if(!empty($content[$field]))<a class="button button-secondary" href="{{ $content[$field] }}" target="_blank" rel="noopener noreferrer">{{ $label }}</a>@endif@endforeach</div></section>@break
    @case('contact_form')<section class="public-page-contact-form"><div><h2>{{ $content['title'] ?? 'Get in touch' }}</h2><p>{{ $content['body'] ?? '' }}</p></div>@if(request()->query('contact') === 'received')<p class="public-page-form-success" role="status">Thank you. Your request has been received.</p>@endif<form method="POST" action="{{ route('public.contact.submit') }}">@csrf<label>First name<input name="first_name" required maxlength="100" autocomplete="given-name"></label><label>Last name<input name="last_name" maxlength="100" autocomplete="family-name"></label><label>Email<input name="email" type="email" maxlength="254" autocomplete="email"></label><label>Phone<input name="phone" maxlength="40" autocomplete="tel"></label><label class="public-contact-message">How can we help?<textarea name="message" rows="5" maxlength="2000" placeholder="Share your question or request"></textarea></label><label class="public-page-consent"><input name="consent_to_contact" type="checkbox" value="1" required>I agree that the ministry may contact me about this request.</label><button class="button" type="submit">{{ $content['submit_label'] ?? 'Send request' }}</button></form></section>@break
    @case('map_directions')<section class="public-page-map-directions"><x-ui.icon name="location" :size="28"/><div><h2>{{ $content['title'] ?? 'Find us' }}</h2><p>{!! nl2br(e($content['address'] ?? '')) !!}</p>@if(!empty($content['directions_url']))<a class="button" href="{{ $content['directions_url'] }}" target="_blank" rel="noopener noreferrer">{{ $content['button_label'] ?? 'Get directions' }}</a>@endif</div></section>@break
    @case('spacer')<div class="public-page-spacer public-page-spacer-{{ $content['height'] ?? 'medium' }}" aria-hidden="true"></div>@break
    @case('quote')<blockquote class="public-page-quote">“{{ $content['text'] ?? '' }}”@if(!empty($content['attribution']))<small>{{ $content['attribution'] }}</small>@endif</blockquote>@break
    @case('divider')<hr class="public-page-divider">@break
    @case('call_to_action')<section class="public-page-callout public-page-cta"><h2>{{ $content['title'] ?? '' }}</h2><p>{{ $content['body'] ?? '' }}</p>@if(!empty($content['button_label']) && !empty($content['button_url']))<a class="button" href="{{ $content['button_url'] }}">{{ $content['button_label'] }}</a>@endif</section>@break
    @case('service_times')<section class="public-page-callout public-page-dynamic-list"><small>SERVICE TIMES</small><h2>{{ $content['title'] ?? 'Join us this week' }}</h2><p>{{ $content['body'] ?? '' }}</p>@foreach(($pageElementData['services'] ?? []) as $service)<article><strong>{{ $service->title }}</strong><x-ui.viewer-time :instant="$service->starts_at" :source-timezone="$service->timezone ?: config('app.timezone')" format="compact"/>@if($service->location_name)<span>{{ $service->location_name }}</span>@endif</article>@endforeach<a href="{{ route('public.service-times') }}">View service times</a></section>@break
    @case('events')<section class="public-page-callout public-page-dynamic-list"><small>EVENTS</small><h2>{{ $content['title'] ?? 'What is happening next' }}</h2><p>{{ $content['body'] ?? '' }}</p>@foreach(($pageElementData['events'] ?? []) as $event)<article><strong>{{ $event->title }}</strong><span>{{ \Illuminate\Support\Carbon::parse($event->starts_at)->format('D, M j · g:i A') }}@if($event->location) · {{ $event->location }}@endif</span></article>@endforeach<a href="{{ route('public.events') }}">Explore events</a></section>@break
    @case('departments')<section class="public-page-callout public-page-dynamic-list"><small>DEPARTMENTS</small><h2>{{ $content['title'] ?? 'Find your place' }}</h2><p>{{ $content['body'] ?? '' }}</p>@foreach(($pageElementData['departments'] ?? []) as $department)<article><strong>{{ $department->name }}</strong>@if($department->description)<span>{{ str($department->description)->limit(140) }}</span>@endif</article>@endforeach<a href="{{ route('public.departments') }}">Explore departments</a></section> @break
    @case('locations')<section class="public-page-callout public-page-module-locations public-page-dynamic-list"><small>LOCATIONS</small><h2>{{ $content['title'] ?? 'Find us' }}</h2><p>{{ $content['body'] ?? '' }}</p>@foreach(($pageElementData['locations'] ?? []) as $location)<article><strong>{{ $location->name }}</strong>@if($location->address)<span>{{ $location->address }}</span>@endif</article>@endforeach@if(!empty($content['button_label']) && !empty($content['button_url']))<a class="button" href="{{ $content['button_url'] }}">{{ $content['button_label'] }}</a>@else<a href="{{ route('public.locations') }}">View locations</a>@endif</section>@break
    @case('prayer')<section class="public-page-callout public-page-module-prayer"><small>PRAYER</small><h2>{{ $content['title'] ?? 'How can we pray for you?' }}</h2><p>{{ $content['body'] ?? '' }}</p>@if(!empty($content['button_label']) && !empty($content['button_url']))<a class="button" href="{{ $content['button_url'] }}">{{ $content['button_label'] }}</a>@endif</section>@break
    @case('announcements')<section class="public-page-callout public-page-module-announcements public-page-dynamic-list"><small>ANNOUNCEMENTS</small><h2>{{ $content['title'] ?? 'Latest announcements' }}</h2><p>{{ $content['body'] ?? '' }}</p>@foreach(($pageElementData['announcements'] ?? []) as $announcement)<article><strong>{{ $announcement->title }}</strong><span>{{ str($announcement->body)->limit(180) }}</span></article>@endforeach</section>@break
    @case('next_steps')<section class="public-page-callout public-page-module-next-steps"><small>NEXT STEPS</small><h2>{{ $content['title'] ?? 'Take your next step' }}</h2><p>{{ $content['body'] ?? '' }}</p></section>@break
    @case('newsletter')<section class="public-page-callout public-page-module-newsletter"><small>STAY CONNECTED</small><h2>{{ $content['title'] ?? 'Stay connected' }}</h2><p>{{ $content['body'] ?? '' }}</p></section>@break
    @case('carousel')@php
        $carouselSlides = collect(['one', 'two', 'three'])->map(fn ($number) => ['type' => $content["slide_{$number}_type"] ?? 'flyer', 'presentation' => $content["slide_{$number}_presentation"] ?? 'editorial', 'media_url' => $content["slide_{$number}_media_url"] ?? '', 'title' => $content["slide_{$number}_title"] ?? '', 'body' => $content["slide_{$number}_body"] ?? '', 'button_label' => $content["slide_{$number}_button_label"] ?? '', 'button_url' => $content["slide_{$number}_button_url"] ?? ''])->filter(fn ($slide) => $slide['title'] !== '' || $slide['body'] !== '' || $slide['media_url'] !== '')->values();
        if ($carouselSlides->isEmpty()) $carouselSlides = collect(array_slice(array_values(array_filter(preg_split('/\R/', (string) ($content['slides'] ?? '')) ?: [])), 0, 8))->map(fn ($slide) => ['type' => 'flyer', 'presentation' => 'editorial', 'media_url' => '', 'title' => trim($slide), 'body' => '', 'button_label' => '', 'button_url' => '']);
    @endphp
    <section class="public-page-carousel" data-public-carousel>
        @if(filled($content['eyebrow'] ?? null) || filled($content['title'] ?? null) || filled($content['body'] ?? null))
            <div class="public-page-carousel-intro">
                @if(filled($content['eyebrow'] ?? null))<small>{{ $content['eyebrow'] }}</small>@endif
                @if(filled($content['title'] ?? null))<h2>{{ $content['title'] }}</h2>@endif
                @if(filled($content['body'] ?? null))<p>{{ $content['body'] }}</p>@endif
            </div>
        @endif
        @if($carouselSlides->isNotEmpty())
            <div class="public-page-carousel-stage" aria-roledescription="carousel" aria-label="Featured highlights">
                @foreach($carouselSlides as $index => $slide)
                    @php
                        $slideType = (string) ($slide['type'] ?? 'flyer');
                        $slideVideoUrl = (string) ($slide['media_url'] ?? '');
                        $isFlyerPresentation = ($slide['presentation'] ?? 'editorial') === 'flyer' && $slideType === 'flyer';
                        $hasSlideCopy = filled($slide['title'] ?? null) || filled($slide['body'] ?? null) || (filled($slide['button_label'] ?? null) && filled($slide['button_url'] ?? null));
                        $isVideoLink = $slideType === 'video_link';
                        $isVideoUpload = in_array($slideType, ['video_upload', 'video'], true);
                        $youtubeId = $isVideoLink && preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/))([A-Za-z0-9_-]{6,})~', $slideVideoUrl, $match) ? $match[1] : null;
                        $vimeoId = $isVideoLink && preg_match('~vimeo\.com/(?:video/)?([0-9]+)~', $slideVideoUrl, $match) ? $match[1] : null;
                    @endphp
                    <article class="public-page-carousel-slide {{ $isFlyerPresentation ? 'public-page-carousel-slide--flyer' : 'public-page-carousel-slide--editorial' }} {{ $isFlyerPresentation && !$hasSlideCopy ? 'public-page-carousel-slide--flyer-only' : '' }}" data-carousel-slide data-slide-presentation="{{ $isFlyerPresentation ? 'flyer' : 'editorial' }}" @if($index > 0) hidden aria-hidden="true" @endif>
                        <div class="public-page-carousel-media">
                            @if($isVideoLink && $youtubeId)
                                <iframe src="https://www.youtube-nocookie.com/embed/{{ $youtubeId }}?autoplay=1&amp;mute=1&amp;playsinline=1&amp;rel=0" title="{{ $slide['title'] ?: 'Featured video' }}" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
                            @elseif($isVideoLink && $vimeoId)
                                <iframe src="https://player.vimeo.com/video/{{ $vimeoId }}?autoplay=1&amp;muted=1&amp;playsinline=1&amp;dnt=1" title="{{ $slide['title'] ?: 'Featured video' }}" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>
                            @elseif($isVideoUpload && $slide['media_url'])
                                <video controls preload="metadata" src="{{ $slide['media_url'] }}"></video>
                            @elseif($slide['media_url'])
                                <img src="{{ $slide['media_url'] }}" alt="{{ $isFlyerPresentation ? ($slide['title'] ?: 'Program flyer') : '' }}" loading="{{ $index === 0 ? 'eager' : 'lazy' }}" decoding="async">
                            @else
                                <div class="public-page-carousel-placeholder" aria-hidden="true"><span>{{ $isVideoLink || $isVideoUpload ? 'VIDEO' : 'FEATURED' }}</span></div>
                            @endif
                        </div>
                        @if($hasSlideCopy)<div class="public-page-carousel-overlay">
                            <div>@if($isVideoLink || $isVideoUpload)<small>WATCH</small>@elseif($isFlyerPresentation)<small>UPCOMING PROGRAM</small>@endif @if($slide['title'])<h3>{{ $slide['title'] }}</h3>@endif @if($slide['body'])<p>{{ $slide['body'] }}</p>@endif</div>
                            @if($slide['button_label'] && $slide['button_url'])<a class="button" href="{{ $slide['button_url'] }}">{{ $slide['button_label'] }}</a>@endif
                        </div>@endif
                    </article>
                @endforeach
            </div>
            @if($carouselSlides->count() > 1)<div class="public-page-carousel-controls"><button type="button" data-carousel-previous aria-label="Previous featured slide"><x-ui.icon class="carousel-arrow-icon" name="chevron-left" :size="28"/></button><span data-carousel-status aria-live="polite">1 of {{ $carouselSlides->count() }}</span><button type="button" data-carousel-next aria-label="Next featured slide"><x-ui.icon class="carousel-arrow-icon" name="chevron-right" :size="28"/></button></div>@endif
        @endif
    </section>
    @break
    @case('live_service')@if(in_array('digital_church', $features ?? [], true))@php $liveService = $pageElementData['liveService'] ?? null; @endphp<section class="public-page-callout"><small>{{ $liveService?->state === 'live' ? 'LIVE NOW' : 'LIVE SERVICE' }}</small><h2>{{ $liveService?->title ?: ($content['title'] ?? 'Join us online') }}</h2><p>{{ $content['body'] ?? '' }}</p>@if($liveService)<a class="button" href="{{ route('public.worship', $liveService->id) }}">Join worship</a>@elseif(!empty($content['button_label']) && !empty($content['button_url']))<a class="button" href="{{ $content['button_url'] }}">{{ $content['button_label'] }}</a>@endif</section>@endif @break
    @case('giving')@if(in_array('giving', $features ?? [], true))<section class="public-page-callout public-page-dynamic-list"><small>GIVING</small><h2>{{ $content['title'] ?? 'Support the ministry' }}</h2><p>{{ $content['body'] ?? '' }}</p>@foreach(($pageElementData['funds'] ?? []) as $fund)<article><strong>{{ $fund->name }}</strong>@if($fund->description)<span>{{ str($fund->description)->limit(140) }}</span>@endif</article>@endforeach@if(!empty($content['button_label']) && !empty($content['button_url']))<a class="button" href="{{ $content['button_url'] }}">{{ $content['button_label'] }}</a>@else<a class="button" href="{{ route('public.giving') }}">Give securely</a>@endif</section>@endif @break
    @case('books')@if(in_array('books_library', $features ?? [], true))<section class="public-page-callout public-page-dynamic-list"><small>RESOURCES</small><h2>{{ $content['title'] ?? 'Books and resources' }}</h2><p>{{ $content['body'] ?? '' }}</p>@foreach(($pageElementData['books'] ?? []) as $book)<article><strong>{{ $book->title }}</strong>@if($book->description)<span>{{ str($book->description)->limit(140) }}</span>@endif</article>@endforeach@if(!empty($content['button_label']) && !empty($content['button_url']))<a class="button" href="{{ $content['button_url'] }}">{{ $content['button_label'] }}</a>@else<a href="{{ route('public.library', 'books') }}">Browse resources</a>@endif</section>@endif @break
    @case('sermon_spotlight')@php $sermon = $pageElementData['sermon'] ?? null; @endphp<section class="public-page-callout"><small>FEATURED TEACHING</small><h2>{{ $sermon?->title ?: ($content['title'] ?? 'Featured teaching') }}</h2><p>{{ $sermon?->speaker ?: ($sermon?->description ?: ($content['body'] ?? '')) }}</p>@if(!empty($content['button_label']) && !empty($content['button_url']))<a class="button" href="{{ $content['button_url'] }}">{{ $content['button_label'] }}</a>@elseif($sermon && \App\Support\PublicLibraryRoute::slugFor($sermon->library))<a class="button" href="{{ route('public.library', \App\Support\PublicLibraryRoute::slugFor($sermon->library)) }}">Explore teaching</a>@endif</section>@break
    @case('welcome')<section class="public-page-callout"><small>WELCOME</small><h2>{{ $content['title'] ?? 'There is room for you' }}</h2><p>{{ $content['body'] ?? '' }}</p></section>@break
    @case('custom')<section class="public-page-callout"><small>CONTENT</small><h2>{{ $content['title'] ?? '' }}</h2><p>{{ $content['body'] ?? '' }}</p>@if(!empty($content['button_label']) && !empty($content['button_url']))<a class="button" href="{{ $content['button_url'] }}">{{ $content['button_label'] }}</a>@endif</section>@break
    @default<section class="public-page-callout"><small>{{ strtoupper(str_replace('_', ' ', (string) ($element['type'] ?? 'MINISTRY'))) }}</small><h2>{{ $content['title'] ?? '' }}</h2><p>{{ $content['body'] ?? $content['text'] ?? '' }}</p>@if(!empty($content['button_label']) && !empty($content['button_url']))<a class="button" href="{{ $content['button_url'] }}">{{ $content['button_label'] }}</a>@endif</section>
@endswitch
