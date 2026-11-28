import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';

const editor = fs.readFileSync(new URL('../../resources/views/staff/pages/partials/structured-editor-script.blade.php', import.meta.url), 'utf8');
const form = fs.readFileSync(new URL('../../resources/views/staff/pages/form.blade.php', import.meta.url), 'utf8');
const styles = fs.readFileSync(new URL('../../resources/css/app.css', import.meta.url), 'utf8');

test('duplicating a page object regenerates every copied descendant id', () => {
    assert.match(editor, /copyElementWithFreshIds\s*=\s*\(item\)\s*=>\s*\(\{\s*\.\.\.clone\(item\),\s*id:id\(\)/);
    assert.match(editor, /copy\.elements\s*=\s*\(copy\.elements\s*\|\|\s*\[\]\)\.map\(copyElementWithFreshIds\)/);
    assert.match(editor, /copy\.columns\s*=\s*\(copy\.columns\s*\|\|\s*\[\]\)\.map\(copyColumnWithFreshIds\)/);
    assert.match(editor, /copySectionWithFreshIds\(currentSection\(\)\)/);
    assert.match(editor, /copyColumnWithFreshIds\(currentColumn\(\)\)/);
    assert.match(editor, /copyElementWithFreshIds\(currentElement\(\)\)/);
});

test('the floating navigator supports bounded hierarchy-aware drag and drop', () => {
    assert.match(editor, /draggable="true" class="page-editor-floating-navigator-node/);
    assert.match(editor, /canDropNavigatorObject/);
    assert.match(editor, /moveNavigatorObject/);
    assert.match(editor, /addEventListener\('dragstart'/);
    assert.match(editor, /addEventListener\('dragover'/);
    assert.match(editor, /addEventListener\('drop'/);
    assert.match(editor, /source\.section !== targetSection && documentState\.sections\[source\.section\]\?\.columns\.length <= 1/);
    assert.match(editor, /targetColumn\?\.elements\.length \|\| 0\) >= 60/);
    assert.match(editor, /Alt\+ArrowUp Alt\+ArrowDown/);
    assert.match(form, /Select or drag sections, columns, and elements to rearrange them/);
    assert.match(styles, /button\.is-drop-inside/);
});
