BEGIN;
INSERT INTO ministry_settings (id, name, public_domain, timezone, primary_color, accent_color, created_at, updated_at)
VALUES ('00000000-0000-0000-0000-000000000001', 'Restore Fixture', 'fixture.example.org', 'UTC', '#17324D', '#D9A441', now(), now());
INSERT INTO locations (id, name, slug, timezone, is_primary, is_public, created_at, updated_at)
VALUES ('10000000-0000-0000-0000-000000000001', 'Fixture Campus', 'fixture-campus', 'UTC', true, true, now(), now());
INSERT INTO users (id, name, email, password, created_at, updated_at)
VALUES ('20000000-0000-0000-0000-000000000001', 'Fixture User', 'fixture@example.org', 'disabled-test-login', now(), now());
COMMIT;
