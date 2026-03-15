#!/bin/bash
set -euo pipefail

SERVER="deploy@46.224.114.108"
REMOTE_PATH="/home/deploy/laravel_rag"

# 1. Sync code files
rsync -avz \
  --exclude='myreadme.md' --exclude='.git' --exclude='node_modules' --exclude='vendor' \
  --exclude='.env*' --exclude='.claude' --exclude='.idea' \
  --exclude='storage' --exclude='bootstrap/cache' --exclude='.phpunit.result.cache' --exclude='tests' \
  ./ ${SERVER}:${REMOTE_PATH}/

# 2. Clear caches and restart PHP-FPM to pick up changes (OPcache has validate_timestamps=0)
ssh ${SERVER} "docker exec laravel_rag_app php artisan optimize:clear && \
  docker exec laravel_rag_app kill -USR2 1"

echo "Deployed!"
