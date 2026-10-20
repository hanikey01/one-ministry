#!/bin/sh
set -euo pipefail

PASS_FILE=/run/secrets/backup_passphrase
if [ ! -s "$PASS_FILE" ]; then
  echo 'Missing /run/secrets/backup_passphrase. Create a strong passphrase file before backup or restore.' >&2
  exit 2
fi

umask 077
cd /backups

case "${1:-}" in
  backup)
    name="ministry-$(date -u +%Y%m%dT%H%M%SZ).pgdump.gpg"
    if [ -e "$name" ]; then
      echo 'A backup with this timestamp already exists; retry in a moment.' >&2
      exit 2
    fi
    partial="$name.partial"
    trap 'rm -f "$partial"' EXIT
    pg_dump --format=custom --no-owner --no-acl "$PGDATABASE" |
      gpg --batch --yes --pinentry-mode loopback --passphrase-file "$PASS_FILE" \
        --symmetric --cipher-algo AES256 --compress-algo none --output "$partial"
    gpg --batch --yes --pinentry-mode loopback --passphrase-file "$PASS_FILE" \
      --decrypt "$partial" >/dev/null
    mv "$partial" "$name"
    sha256sum "$name" > "$name.sha256"
    echo "Encrypted backup verified: $name"
    ;;
  restore-verify)
    name="${2:-}"
    case "$name" in
      ''|*[!A-Za-z0-9._-]*|*.partial|*/*)
        echo 'Provide a backup filename from /backups, not a path.' >&2
        exit 2
        ;;
    esac
    case "$name" in
      *.pgdump.gpg) ;;
      *) echo 'Expected a .pgdump.gpg backup filename.' >&2; exit 2 ;;
    esac
    if [ ! -f "$name" ] || [ ! -f "$name.sha256" ]; then
      echo 'Backup or checksum sidecar is missing.' >&2
      exit 2
    fi
    expected="$(cut -d ' ' -f 1 "$name.sha256")"
    actual="$(sha256sum "$name" | cut -d ' ' -f 1)"
    if [ "${#expected}" -ne 64 ] || [ "$expected" != "$actual" ]; then
      echo 'Backup checksum verification failed.' >&2
      exit 2
    fi
    suffix="$(od -An -N4 -tx1 /dev/urandom | tr -d ' \n')"
    target="ministry_restore_${suffix}"
    if [ "$target" = "$PGDATABASE" ]; then
      echo 'Refusing to restore over the live database.' >&2
      exit 2
    fi
    createdb --template=template0 "$target"
    gpg --batch --yes --pinentry-mode loopback --passphrase-file "$PASS_FILE" \
      --decrypt "$name" |
      pg_restore --exit-on-error --no-owner --no-acl --dbname="$target"
    counts="$(psql --dbname="$target" --tuples-only --no-align --command='SELECT (SELECT count(*) FROM migrations), (SELECT count(*) FROM ministry_settings), (SELECT count(*) FROM locations), (SELECT count(*) FROM users), (SELECT count(*) FROM audit_events)')"
    echo "Restore verified in isolated database: $target"
    echo "Counts (migrations|ministry_settings|locations|users|audit_events): $counts"
    ;;
  *)
    echo 'Usage: ministry-backup backup | restore-verify <backup filename>' >&2
    exit 2
    ;;
esac
