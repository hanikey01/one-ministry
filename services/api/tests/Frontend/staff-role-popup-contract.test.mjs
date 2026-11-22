import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const css = readFileSync(new URL('../../resources/css/app.css', import.meta.url), 'utf8');
const view = readFileSync(new URL('../../resources/views/staff/access.blade.php', import.meta.url), 'utf8');
const roles = readFileSync(new URL('../../app/Support/RoleCatalog.php', import.meta.url), 'utf8');

test('staff role editing uses an unclipped workspace dialog', () => {
    assert.match(view, /data-workspace-dialog-open="account-roles-/);
    assert.match(view, /<dialog class="workspace-dialog staff-role-dialog"/);
    assert.match(view, /class="staff-role-dialog-options"/);
    assert.doesNotMatch(view, /data-role-multiselect|<details class="staff-role-multiselect"/);
    assert.doesNotMatch(css, /\.staff-role-multiselect/);
});

test('role popup presents account context and explicit save and cancel actions', () => {
    assert.match(view, /Manage roles for \{\{ \$account->name \}\}/);
    assert.match(view, /Clearing a selected role removes it when you save\./);
    assert.match(view, /data-workspace-dialog-close>Cancel/);
    assert.match(view, /type="submit"[^>]*>[^<]*<x-ui\.icon name="check"[^>]*\/>Save roles/);
});

test('invitation history is responsive and exposes meaningful status styling', () => {
    assert.match(view, /class="data-panel staff-invitation-history"/);
    assert.match(view, /class="responsive-records"/);
    assert.match(view, /staff-invitation-status is-/);
    assert.match(view, /No invitations yet/);
    assert.match(css, /\.staff-invitation-status\.is-accepted/);
    assert.match(css, /\.staff-invitation-status\.is-pending/);
    assert.match(css, /\.staff-invitation-history>header\{[^}]+padding:22px 24px[^}]+border-bottom:1px solid var\(--line\)/);
    assert.match(css, /@media\(max-width:760px\)\{\.staff-invitation-history>header\{[^}]+padding:20px/);
});

test('safe administrator and operations roles can be selected for invitations', () => {
    const invitationRoles = roles.match(/ROUTINE_INVITATION_ROLES\s*=\s*\[(?<list>[\s\S]*?)\];/)?.groups?.list ?? '';
    assert.match(invitationRoles, /'ministry_administrator'/);
    assert.match(invitationRoles, /'ministry_operations_coordinator'/);
    assert.doesNotMatch(invitationRoles, /'super_administrator'/);
    assert.doesNotMatch(invitationRoles, /'security_administrator'/);
});
