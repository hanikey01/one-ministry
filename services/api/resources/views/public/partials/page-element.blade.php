@php($content = $element['content'] ?? [])
@switch($element['type'] ?? '')
    @case('heading')<h2>{{ $content['text'] ?? '' }}</h2>@break
    @case('paragraph')<p>{!! nl2br(e($content['text'] ?? '')) !!}</p>@break
    @case('image')<figure class="public-page-image">@if(!empty($content['url']))<img src="{{ $content['url'] }}" alt="{{ $content['alt'] ?? '' }}">@endif@if(!empty($content['caption']))<figcaption>{{ $content['caption'] }}</figcaption>@endif</figure>@break
    @case('quote')<blockquote class="public-page-quote">“{{ $content['text'] ?? '' }}”@if(!empty($content['attribution']))<small>{{ $content['attribution'] }}</small>@endif</blockquote>@break
    @case('divider')<hr class="public-page-divider">@break
    @case('spacer')<div class="public-page-spacer public-page-spacer-{{ $content['height'] ?? 'medium' }}" aria-hidden="true"></div>@break
    @case('call_to_action')<section class="public-page-callout"><h2>{{ $content['title'] ?? '' }}</h2><p>{{ $content['body'] ?? '' }}</p>@if(!empty($content['button_label']) && !empty($content['button_url']))<a class="button" href="{{ $content['button_url'] }}">{{ $content['button_label'] }}</a>@endif</section>@break
    @default<article class="public-page-callout"><h2>{{ $content['title'] ?? '' }}</h2><p>{{ $content['body'] ?? $content['text'] ?? '' }}</p></article>
@endswitch
