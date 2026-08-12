# Universal Dockerfile for Development and Production
# Laravel Octane on FrankenPHP: one binary (Caddy + PHP) serves static assets
# AND executes Laravel — no separate Nginx / PHP-FPM processes to manage.
# Traefik is untouched: this image still listens on container port 80.
FROM dunglas/frankenphp:php8.2

# Install PHP extensions needed by the app (mbstring/xml/opcache already
# built into the base image).
RUN install-php-extensions \
    bcmath \
    pdo_mysql \
    mysqli \
    zip \
    exif \
    pcntl \
    sockets \
    gd

# Install Node.js (for Tailwind CSS build)
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - && \
    apt-get install -y nodejs && \
    rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy PHP config
COPY docker/php/php.ini /usr/local/etc/php/php.ini

# Copy app
COPY . /app

# Install PHP dependencies
RUN if [ -f "composer.json" ]; then \
    composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction; \
    fi

# Build frontend assets (Tailwind CSS)
RUN if [ -f "package.json" ]; then \
    npm ci && \
    npm run tw:build && \
    rm -rf node_modules; \
    fi

# Storage permissions
RUN mkdir -p storage/logs \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache

# Copy entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80 443 443/udp

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
