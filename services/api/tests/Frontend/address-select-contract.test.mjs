import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const css = readFileSync(new URL('../../resources/css/app.css', import.meta.url), 'utf8');
const js = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
const setup = readFileSync(new URL('../../resources/views/setup/wizard.blade.php', import.meta.url), 'utf8');
const location = readFileSync(new URL('../../resources/views/staff/location-fields.blade.php', import.meta.url), 'utf8');

test('search filtering can actually hide enhanced select choices', () => {
    assert.match(css, /\.enhanced-select-options button\[hidden\]\s*\{\s*display\s*:\s*none\s*!important/);
    assert.match(js, /choice\.hidden\s*=\s*!matches/);
});

test('setup and location editing share dependent country and subdivision selects', () => {
    for (const view of [setup, location]) {
        assert.match(view, /data-address-fields/);
        assert.match(view, /address-catalog\.subdivisions/);
        assert.match(view, /x-ui\.country-select/);
        assert.match(view, /x-ui\.subdivision-select/);
    }
    assert.match(js, /data-address-country/);
    assert.match(js, /data-address-subdivision/);
});
