#!/bin/sh
set -e

# Hosts such as Render tell the app which port to listen on.
PORT="${PORT:-80}"
sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Accept a generated key without Laravel's "base64:" prefix.
case "$APP_KEY" in
  ""|base64:*) ;;
  *) export APP_KEY="base64:${APP_KEY}" ;;
esac

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
php artisan db:seed --force

# Daily jobs: end used-up subscriptions and send renewal reminders.
php artisan schedule:work > /dev/null 2>&1 &

exec apache2-foreground
