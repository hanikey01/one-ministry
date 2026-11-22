import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const read = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');
const component = read('../../resources/views/components/ui/timezone-select.blade.php');
const timezoneViews = [
    '../../resources/views/setup/wizard.blade.php',
    '../../resources/views/staff/settings.blade.php',
    '../../resources/views/staff/location-fields.blade.php',
    '../../resources/views/staff/services.blade.php',
    '../../resources/views/staff/school/show.blade.php',
].map(read);

test('all editable timezone fields use the shared searchable catalog component', () => {
    for (const view of timezoneViews) {
        assert.match(view, /<x-ui\.timezone-select/);
        assert.doesNotMatch(view, /timezone_identifiers_list\(|<input[^>]+name="timezone"/);
    }
});

test('timezone component uses the common grouped catalog, selected value, and search metadata', () => {
    assert.match(component, /TimezoneCatalog::groups\(\)/);
    assert.match(component, /old\(\$name, \$selected\)/);
    assert.match(component, /data-searchable-select/);
    assert.match(component, /<optgroup label="\{\{ \$group \}\}">/);
    assert.match(component, /data-search-text="\{\{ \$timezone\['search'\] \}\}"/);
});
