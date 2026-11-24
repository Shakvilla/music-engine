#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="${ENV_FILE:-$ROOT_DIR/.env.production}"
COMPOSE_FILE="${COMPOSE_FILE:-$ROOT_DIR/compose.production.yaml}"
BACKUP_DIR="${BACKUP_DIR:-$ROOT_DIR/backups}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"

if [[ ! -f "$ENV_FILE" ]]; then
    echo "Missing production environment file: $ENV_FILE" >&2
    exit 1
fi

umask 077
mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"
stamp="$(date -u +%Y%m%dT%H%M%SZ)"
database_tmp="$BACKUP_DIR/musicengine-$stamp.sql.gz.tmp"
media_tmp="$BACKUP_DIR/musicengine-$stamp-uploads.tar.gz.tmp"
database_backup="${database_tmp%.tmp}"
media_backup="${media_tmp%.tmp}"
cleanup() { rm -f "$database_tmp" "$media_tmp"; }
trap cleanup EXIT

docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec -T db sh -c \
    'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqldump --single-transaction --routines --triggers -uroot "$MYSQL_DATABASE"' \
    | gzip -9 > "$database_tmp"

docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec -T app \
    tar -czf - -C /var/www/html/Script uploads -C /var/www/html musicengine-private > "$media_tmp"

mv "$database_tmp" "$database_backup"
mv "$media_tmp" "$media_backup"
(cd "$BACKUP_DIR" && sha256sum "$(basename "$database_backup")" "$(basename "$media_backup")" > "musicengine-$stamp.sha256")
find "$BACKUP_DIR" -maxdepth 1 -type f -name 'musicengine-*' -mtime "+$RETENTION_DAYS" -delete

echo "Created backup set $stamp in $BACKUP_DIR"
