@props(['code', 'title', 'message', 'actionLabel' => 'Return home', 'actionUrl' => '/'])
@php
    try {
        $themeSettings = \App\Models\MinistrySetting::find(\App\Models\MinistrySetting::SINGLETON_ID);
    } catch (\Throwable $exception) {
        // Error pages must still render when the database itself is unavailable.
        $themeSettings = null;
    }

    $ministryName = trim((string) ($themeSettings?->name ?? '')) ?: config('app.name');
    $lightLogoPath = $themeSettings?->logo_path;
    $darkLogoPath = $themeSettings?->dark_logo_path;
    $faviconPath = $themeSettings?->favicon_path;
    $theme = \App\Support\MinistryTheme::publicTheme($themeSettings);
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" media="(prefers-color-scheme: light)" content="{{ $theme['light']['canvas'] }}">
    <meta name="theme-color" media="(prefers-color-scheme: dark)" content="{{ $theme['dark']['canvas'] }}">
    @if($faviconPath)<link rel="icon" href="{{ asset('storage/'.$faviconPath) }}">@endif
    <title>{{ $code }} · {{ $ministryName }}</title>
    <style>
        :root{color-scheme:light;font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif}
        *{box-sizing:border-box}
        body{--canvas:var(--theme-light-canvas-color);--surface:var(--theme-light-surface-color);--surface-ink:var(--theme-light-surface-ink);--ink:var(--theme-light-ink);--line:color-mix(in srgb,var(--ink) 14%,var(--surface));--brand-text:var(--theme-brand-text-light);display:grid;min-height:100vh;margin:0;place-items:center;background:radial-gradient(circle at 12% 8%,color-mix(in srgb,var(--accent) 12%,var(--canvas)) 0,transparent 33%),var(--canvas);color:var(--ink)}
        .error-shell{display:grid;width:100%;min-height:100vh;place-items:center;padding:28px}
        .error-card{position:relative;overflow:hidden;width:min(100%,660px);border:1px solid var(--line);border-radius:26px;padding:clamp(34px,7vw,64px);background:var(--surface);color:var(--surface-ink);box-shadow:0 26px 80px color-mix(in srgb,var(--ink) 10%,transparent)}
        .error-card:before{position:absolute;top:-115px;right:-115px;width:250px;height:250px;border-radius:50%;background:radial-gradient(circle,color-mix(in srgb,var(--accent) 20%,var(--surface)),transparent 67%);content:""}
        .error-brand{position:relative;display:flex;min-width:0;align-items:center;gap:11px;color:var(--muted);font-size:.76rem;font-weight:750;letter-spacing:.01em}
        .error-brand-mark{display:grid;width:42px;height:42px;flex:0 0 42px;place-items:center;overflow:hidden;border:1px solid var(--line);border-radius:12px;background:var(--surface);color:var(--brand);font-family:Georgia,serif;font-size:1.1rem;font-style:normal}
        .error-brand-mark img{display:block;width:100%;height:100%;object-fit:contain;padding:3px}
        .error-logo-dark{display:none!important}
        .error-code{position:relative;margin:47px 0 9px;color:var(--brand-text);font-size:.66rem;font-weight:850;letter-spacing:.14em}
        .error-card h1{position:relative;max-width:480px;margin:0;color:var(--surface-ink);font-size:clamp(2.1rem,7vw,3.8rem);letter-spacing:-.065em;line-height:1.03}
        .error-card p:not(.error-code){position:relative;max-width:460px;margin:17px 0 0;color:var(--muted);font-size:.9rem;line-height:1.65}
        .error-actions{position:relative;display:flex;flex-wrap:wrap;gap:12px;margin-top:32px}
        .error-actions a{display:inline-flex;min-height:43px;align-items:center;border:1px solid var(--accent);border-radius:11px;padding:0 15px;background:var(--accent);color:var(--accent-foreground);font-size:.75rem;font-weight:760;text-decoration:none;transition:filter .15s ease,transform .15s ease}
        .error-actions a:hover{filter:brightness(.94);transform:translateY(-1px)}
        .error-actions a:focus-visible{outline:3px solid color-mix(in srgb,var(--accent) 42%,transparent);outline-offset:3px}
        .error-actions a+a{border-color:var(--line);background:var(--surface);color:var(--brand-text)}
        .error-mark{position:absolute;right:clamp(20px,5vw,52px);bottom:-49px;color:color-mix(in srgb,var(--ink) 8%,var(--surface));font-family:Georgia,serif;font-size:11rem;font-weight:700;line-height:1;pointer-events:none}
        @media(prefers-color-scheme:dark){
            :root{color-scheme:dark}
            body{--canvas:var(--theme-dark-canvas-color);--surface:var(--theme-dark-surface-color);--surface-ink:var(--theme-dark-surface-ink);--ink:var(--theme-dark-ink);--line:color-mix(in srgb,var(--ink) 16%,var(--surface));--brand-text:var(--theme-brand-text-dark)}
            .error-has-dark-logo .error-logo-light{display:none!important}
            .error-logo-dark{display:block!important}
            .error-brand-initial-has-dark{display:none}
        }
        @media(max-width:480px){.error-shell{padding:16px}.error-card{border-radius:20px;padding:34px 25px}.error-code{margin-top:37px}.error-mark{font-size:8rem}}
        @media(prefers-reduced-motion:reduce){.error-actions a{transition:none}}
    </style>
</head>
<body @if($darkLogoPath) class="error-has-dark-logo" @endif style="{{ \App\Support\MinistryTheme::cssVariables($themeSettings) }};--brand:var(--theme-action-color);--accent:var(--theme-action-color);--brand-foreground:var(--action-foreground);--accent-foreground:var(--action-foreground);--muted:color-mix(in srgb,var(--ink) 62%,var(--canvas))">
    <main class="error-shell">
        <section class="error-card" aria-labelledby="error-title">
            <div class="error-brand">
                <span class="error-brand-mark" aria-hidden="true">
                    @if($lightLogoPath)<img class="error-logo-light" src="{{ asset('storage/'.$lightLogoPath) }}" alt="">@else<i class="error-brand-initial @if($darkLogoPath) error-brand-initial-has-dark @endif">{{ mb_strtoupper(mb_substr($ministryName, 0, 1)) }}</i>@endif
                    @if($darkLogoPath)<img class="error-logo-dark" src="{{ asset('storage/'.$darkLogoPath) }}" alt="">@endif
                </span>
                <span>{{ $ministryName }}</span>
            </div>
            <p class="error-code">ERROR {{ $code }}</p>
            <h1 id="error-title">{{ $title }}</h1>
            <p>{{ $message }}</p>
            <div class="error-actions">
                <a href="{{ $actionUrl }}">{{ $actionLabel }}</a>
                <a href="javascript:history.back()">Go back</a>
            </div>
            <span class="error-mark" aria-hidden="true">{{ $code }}</span>
        </section>
    </main>
</body>
</html>
