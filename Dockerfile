FROM php:8.5-apache

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends libapache2-mod-xsendfile libfreetype6-dev libjpeg62-turbo-dev libpng-dev libzip-dev libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd mbstring mysqli pdo_mysql zip \
    && a2enmod rewrite headers xsendfile \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/Script

RUN sed -ri 's!/var/www/html!/var/www/html/Script!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!/var/www/!/var/www/html/Script!g' /etc/apache2/apache2.conf \
    && sed -ri '/<Directory \/var\/www\/html\/Script>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf \
    && echo 'XSendFile On' >> /etc/apache2/apache2.conf \
    && echo 'XSendFilePath /var/www/html/Script/uploads' >> /etc/apache2/apache2.conf \
    && echo 'XSendFilePath /var/www/html/musicengine-private' >> /etc/apache2/apache2.conf \
    && echo 'ServerName localhost' >> /etc/apache2/apache2.conf

WORKDIR /var/www/html/Script

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-musicengine.ini
COPY Script/ /var/www/html/Script/

RUN mkdir -p /var/www/html/musicengine-private /var/www/html/Script/uploads \
    && touch /var/www/html/Script/uploads/index.html \
    && chown -R www-data:www-data /var/www/html/musicengine-private /var/www/html/Script/uploads

