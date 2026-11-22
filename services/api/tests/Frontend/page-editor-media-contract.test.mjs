import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const editorSource = fs.readFileSync(new URL('../../resources/views/staff/pages/partials/structured-editor-script.blade.php', import.meta.url), 'utf8');
const rendererSource = fs.readFileSync(new URL('../../resources/views/public/partials/page-element.blade.php', import.meta.url), 'utf8');
const stylesheetSource = fs.readFileSync(new URL('../../resources/css/app.css', import.meta.url), 'utf8');

test('AI canvas media accepts explicit internal and HTTP(S) URLs', () => {
    assert.match(editorSource, /\^https\?:\\\/\\\//);
    assert.ok(editorSource.includes('/^\\/(?!\\/)/'));
    assert.match(editorSource, /data-canvas-media-url/);
    assert.match(editorSource, /Use an internal path beginning with \/ or a complete HTTP or HTTPS URL/);
    assert.match(editorSource, /data-canvas-media-style/);
    assert.match(editorSource, /Full image/);
    assert.match(editorSource, /No repeat/);
    assert.match(editorSource, /Bottom left/);
});

test('stale media markers fail visibly and do not silently close the picker', () => {
    assert.match(editorSource, /This media position is no longer present in the section/);
    assert.match(editorSource, /The selected page element changed/);
    assert.match(editorSource, /catch \(error\)[\s\S]*The selected media could not be applied/);
    assert.match(editorSource, /snapshot\(\);[\s\S]*item\.content\.html = replacementHtml/);
});

test('sandbox width protection does not override the authored root display mode', () => {
    assert.match(rendererSource, /body>:not\(script\)\{width:100%!important/);
    assert.doesNotMatch(rendererSource, /body>:not\(script\)\{display:block!important/);
    assert.match(rendererSource, /inline-size:100%!important/);
    assert.match(rendererSource, /flex:1 1 100%!important/);
});

test('AI canvas editor column wrapper stretches across its grid track', () => {
    assert.match(
        stylesheetSource,
        /\.public-page-structured-columns\s*>\s*\[data-page-editor-object="column"\]:has\(\.ai-canvas-design\)\s*\{[^}]*width:100%;[^}]*min-width:0;[^}]*justify-self:stretch;/,
    );
});

test('AI canvas keeps authored colors and renders model links as inert placeholders', () => {
    const appSource = fs.readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
    assert.match(rendererSource, /data-ai-canvas-design/);
    assert.match(rendererSource, /AiCanvasSectionService::neutraliseLinks/);
    assert.match(appSource, /frame\.matches\('\[data-ai-canvas-design\]'\)/);
});
