<x-layouts.public :settings="$settings" :features="$features" title="Give">
    <section class="public-heading">
        <p>GIVING</p>
        <h1>Give with purpose.</h1>
        <span>Choose a ministry fund. Secure provider checkout is configured by your ministry before donations are accepted.</span>
    </section>

    <section class="public-card public-giving-funds">
        <h2>Choose a fund</h2>
        <div class="public-giving-fund-grid">
            @forelse($funds as $fund)
                <article><strong>{{ $fund->name }}</strong><span>{{ $fund->description ?: 'Support this area of ministry.' }}</span><small>Online checkout is being prepared by this ministry.</small></article>
            @empty
                <article><strong>General giving</strong><span>Support the work of the ministry and its community.</span><small>Online checkout is being prepared by this ministry.</small></article>
            @endforelse
        </div>
    </section>

    <style>
        .public-giving-funds{display:grid;gap:18px}.public-giving-funds h2{margin:0}.public-giving-fund-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.public-giving-fund-grid article{display:grid;gap:8px;border:1px solid var(--line);border-radius:14px;padding:18px;background:var(--surface-muted)}.public-giving-fund-grid strong{font-size:.88rem}.public-giving-fund-grid span,.public-giving-fund-grid small{color:var(--muted);font-size:.72rem;line-height:1.55}.public-giving-fund-grid small{color:var(--brand);font-weight:700}@media(max-width:760px){.public-giving-fund-grid{grid-template-columns:1fr}}
    </style>
</x-layouts.public>
