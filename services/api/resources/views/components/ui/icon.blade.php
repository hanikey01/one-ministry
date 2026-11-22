@props(['name', 'size' => 19])
<svg {{ $attributes->merge(['class' => 'ui-icon', 'width' => $size, 'height' => $size, 'viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.8', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round', 'aria-hidden' => 'true']) }}>
    @switch($name)
        @case('home') <path d="M3 11.5 12 4l9 7.5"/><path d="M5.5 10.5V20h13v-9.5M9.5 20v-6h5v6"/> @break
        @case('add') <path d="M12 5v14M5 12h14"/> @break
        @case('people') <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/> @break
        @case('services') <path d="M3 21h18M5 21V10h14v11M3 10h18M12 3l9 7H3l9-7ZM8 14v3M12 14v3M16 14v3"/> @break
        @case('worship') <circle cx="12" cy="12" r="9"/><path d="m10 8 6 4-6 4V8Z"/><path d="M4 5.5v2M20 16.5v2"/> @break
        @case('departments') <circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2"/><path d="M3 20a6 6 0 0 1 12 0M15 15a5 5 0 0 1 6 5"/> @break
        @case('calendar') <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/> @break
        @case('content') <rect x="3" y="4" width="18" height="16" rx="2"/><path d="m8 13 2.5-2.5L15 15l2-2 3 3M8 8h.01"/> @break
        @case('school') <path d="m3 10 9-5 9 5-9 5-9-5Z"/><path d="M7 12.5V17c3 2 7 2 10 0v-4.5M21 10v6"/> @break
        @case('homepage') <rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/> @break
        @case('followup') <path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6M8 12l2.5 2.5L16 9"/> @break
        @case('care') <path d="M12 21s-8-4.8-8-11a4.5 4.5 0 0 1 8-2.8A4.5 4.5 0 0 1 20 10c0 6.2-8 11-8 11Z"/> @break
        @case('message') <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"/><path d="M8 9h8M8 13h5"/> @break
        @case('giving') <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/> @break
        @case('reports') <path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/> @break
        @case('location') <path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/> @break
        @case('modules') <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/> @break
        @case('access') <circle cx="8" cy="8" r="4"/><path d="M2 21a6 6 0 0 1 12 0M16 11l2 2 4-4"/> @break
        @case('security') <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/> @break
        @case('settings') <circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-1.6v-.2h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z"/> @break
        @case('search') <circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/> @break
        @case('bell') <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/> @break
        @case('menu') <path d="M4 7h16M4 12h16M4 17h16"/> @break
        @case('chevron-down') <path d="m6 9 6 6 6-6"/> @break
        @case('close') <path d="m6 6 12 12M18 6 6 18"/> @break
        @case('external') <path d="M15 3h6v6M10 14 21 3M18 13v7a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h7"/> @break
        @case('about') <circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/> @break
        @case('activity') <path d="M3 12h4l3-8 4 16 3-8h4"/> @break
        @case('announcement') <path d="M3 11v2a2 2 0 0 0 2 2h2l9 5V4l-9 5H5a2 2 0 0 0-2 2ZM7 15l2 6h4"/><path d="M19 9a4 4 0 0 1 0 6"/> @break
        @case('archive') <path d="M3 4h18v4H3zM5 8v12h14V8M10 12h4"/> @break
        @case('arrow-right') <path d="M5 12h14M13 6l6 6-6 6"/> @break
        @case('audio') <path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/> @break
        @case('book') <path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v18H6.5A2.5 2.5 0 0 1 4 17.5zM4 17a2 2 0 0 1 2-2h14M8 6h8"/> @break
        @case('book-open') <path d="M12 7v14M3 5a14 14 0 0 1 9 3 14 14 0 0 1 9-3v13a14 14 0 0 0-9 3 14 14 0 0 0-9-3z"/> @break
        @case('briefcase') <rect x="3" y="7" width="18" height="14" rx="2"/><path d="M8 7V4h8v3M3 12h18M10 12v2h4v-2"/> @break
        @case('building') <path d="M4 21V4l8-2v19M12 8h8v13M2 21h20M7 7v.01M7 11v.01M7 15v.01M16 12v.01M16 16v.01"/> @break
        @case('camera') <path d="M4 7h3l2-3h6l2 3h3a2 2 0 0 1 2 2v10H2V9a2 2 0 0 1 2-2Z"/><circle cx="12" cy="13" r="3"/> @break
        @case('chat') <path d="M21 11.5a8.5 8.5 0 0 1-12.7 7.4L3 21l1.7-5.3A8.5 8.5 0 1 1 21 11.5Z"/> @break
        @case('check') <path d="m5 12 4 4L19 6"/> @break
        @case('clock') <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/> @break
        @case('coffee') <path d="M4 9h13v7a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4zM17 11h2a2 2 0 1 1 0 4h-2M7 3v3M12 3v3"/> @break
        @case('compass') <circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5z"/> @break
        @case('download') <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/> @break
        @case('edit') <path d="m4 16-.7 4.7L8 20l10.8-10.8a2.1 2.1 0 0 0-3-3Z"/><path d="m14.5 7.5 2 2"/> @break
        @case('trash') <path d="M5 7h14M10 11v6M14 11v6M9 7V5h6v2M7 7l1 13h8l1-13"/> @break
        @case('mail')
        @case('email') <rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/> @break
        @case('file') <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M8 13h8M8 17h8"/> @break
        @case('flag') <path d="M5 21V4M5 4c6-4 8 4 14 0v11c-6 4-8-4-14 0"/> @break
        @case('globe') <circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/> @break
        @case('hand-heart') <path d="M12 21s-7-4.2-7-9a4 4 0 0 1 7-2.6A4 4 0 0 1 19 12c0 4.8-7 9-7 9Z"/><path d="M2 16h4l2 2h5l3-3h3a2 2 0 0 1 2 2l-5 4H8z"/> @break
        @case('heart') <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.8-7.5 1.1-1.1a5.5 5.5 0 0 0-.1-7.8Z"/> @break
        @case('help') <circle cx="12" cy="12" r="9"/><path d="M9.6 9a2.5 2.5 0 1 1 4.7 1.2c-.8 1.1-2.3 1.4-2.3 3M12 17h.01"/> @break
        @case('image') <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/> @break
        @case('info') <circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/> @break
        @case('link') <path d="M10 13a5 5 0 0 0 7.1 0l3-3A5 5 0 0 0 13 2.9l-1.7 1.7M14 11a5 5 0 0 0-7.1 0l-3 3A5 5 0 0 0 11 21.1l1.7-1.7"/> @break
        @case('list') <path d="M9 6h12M9 12h12M9 18h12M4 6h.01M4 12h.01M4 18h.01"/> @break
        @case('lock') <rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/> @break
        @case('map') <path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3zM9 3v15M15 6v15"/> @break
        @case('music') <path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/> @break
        @case('news') <rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 8h5v4H7zM15 8h3M15 12h3M7 16h11"/> @break
        @case('phone') <path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.4 19.4 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7l.5 3a2 2 0 0 1-.6 1.7L7.7 9.7a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 1.7-.6l3 .5a2 2 0 0 1 1.3 2.6Z"/> @break
        @case('pin') <path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/> @break
        @case('play') <circle cx="12" cy="12" r="9"/><path d="m10 8 6 4-6 4z"/> @break
        @case('podcast') <circle cx="12" cy="11" r="2"/><path d="M8.5 15a5 5 0 1 1 7 0L14 21h-4zM5 8a8 8 0 0 1 0 6M19 8a8 8 0 0 0 0 6"/> @break
        @case('prayer') <path d="M12 22v-8M8 8l4-5 4 5M4 13l4-5M20 13l-4-5M5 20h14"/> @break
        @case('send') <path d="m22 2-7 20-4-9-9-4zM22 2 11 13"/> @break
        @case('share') <circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 10.5 6.8-4M8.6 13.5l6.8 4"/> @break
        @case('sparkles') <path d="m12 3 1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8zM19 16l1 2.5 2.5 1-2.5 1L19 23l-1-2.5-2.5-1 2.5-1z"/> @break
        @case('star') <path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9z"/> @break
        @case('support') <path d="M4 13v-2a8 8 0 0 1 16 0v2M4 13H3a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h3v-7zM20 13h1a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-3v-7zM17 20a5 5 0 0 1-5 2"/> @break
        @case('video') <rect x="3" y="5" width="13" height="14" rx="2"/><path d="m16 10 5-3v10l-5-3z"/> @break
        @case('wifi') <path d="M5 12.5a11 11 0 0 1 14 0M8.5 16a5.5 5.5 0 0 1 7 0M12 20h.01"/> @break
        @default <circle cx="12" cy="12" r="9"/><path d="M12 8v4l2 2"/>
    @endswitch
</svg>
