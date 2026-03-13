#!/bin/bash
#
# Database Backup Script
# Creates a pg_dump backup and keeps the last 7 days.
#
# Usage: bash deploy/backup-db.sh
# Cron:  0 3 * * * cd /home/deploy/laravel_rag && bash deploy/backup-db.sh
#

set -euo pipefail

BACKUP_DIR="/home/deploy/backups"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
KEEP_DAYS=7

mkdir -p "${BACKUP_DIR}"

echo "==> Creating database backup..."
docker exec laravel_rag_postgres pg_dump \
    -U "${DOCKER_DB_USERNAME:-laravel_rag}" \
    -d "${DOCKER_DB_DATABASE:-laravel_rag}" \
    --clean --if-exists \
    | gzip > "${BACKUP_DIR}/laravel_rag_${TIMESTAMP}.sql.gz"

echo "    Backup saved: ${BACKUP_DIR}/laravel_rag_${TIMESTAMP}.sql.gz"

echo "==> Cleaning up backups older than ${KEEP_DAYS} days..."
find "${BACKUP_DIR}" -name "laravel_rag_*.sql.gz" -mtime +${KEEP_DAYS} -delete

echo "==> Current backups:"
ls -lh "${BACKUP_DIR}"/laravel_rag_*.sql.gz 2>/dev/null || echo "    (none)"
