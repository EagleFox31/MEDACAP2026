FROM php:8.4-apache

RUN sed -i 's|http://|https://|g' /etc/apt/sources.list.d/debian.sources \
    && apt-get update \
    && apt-get install -y --no-install-recommends libssl-dev pkg-config \
    && pecl install mongodb-1.21.0 \
    && docker-php-ext-enable mongodb \
    && a2enmod rewrite \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && rm -rf /var/lib/apt/lists/*

# PhpSpreadsheet 1.30 exige gd et zip. L'image officielle ne les active pas.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd zip \
    && rm -rf /var/lib/apt/lists/*

# Le site envoie du HTML avant la redirection de connexion. Le WAMP tamponne
# cette sortie. La bibliothèque MongoDB est en 1.21 : les dépréciations
# de la 1.0.0 ne sont plus masquées.
RUN printf '%s\n' \
    'output_buffering=4096' \
    'display_errors=On' \
    'log_errors=On' \
    'error_reporting=E_ALL' \
    'date.timezone=Africa/Douala' \
    'session.save_path=/var/lib/medacap/sessions' \
    > /usr/local/etc/php/conf.d/medacap.ini

# Le lien « Continuer » appelle /quiz sans .php. MultiViews échoue avec le
# handler PHP de l'image officielle, donc on ajoute l'extension quand le fichier existe.
RUN printf '%s\n' \
    '<Directory /var/www/html>' \
    'AllowOverride All' \
    'RewriteEngine On' \
    'RewriteCond %{REQUEST_FILENAME} !-d' \
    'RewriteCond %{REQUEST_FILENAME}.php -f' \
    'RewriteRule ^(.+)$ $1.php [L]' \
    '</Directory>' \
    > /etc/apache2/conf-available/medacap.conf \
    && a2enconf medacap

COPY entrypoint.sh /usr/local/bin/medacap-entrypoint.sh
RUN chmod +x /usr/local/bin/medacap-entrypoint.sh
ENTRYPOINT ["medacap-entrypoint.sh"]
