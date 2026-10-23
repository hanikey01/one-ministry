<x-layouts.app :title="$title ?? 'Worship'" :ministry-name="$ministryName ?? null" :primary-color="$primaryColor ?? null" :accent-color="$accentColor ?? null">
    <div class="worship-shell" data-worship-shell>
        <header class="worship-header"><a href="/"><span class="worship-brand-mark">{{ mb_strtoupper(mb_substr($ministryName ?? 'M',0,1)) }}</span><strong>{{ $ministryName ?? config('app.name') }}</strong></a><div class="worship-header-actions"><div class="worship-status"><span class="worship-live-dot"></span><span>Service room</span></div><button class="workspace-theme-toggle" type="button" data-worship-theme-toggle aria-pressed="false" aria-label="Switch to dark mode"><span class="workspace-theme-track" aria-hidden="true"></span><span class="workspace-theme-label">Light</span></button></div></header>
        <main class="worship-content">{{ $slot }}</main>
    </div>
</x-layouts.app>
