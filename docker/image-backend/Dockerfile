FROM php:8.5-fpm

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        intl \
        zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ARG U_ID=1000
RUN \
  addgroup default \
  && adduser --shell /bin/bash --uid ${U_ID} --ingroup default --home /home/default --disabled-password default \
  && chown -R default:default /var/www/html
USER default

EXPOSE 9000

CMD ["php-fpm"]
