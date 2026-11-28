import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';

const renderer = fs.readFileSync(new URL('../../resources/views/public/partials/page-element.blade.php', import.meta.url), 'utf8');
const publicHome = fs.readFileSync(new URL('../../resources/views/public/home.blade.php', import.meta.url), 'utf8');
const script = fs.readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
const styles = fs.readFileSync(new URL('../../resources/css/app.css', import.meta.url), 'utf8');
const starter = fs.readFileSync(new URL('../../app/Services/CrusadeArchiveStarterService.php', import.meta.url), 'utf8');

test('crusade gallery carousel remains bounded and centered at every breakpoint', () => {
    assert.match(styles, /\.public-page-gallery-carousel \{[^}]*box-sizing: border-box;[^}]*width: 100%;[^}]*max-width: 100%;[^}]*min-width: 0;/);
    assert.match(styles, /\.public-page-gallery-carousel-stage \{[^}]*width: 100%;[^}]*max-width: 100%;[^}]*min-width: 0;[^}]*min-height: 0;[^}]*aspect-ratio: 16 \/ 9;/);
    assert.match(styles, /@media \(max-width: 760px\) \{ \.public-page-gallery-carousel-stage \{ aspect-ratio: 4 \/ 3;/);
    assert.doesNotMatch(styles, /public-page-gallery-carousel-stage \{[^}]*min-height: clamp/);
    assert.match(starter, /public-page-gallery-carousel-stage \{ width: 100%; max-width: 100%; min-width: 0; min-height: 0;/);
});

test('gallery images open an accessible full-screen viewer', () => {
    assert.match(renderer, /data-gallery-lightbox-open/);
    assert.match(renderer, /data-gallery-lightbox-image/);
    assert.match(renderer, /data-gallery-lightbox-close/);
    assert.match(renderer, /aria-label="Open image \{\{ \$index \+ 1 \}\} of \{\{ count\(\$galleryImages\) \}\} full screen"/);
    assert.match(script, /initializeGalleryLightboxes/);
    assert.match(script, /closest\('\[data-carousel-previous\], \[data-carousel-next\]'\)/);
    assert.match(script, /closest\('\[data-carousel-slide\] img'\)/);
    assert.match(script, /slide\.style\.display = selected \? 'block' : 'none'/);
    assert.match(script, /show\(0\)/);
    assert.match(script, /dialog\.showModal\(\)/);
    assert.match(script, /event\.key === 'ArrowLeft'/);
    assert.match(script, /event\.key === 'ArrowRight'/);
    assert.match(script, /event\.key === 'Escape'/);
    assert.match(script, /public-page-gallery-lightbox-shell, \.public-page-gallery-lightbox-stage/);
    assert.match(script, /returnFocus\?\.focus\(\)/);
    assert.match(styles, /\.public-page-gallery-lightbox \{[^}]*width: 100%;[^}]*height: 100%;/);
    assert.match(styles, /\.public-page-gallery-lightbox-stage img \{[^}]*object-fit: contain;/);
    assert.match(styles, /\.public-page-gallery-carousel-stage \{[^}]*touch-action: pan-y;[^}]*overscroll-behavior-x: contain;/);
    assert.match(styles, /\.carousel-arrow-icon \{[^}]*width: 28px;[^}]*height: 28px;[^}]*stroke-width: 2\.4;/);
    assert.match(renderer, /name="chevron-left"/);
    assert.match(renderer, /name="chevron-right"/);
    assert.match(publicHome, /data-carousel-previous[^>]*>[\s\S]*?name="chevron-left"/);
    assert.match(publicHome, /data-carousel-next[^>]*>[\s\S]*?name="chevron-right"/);
});
