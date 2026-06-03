# Stage 1: Build assets
# -----------------------------------------------------------
# PHP + Composer are needed here because the Wayfinder Vite plugin
# runs `php artisan wayfinder:generate` during the asset build.
# The builder stage is discarded after the build; only the final
# nginx stage is kept, so the extra size has no runtime impact.
# -----------------------------------------------------------
FROM php:8.3-cli AS builder

COPY --from=node:22.13.0-bookworm-slim /usr/local/ /usr/local/

# Set working directory
WORKDIR /var/www

# Install PHP extensions required by Composer, Artisan, and Wayfinder.
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    unzip \
    libpq-dev \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo_pgsql \
    pgsql \
    intl \
    zip \
    bcmath \
    gd \
    && apt-get autoremove -y && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- \
    --install-dir=/usr/local/bin --filename=composer

# Copy Laravel application code
COPY . /var/www

# Install PHP dependencies (needed for artisan commands like wayfinder:generate)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress --prefer-dist

# Install Node.js dependencies and build assets
RUN php artisan route:clear \
    && npm ci --ignore-scripts=false \
    && npm run build

# Stage 2: Nginx production image
FROM nginx:alpine

# Copy custom Nginx configuration
# -----------------------------------------------------------
# Replace the default Nginx configuration with our custom one
# that is optimized for serving a Laravel application.
# -----------------------------------------------------------
COPY ./.docker/production/nginx/nginx.conf /etc/nginx/nginx.conf

# Copy Laravel's public assets from the builder stage
# -----------------------------------------------------------
# We only need the 'public' directory from our Laravel app.
# -----------------------------------------------------------
COPY --from=builder /var/www/public /var/www/public

# Set the working directory to the public folder
WORKDIR /var/www/public

# Create the storage symlink for serving files
# -----------------------------------------------------------
# This symlink allows Nginx to serve files from the shared
# storage volume via the /storage/* URL path.
# -----------------------------------------------------------
RUN ln -sf ../storage/app/public storage

# Expose port 80 and start Nginx
EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
