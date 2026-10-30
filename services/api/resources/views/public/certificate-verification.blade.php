<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Certificate verification · {{ $settings->name }}</title>
    @vite(['resources/css/app.css'])
    <style>
        .public-certificate-verification{min-height:100vh;margin:0;padding:24px;background:var(--canvas);color:var(--ink);font-family:inherit}.certificate-verification-shell{display:grid;max-width:720px;margin:0 auto;gap:18px;padding-top:min(12vh,96px)}.certificate-verification-brand{margin:0;color:var(--muted);font-size:.72rem;font-weight:850;letter-spacing:.13em;text-align:center;text-transform:uppercase}.certificate-verification-card{border:1px solid var(--line-strong);border-radius:18px;padding:clamp(24px,6vw,54px);background:var(--surface);text-align:center}.certificate-verification-status{display:inline-flex;border:1px solid var(--line-strong);border-radius:999px;padding:6px 10px;background:var(--surface-muted);color:var(--ink);font-size:.65rem;font-weight:850;text-transform:uppercase;letter-spacing:.1em}.certificate-verification-card h1{margin:18px 0 10px;font-size:clamp(1.65rem,5vw,3rem);letter-spacing:-.05em}.certificate-verification-card p{max-width:520px;margin:0 auto;color:var(--muted);line-height:1.7}.certificate-verification-card dl{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin:28px 0 0;text-align:left}.certificate-verification-card dl div{border:1px solid var(--line);border-radius:12px;padding:13px;background:var(--surface-muted)}.certificate-verification-card dt{color:var(--muted);font-size:.62rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em}.certificate-verification-card dd{margin:5px 0 0;color:var(--ink);font-size:.75rem;font-weight:800}.certificate-verification-card.is-valid{border-color:color-mix(in srgb,var(--brand) 44%,var(--line))}.certificate-verification-card.is-invalid{border-color:var(--line-strong)}@media(max-width:540px){.public-certificate-verification{padding:12px}.certificate-verification-card dl{grid-template-columns:1fr}}
    </style>
</head>
<body class="public-certificate-verification">
    <main class="certificate-verification-shell">
        <p class="certificate-verification-brand">{{ $settings->name }}</p>
        @if($certificate && ! $certificate->revoked_at)
            <article class="certificate-verification-card is-valid">
                <span class="certificate-verification-status">Verified certificate</span>
                <h1>Certificate is valid</h1>
                <p>This confirms that <strong>{{ $certificate->recipient_name ?: trim($certificate->first_name.' '.$certificate->last_name) }}</strong> completed <strong>{{ $certificate->course_title ?: $certificate->current_course_title }}</strong>.</p>
                <dl>
                    <div><dt>Issued</dt><dd>{{ \Carbon\Carbon::parse($certificate->issued_at)->format('F j, Y') }}</dd></div>
                    <div><dt>Credential ID</dt><dd>{{ $certificate->certificate_number }}</dd></div>
                </dl>
            </article>
        @elseif($certificate?->revoked_at)
            <article class="certificate-verification-card is-invalid">
                <span class="certificate-verification-status">Credential revoked</span>
                <h1>This certificate is no longer valid</h1>
                <p>This credential was revoked by {{ $settings->name }}. Please contact the ministry if you need help.</p>
            </article>
        @else
            <article class="certificate-verification-card is-invalid">
                <span class="certificate-verification-status">Certificate unavailable</span>
                <h1>We could not verify this certificate</h1>
                <p>Check the verification link or contact {{ $settings->name }} for assistance.</p>
            </article>
        @endif
    </main>
</body>
</html>
