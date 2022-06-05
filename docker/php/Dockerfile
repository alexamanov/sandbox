FROM php:8.1-fpm
LABEL "docs"="https://alexamanov.github.io"

# Install dependencies
RUN apt-get update \
  && apt-get install -y \
    procps \
    libcurl4-openssl-dev \
    libfreetype6-dev \
    libldap2-dev \
    libicu-dev \
    libjpeg62-turbo-dev \
    libmcrypt-dev \
    libpng-dev \
    libxslt1-dev \
    libzip-dev \
    libsodium-dev \
    libonig-dev \
    libpq-dev \
    npm \
    python2.7 \
    python2.7-dev \
    webp \
    optipng \
    jpegoptim \
    jq \
    gifsicle \
    sudo

# Configure the gd library
RUN docker-php-ext-configure \
  gd --with-freetype --with-jpeg

RUN docker-php-ext-install \
  pdo_mysql \
  soap \
  bcmath \
  gd \
  intl \
  xsl \
  zip \
  sockets

#Install xdebug and configure
RUN yes | pecl install xdebug-3.1.3
RUN echo "zend_extension=$(find /usr/local/lib/php/extensions/ -name xdebug.so)" > /usr/local/etc/php/conf.d/xdebug.ini

#Configure sendmail and php
RUN echo "memory_limit=3G" > /usr/local/etc/php/conf.d/docker-php-memlimit.ini \
    && echo "max_execution_time = 600" >> /usr/local/etc/php/conf.d/docker-php-maxexectime.ini

#Configure permissions
RUN usermod -u 1000 www-data && groupmod -g 1000 www-data && \
    chsh -s /bin/bash www-data

RUN usermod -aG sudo www-data

USER www-data
