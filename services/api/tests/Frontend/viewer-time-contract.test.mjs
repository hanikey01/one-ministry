import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');

test('public service-time surfaces use the shared viewer-time contract', () => {
    const component = read('resources/views/components/ui/viewer-time.blade.php');
    const dedicated = read('resources/views/public/service-times.blade.php');
    const homepage = read('resources/views/public/home.blade.php');
    const pageElement = read('resources/views/public/partials/page-element.blade.php');
    const script = read('resources/js/app.js');

    assert.match(component, /datetime="\{\{ \$moment->toIso8601String\(\) \}\}"/);
    assert.match(component, /data-source-timezone/);
    assert.match(dedicated, /<x-ui\.viewer-time/);
    assert.match(homepage, /<x-ui\.viewer-time/);
    assert.match(pageElement, /<x-ui\.viewer-time/);
    assert.match(script, /Intl\.DateTimeFormat\(\)\.resolvedOptions\(\)\.timeZone/);
    assert.match(script, /Ministry time:/);
    assert.match(script, /initializeViewerTimes\(\)/);
});
