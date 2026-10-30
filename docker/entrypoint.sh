#!/bin/sh
set -eu

mkdir -p /var/lib/medacap/sessions /var/www/html/vendor
chown www-data:www-data /var/lib/medacap/sessions
chmod 1733 /var/lib/medacap/sessions

# Un volume dédié monte sur /var/www/html/vendor : le bind mount du dépôt
# ne masque donc plus les dépendances de l'image. Synchroniser seulement si
# composer.lock a changé entre deux constructions d'image.
SOURCE=/opt/medacap-deps/vendor
TARGET=/var/www/html/vendor
if [ ! -f "$SOURCE/autoload.php" ] || [ ! -f "$SOURCE/.medacap-lock-sha" ]; then
  echo "Erreur: dépendances Composer manquantes dans l'image" >&2
  exit 1
fi
if [ ! -f "$TARGET/.medacap-lock-sha" ] || ! cmp -s "$SOURCE/.medacap-lock-sha" "$TARGET/.medacap-lock-sha"; then
  find "$TARGET" -mindepth 1 -maxdepth 1 -exec rm -rf -- {} +
  cp -a "$SOURCE/." "$TARGET/"
fi
if [ ! -f "$TARGET/autoload.php" ]; then
  echo "Erreur: vendor/autoload.php absent dans le volume" >&2
  exit 1
fi

# mail() passe par msmtp vers le relais SMTP (sans authentification, comme
# l'ancien serveur avec SMTP = smtp.cfao.corp dans php.ini).
SMTP_HOST=${MEDACAP_SMTP_HOST:-smtp.cfao.corp}
SMTP_PORT=${MEDACAP_SMTP_PORT:-25}
MAIL_FROM=${MEDACAP_MAIL_FROM:-helpdeskcm@cfao.com}
printf 'sendmail_path = "/usr/bin/msmtp -t --host=%s --port=%s --from=%s --auth=off --tls=off"\n' \
  "$SMTP_HOST" "$SMTP_PORT" "$MAIL_FROM" \
  > /usr/local/etc/php/conf.d/medacap-mail.ini

exec docker-php-entrypoint apache2-foreground
