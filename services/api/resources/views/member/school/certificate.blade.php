<x-layouts.member title="Course certificate" :ministry-name="$settings->name" :features="$features">
    <style>
        .certificate-actions{display:flex;justify-content:center;gap:10px;margin:0 auto 18px}.certificate-actions a,.certificate-actions button{min-height:42px;border:1px solid var(--line-strong);border-radius:12px;padding:0 16px;background:var(--surface);color:var(--ink);font:inherit;font-weight:800;cursor:pointer;text-decoration:none}.certificate{position:relative;overflow:hidden;max-width:980px;margin:auto;border:1px solid var(--line-strong);border-radius:28px;padding:clamp(34px,7vw,82px);background:linear-gradient(145deg,var(--surface),color-mix(in srgb,var(--brand) 7%,var(--surface)));box-shadow:0 24px 70px rgb(15 38 24 / 12%);text-align:center}.certificate:before,.certificate:after{content:'';position:absolute;width:220px;height:220px;border-radius:50%;background:color-mix(in srgb,var(--brand) 12%,transparent)}.certificate:before{left:-110px;top:-110px}.certificate:after{right:-110px;bottom:-110px}.certificate>*{position:relative;z-index:1}.certificate-mark{display:grid;width:76px;height:76px;margin:0 auto 24px;place-items:center;border-radius:24px;background:var(--brand);color:#fff}.certificate-mark svg{width:36px;height:36px}.certificate small{color:var(--brand);font-size:.72rem;font-weight:900;letter-spacing:.18em;text-transform:uppercase}.certificate h1{margin:18px 0 8px;color:var(--ink);font-size:clamp(2rem,6vw,4.6rem);letter-spacing:-.06em}.certificate h2{margin:22px 0;color:var(--ink);font-size:clamp(1.4rem,3vw,2.3rem)}.certificate p{color:var(--muted);line-height:1.75}.certificate-number{display:inline-flex;margin-top:28px;border:1px solid var(--line-strong);border-radius:999px;padding:9px 15px;background:var(--surface-muted);color:var(--ink);font-size:.72rem;font-weight:800}@media print{.member-shell>aside,.member-topbar,.member-mobile-nav,.certificate-actions{display:none!important}.member-shell,.member-main{display:block!important}.member-content{padding:0!important}.certificate{box-shadow:none}}
    </style>
    <div class="certificate-actions"><a href="{{ route('member.school.index') }}">Back to school</a><button type="button" onclick="window.print()">Print certificate</button></div>
    <article class="certificate">
        <div class="certificate-mark"><x-ui.icon name="school"/></div>
        <small>{{ $settings->name }} digital school</small>
        <h1>Certificate of completion</h1>
        <p>This certifies that</p>
        <h2>{{ trim($person->first_name.' '.$person->last_name) }}</h2>
        <p>successfully completed <strong>{{ $certificate->course_title }}</strong><br>on {{ \Carbon\Carbon::parse($certificate->issued_at)->format('F j, Y') }}.</p>
        <span class="certificate-number">Certificate {{ $certificate->certificate_number }}</span>
    </article>
</x-layouts.member>
