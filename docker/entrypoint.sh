#!/bin/sh

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "${GREEN}Starting Laravel Application (Octane/FrankenPHP)...${NC}"

# Change to app directory
cd /app

rm -f /app/bootstrap/cache/*.php

# Set permissions (ignore errors for volume mounts)
echo "${YELLOW}Setting permissions...${NC}"
chmod -R 775 /app/storage 2>/dev/null || true
chmod -R 775 /app/bootstrap/cache 2>/dev/null || true
chmod -R 775 /app/public 2>/dev/null || true

# Clear caches (only if vendor exists)
if [ -d "/app/vendor" ]; then
    # Wipe compiled provider cache first: package:discover itself needs a
    # working boot, and a stale services.php referencing a removed class
    # (e.g. leftover from a previous vendor state) fails BEFORE any artisan
    # command can run, so `rm` has to happen ahead of package:discover.
    rm -f /app/bootstrap/cache/*.php

    # Regenerate the cached package manifest so newly added/removed composer
    # packages (e.g. laravel/octane) are picked up even when bootstrap/cache
    # is a persistent volume left over from a previous image/vendor state.
    echo "${YELLOW}Discovering packages...${NC}"
    php artisan package:discover --ansi 2>/dev/null || true

    echo "${YELLOW}Clearing caches...${NC}"
    php artisan config:clear 2>/dev/null || true
    php artisan cache:clear 2>/dev/null || true
    php artisan view:clear 2>/dev/null || true

    # Create storage symlink if it doesn't exist (skip if already exists or can't create)
    if [ ! -L "/app/public/storage" ] && [ ! -d "/app/public/storage" ]; then
        echo "${YELLOW}Creating storage symlink...${NC}"
        php artisan storage:link 2>&1 | grep -v "already exists\|Permission denied" || true
    fi
else
    echo "${YELLOW}Composer dependencies not installed yet - skipping artisan commands${NC}"
fi

echo "${GREEN}Application initialized!${NC}"
echo ""

# Start Octane on FrankenPHP. FrankenPHP (Caddy + PHP in one binary) serves
# static assets directly from public/ AND executes Laravel — no Nginx or
# PHP-FPM needed. Listens on port 80 so Traefik's routing is unaffected.
# --max-requests recycles each worker after N requests to guard against
# memory leaks / stale static state building up over the worker's lifetime.
echo "${GREEN}Starting Octane (FrankenPHP)...${NC}"
exec php artisan octane:frankenphp \
    --host=0.0.0.0 \
    --port=80 \
    --admin-port=2019 \
    --workers=auto \
    --max-requests=500
