FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev curl \
    && docker-php-ext-install mbstring pdo_mysql \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
RUN printf '%s\n' 'expose_php=Off' 'display_errors=Off' 'log_errors=On' > /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/html
COPY --chown=root:root . /var/www/html

RUN chmod -R a=rX /var/www/html

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -fsS 'http://localhost/public/index.php?route=auth.login' || exit 1
