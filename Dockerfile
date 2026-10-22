FROM php:8.5.12-apache-bookworm

ENV DEBIAN_FRONTEND=noninteractive \
    TZ=Asia/Bangkok \
    APACHE_DOCUMENT_ROOT=/var/www/html

RUN apt-get update && apt-get install -y --no-install-recommends \
      libzip-dev \
      libpng-dev \
      libjpeg62-turbo-dev \
      libfreetype6-dev \
      libicu-dev \
      libcurl4-openssl-dev \
      unzip \
      curl \
      wait-for-it \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql \
    && docker-php-ext-install mysqli \
    && docker-php-ext-install gd \
    && docker-php-ext-install zip \
    && docker-php-ext-install intl \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Serve app from /var/www/html (project root)
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/sci-shop.ini
COPY docker/entrypoint.sh /usr/local/bin/sci-entrypoint.sh

RUN sed -i 's/\r$//' /usr/local/bin/sci-entrypoint.sh \
 && chmod +x /usr/local/bin/sci-entrypoint.sh \
 && mkdir -p /var/www/html/data/uploads \
 && chown -R www-data:www-data /var/www/html

WORKDIR /var/www/html

# App code is bind-mounted in compose for local dev; still COPY for standalone image builds.
COPY --chown=www-data:www-data . /var/www/html

EXPOSE 80

# Prefer bind-mounted script in compose; fall back to image copy.
ENTRYPOINT ["/bin/bash", "-c", "if [ -f /var/www/html/docker/entrypoint.sh ]; then sed -i 's/\\r$//' /var/www/html/docker/entrypoint.sh; exec /bin/bash /var/www/html/docker/entrypoint.sh \"$@\"; else exec /usr/local/bin/sci-entrypoint.sh \"$@\"; fi", "--"]
CMD ["apache2-foreground"]
