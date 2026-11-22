import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const css = readFileSync(new URL('../../resources/css/app.css', import.meta.url), 'utf8');
const content = readFileSync(new URL('../../resources/views/staff/content.blade.php', import.meta.url), 'utf8');

test('staff archive command actions remain compact beside their headings', () => {
    assert.match(css, /header\.workspace-page-heading\.staff-page-command\{[^}]+display:flex;[^}]+justify-content:space-between/);
    assert.match(css, /header\.workspace-page-heading\.staff-page-command>\.button,[\s\S]+?width:max-content;[\s\S]+?white-space:nowrap/);
    assert.match(css, /@media\(max-width:760px\)[\s\S]+?header\.workspace-page-heading\.staff-page-command\{[\s\S]+?flex-direction:column;[\s\S]+?gap:16px/);
});

test('content library has explicit navigation, tools, and record surfaces', () => {
    assert.match(content, /staff-page-command content-library-heading/);
    assert.match(content, /class="table-toolbar content-library-tabs"/);
    assert.match(content, /class="staff-page-command content-library-tools"/);
    assert.match(content, /class="content-library-records"/);
    assert.match(content, /number_format\(\$items->total\(\)\)/);
    assert.match(css, /body \.content-library-tabs\{[^}]+grid-template-columns:repeat\(4,minmax\(0,1fr\)\)/);
    assert.match(css, /body \.content-library-tools\{[^}]+grid-template-columns:minmax\(0,1\.35fr\) minmax\(280px,\.65fr\)/);
    assert.match(css, /\.content-library-records\{[^}]+border:1px solid var\(--line\)/);
});
