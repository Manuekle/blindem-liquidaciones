#!/bin/sh
set -eu
umask 077
backup_dir="${BACKUP_DIR:-backups}"
mkdir -p "$backup_dir"
backup_file="$backup_dir/blindem-$(date -u +%Y%m%dT%H%M%SZ).dump"
temporary_file="$backup_file.partial"
trap 'rm -f "$temporary_file"' EXIT HUP INT TERM
docker compose exec -T postgres sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc' > "$temporary_file"
mv "$temporary_file" "$backup_file"
printf '%s\n' "$backup_file"
