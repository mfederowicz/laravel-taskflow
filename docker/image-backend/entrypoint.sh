#!/bin/sh
set -e

cd /var/www/html

# Run the Laravel scheduler in the background; its output goes to container logs.
php artisan schedule:work > /proc/1/fd/1 2>&1 &

exec php-fpm