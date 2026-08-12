#!/bin/bash

# octane-watch-loop.sh
# Fallback hot-reload watcher for environments where FrankenPHP's native
# inotify watcher cannot see file changes (e.g. Docker Desktop on Windows,
# where bind-mount events are not propagated into the container).
#
# Uses a content hash (not mtime) to detect changes: Docker Desktop's bind
# mount attribute cache can leave mtime stale relative to the host (observed
# after the VM was idle/suspended), even though the file's actual content is
# already up to date. Hashing forces a real read, so it is reliable.

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

cd /app || exit 1

# Paths to watch (mirrors config/octane.php watch list)
WATCH_PATHS="app routes config database resources public bootstrap"
POLL_INTERVAL=2

hash_watched_files() {
    find $WATCH_PATHS -type f \( -name '*.php' -o -name '*.env*' \) 2>/dev/null \
        | sort \
        | xargs -r md5sum 2>/dev/null \
        | md5sum \
        | awk '{print $1}'
}

PREV_HASH=$(hash_watched_files)

echo "${GREEN}Starting content-hash watcher (interval ${POLL_INTERVAL}s) for: ${WATCH_PATHS}${NC}"

while true; do
    sleep "$POLL_INTERVAL"

    CURRENT_HASH=$(hash_watched_files)

    if [ "$CURRENT_HASH" != "$PREV_HASH" ]; then
        PREV_HASH="$CURRENT_HASH"
        echo "${YELLOW}File change detected (content hash changed)${NC}"
        echo "${GREEN}Reloading Octane workers...${NC}"
        php artisan octane:reload 2>&1 | sed 's/^/  /'
    fi
done
