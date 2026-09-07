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

ARG UID=1000
ARG GID=33
RUN \
  addgroup default \
  && adduser --shell /bin/bash --uid ${UID} --gid ${GID} --home /home/default --disabled-password default \
  && chown -R default:www-data /var/www/html
USER default

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
EXPOSE 9000

CMD ["php-fpm"]
