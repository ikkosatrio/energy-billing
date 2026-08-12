#!/bin/bash

# Development entrypoint - HOT RELOAD via Octane --watch
# Code is volume-mounted from the host (see tenants/development/docker-compose.yml).
# Octane runs with --watch so any change under app/, routes/, config/ (etc.)
# automatically reloads the FrankenPHP workers - no rebuild, no restart needed.

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo "${GREEN}Initializing Laravel Development Environment (Octane --watch)...${NC}"

cd /app

# bootstrap/cache is a persistent named volume here (see docker-compose.yml),
# so a compiled packages.php/services.php from a previous vendor state can
# survive container recreates and reference classes that no longer exist
# (fatal "Class ... not found" on every artisan call, since it can't even
# boot far enough to run package:discover). Wipe it BEFORE any artisan
# command so providers are always rediscovered fresh from the current vendor.
rm -f /app/bootstrap/cache/*.php

# Only clear caches if vendor exists (composer installed)
if [ -d "/app/vendor" ]; then
    echo "${YELLOW}Regenerating package manifest...${NC}"
    php artisan package:discover --ansi 2>/dev/null || true

    echo "${YELLOW}Clearing config/route/view/cache...${NC}"
    php artisan config:clear 2>/dev/null || true
    php artisan route:clear 2>/dev/null || true
    php artisan view:clear 2>/dev/null || true
    php artisan cache:clear 2>/dev/null || true

    # Create storage symlink if needed
    if [ ! -L "/app/public/storage" ]; then
        echo "${YELLOW}Creating storage symlink...${NC}"
        php artisan storage:link 2>/dev/null || true
    fi
else
    echo "${YELLOW}Composer not installed yet - skipping artisan commands${NC}"
fi

echo "${GREEN}Starting Octane (FrankenPHP) with --watch...${NC}"
echo "${YELLOW}Code changes under app/, routes/, config/ will reload automatically${NC}"

# FrankenPHP's native watcher (e-dant/watcher, inotify-based) cannot see file
# changes coming through Docker Desktop bind mounts on Windows. Laravel Octane's
# --poll flag is not forwarded to FrankenPHP, so we start our own mtime-polling
# watcher that triggers `octane:reload` when a file changes.
if [ -x "/usr/local/bin/octane-watch-loop.sh" ]; then
    nohup /usr/local/bin/octane-watch-loop.sh > /tmp/octane-watch-loop.log 2>&1 &
    echo "${GREEN}Polling watcher started (log: /tmp/octane-watch-loop.log)${NC}"
fi

exec php artisan octane:frankenphp \
    --host=0.0.0.0 \
    --port=80 \
    --admin-port=2019 \
    --workers=auto \
    --max-requests=500 \
    --watch \
    --poll
