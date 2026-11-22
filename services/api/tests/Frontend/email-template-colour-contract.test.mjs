import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const css = readFileSync(new URL('../../resources/css/app.css', import.meta.url), 'utf8');
const view = readFileSync(new URL('../../resources/views/staff/email.blade.php', import.meta.url), 'utf8');

test('email navigation and selection controls opt out of primary action styling', () => {
    assert.match(css, /button:not\(\.workspace-neutral-control\)[^{]+\{[^}]+background:var\(--brand\)/);
    assert.match(view, /class="workspace-neutral-control email-tab"/);
    assert.match(view, /class="workspace-neutral-control email-template-choice"/);
    assert.match(view, /class="workspace-neutral-control email-placeholder-choice"/);
    assert.doesNotMatch(view, /html body\.ministry-app \.workspace-content \.email-template-list/);
});

test('email controls use the intended contrast-safe foreground and surface tokens', () => {
    assert.match(css, /\.email-tab \{[^}]+background:transparent;[^}]+color:var\(--muted\)/);
    assert.match(css, /\.email-template-choice \{[^}]+background:var\(--brand\);[^}]+color:var\(--action-foreground\)/);
    assert.match(css, /\.email-template-choice strong \{ color:var\(--action-foreground\); \}/);
    assert.match(css, /\.email-template-choice small \{ color:var\(--action-foreground\); opacity:\.82; \}/);
    assert.match(css, /\.email-template-choice \.ui-icon \{ color:var\(--action-foreground\); \}/);
    assert.match(view, /\.email-template-choice strong,\.email-template-choice small,\.email-template-choice \.ui-icon\{color:var\(--action-foreground\)!important\}/);
    assert.doesNotMatch(css, /\.email-template-choice[^}]+(?:#fff|#000)/);
    assert.match(css, /\.email-placeholder-choice \{[^}]+background:var\(--brand\);[^}]+color:var\(--action-foreground\)/);
    assert.match(css, /\.email-placeholder-choice :is\(code,span\) \{ color:var\(--action-foreground\); \}/);
    assert.match(view, /\.email-placeholder-choice :is\(code,span\)\{color:var\(--action-foreground\)!important\}/);
    assert.doesNotMatch(css, /\.email-placeholder-choice[^}]+(?:#fff|#000)/);
});

test('email tabs and template choices expose their selected state', () => {
    assert.match(view, /role="tablist"/);
    assert.equal((view.match(/role="tab"/g) ?? []).length, 3);
    assert.equal((view.match(/role="tabpanel"/g) ?? []).length, 3);
    assert.match(view, /:aria-selected="section === 'templates'"/);
    assert.match(view, /:aria-pressed="activeTemplate ===/);
});
