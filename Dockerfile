ARG PHP_VERSION=8.1

FROM php:${PHP_VERSION}-cli

EXPOSE 9003

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update && apt-get install -y \
	git \
	libxml2-dev \
	libonig-dev

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions dom mbstring xdebug

RUN echo "[xdebug]\n\
xdebug.mode=debug\n\
xdebug.client_host=host.docker.internal\n\
xdebug.start_with_request=yes\n\
xdebug.idekey=docker\n\
xdebug.client_port=9003\n\
" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . /app

RUN composer install --dev

CMD ["php", "vendor/bin/phpunit", "tests", "--testdox", "--colors=always"]