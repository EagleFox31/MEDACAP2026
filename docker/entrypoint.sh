#!/bin/sh
set -e
mkdir -p /var/lib/medacap/sessions
chown www-data:www-data /var/lib/medacap/sessions
chmod 1733 /var/lib/medacap/sessions
exec docker-php-entrypoint apache2-foreground
