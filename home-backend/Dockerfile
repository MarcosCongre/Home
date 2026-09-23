FROM php:8.2-cli

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libsqlite3-dev \
    && docker-php-ext-install pdo_mysql \
    && rm -rf /var/www/ && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY . /var/www/html

RUN composer install --no-interaction --no-progress --no-scripts --prefer-dist

CMD ["php", "-S", "0.0.0.0:8000", "-t", "/var/www/html"]
