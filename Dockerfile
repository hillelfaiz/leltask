# ==========================================
# Tahap 1: Install Dependensi PHP (Composer)
# ==========================================
FROM composer:2.7 AS vendor
WORKDIR /app
COPY database/ database/
COPY composer.json composer.lock ./
RUN composer install \
    --ignore-platform-reqs \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --prefer-dist

# ==========================================
# Tahap 2: Build Aset Frontend (Node.js)
# ==========================================
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm install
COPY . .
RUN npm run build

# ==========================================
# Tahap 3: Image Utama (Nginx + PHP)
# ==========================================
FROM webdevops/php-nginx:8.3-alpine

# Konfigurasi Environment untuk Produksi
ENV WEB_DOCUMENT_ROOT=/app/public
ENV APP_ENV=production
ENV APP_DEBUG=false

WORKDIR /app

# Salin kode aplikasi utama
COPY . .

# Salin folder vendor (PHP) dari Tahap 1
COPY --from=vendor /app/vendor/ /app/vendor/

# Salin aset frontend (Vite build) dari Tahap 2
COPY --from=frontend /app/public/build/ /app/public/build/

# Setel permission folder agar Laravel bisa menulis cache dan log
RUN chown -R application:application /app/storage /app/bootstrap/cache
RUN chmod -R 775 /app/storage /app/bootstrap/cache

# Jalankan skrip optimasi dan migrasi otomatis saat server menyala
RUN echo "php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan migrate --force" > /opt/docker/provision/entrypoint.d/99-laravel-setup.sh
