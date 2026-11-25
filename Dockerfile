# syntax=docker/dockerfile:1
#
# Production image for the Tolarian VPS (Dokploy): the pre-rendered Astro site
# and the PHP API, both served by Apache, which reads the .htaccess files as
# the OVH shared host did.

FROM node:24-slim AS build
WORKDIR /app/site
COPY site/package.json site/package-lock.json ./
# Same fallback as the CI: npm sometimes drops the rolldown native bindings.
RUN npm ci || { rm -rf node_modules package-lock.json && npm install; }
COPY site/ ./
# Public values, not secrets: the Turnstile site key and the submission forms
# switch. Set as build arguments in Dokploy.
ARG PUBLIC_TURNSTILE_SITE_KEY
ARG PUBLIC_SUBMISSIONS_ENABLED
# The Scryfall cache survives between builds in a BuildKit cache: without it,
# every deploy downloads every card again.
RUN --mount=type=cache,id=pdc-scryfall,target=/app/site/.cache npm run build

FROM php:8.3-apache
RUN a2enmod rewrite headers expires remoteip setenvif deflate \
 && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && echo 'expose_php = Off' > "$PHP_INI_DIR/conf.d/pdc.ini"
COPY docker/zz-pdc.conf /etc/apache2/conf-enabled/zz-pdc.conf
COPY --from=build /app/site/dist/ /var/www/html/
# Scryfall cache and rate-limit state: a volume in Dokploy, writable by PHP.
RUN mkdir -p /var/www/html/api/cache && chown -R www-data:www-data /var/www/html/api/cache
