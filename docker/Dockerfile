FROM php:8.4-apache

# PHP 8.4 + extensions utilisées par MEDACAP et PhpSpreadsheet 1.30.
RUN sed -i 's|http://|https://|g' /etc/apt/sources.list.d/debian.sources \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
       libssl-dev pkg-config libonig-dev \
       libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev \
       msmtp \
    && pecl install mongodb-1.21.0 \
    && docker-php-ext-enable mongodb \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd zip mbstring \
    && a2enmod rewrite \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && rm -rf /var/lib/apt/lists/*

# Les dépendances sont construites avec LE PHP qui exécute réellement MEDACAP.
# Le volume vendor est initialisé depuis cette copie au premier démarrage.
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /opt/medacap-deps
COPY composer.json composer.lock ./
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install \
        --no-dev --prefer-dist --no-interaction --no-progress \
        --no-scripts --optimize-autoloader \
    && sha256sum composer.lock | cut -d ' ' -f1 > vendor/.medacap-lock-sha

# Afficher les erreurs dans les logs, pas dans les réponses HTTP.
RUN printf '%s\n' \
    'output_buffering=4096' \
    'display_errors=Off' \
    'log_errors=On' \
    'error_reporting=E_ALL' \
    'date.timezone=Africa/Douala' \
    'session.save_path=/var/lib/medacap/sessions' \
    > /usr/local/etc/php/conf.d/medacap.ini

# Les liens sans extension .php sont conservés.
# Le code contient des URL absolues /MEDACAP/... (et les favoris /medacap/ de l'ancienne prod) :
# ce préfixe est servi depuis la racine.
RUN printf '%s\n' \
    'AliasMatch "(?i)^/medacap(/.*)?$" "/var/www/html$1"' \
    '<Directory /var/www/html>' \
    'AllowOverride All' \
    'RewriteEngine On' \
    'RewriteCond %{REQUEST_FILENAME} !-d' \
    'RewriteCond %{REQUEST_FILENAME}.php -f' \
    'RewriteRule ^(.+)$ $1.php [L]' \
    '</Directory>' \
    > /etc/apache2/conf-available/medacap.conf \
    && a2enconf medacap

COPY docker/entrypoint.sh /usr/local/bin/medacap-entrypoint.sh
RUN chmod +x /usr/local/bin/medacap-entrypoint.sh
WORKDIR /var/www/html
ENTRYPOINT ["medacap-entrypoint.sh"]
