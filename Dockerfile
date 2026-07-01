# syntax=docker/dockerfile:1
# Render 무료 티어 배포용 (내부 검토 프리뷰). SQLite + 매 기동 시 샘플 데이터 시딩.

# ---- Stage 1: 프론트엔드 에셋 빌드 (Vite) ----
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# ---- Stage 2: PHP 런타임 ----
FROM php:8.2-cli AS app
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libonig-dev sqlite3 libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite mbstring bcmath zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app

# 앱 소스 + 빌드된 에셋
COPY . .
COPY --from=assets /app/public/build ./public/build

# PHP 의존성 (운영용, 스크립트 미실행 — 패키지 매니페스트는 런타임에 자동 생성)
RUN composer install --no-dev --no-scripts --optimize-autoloader --no-interaction --prefer-dist

# 쓰기 디렉터리 + SQLite 파일
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache database \
    && touch database/database.sqlite \
    && chmod -R 777 storage bootstrap/cache database

ENV PORT=10000
EXPOSE 10000

# 기동 시: DB를 샘플 데이터로 초기화 후 서버 실행
CMD sh -c "php artisan migrate:fresh --seed --force && php artisan serve --host 0.0.0.0 --port ${PORT}"
