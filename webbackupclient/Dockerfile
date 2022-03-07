# Base image
FROM php:8.0.16-cli-buster as php_base

# Set Terminal to use UTF-8 encoding
ENV LANG=C.UTF-8
  
# Fix debconf warnings upon build
ARG DEBIAN_FRONTEND=noninteractive

# Add our supported locales to make sure gettext works
COPY docker/php-apache2/locale.gen /etc/locale.gen
#RUN /bin/bash -C locale-gen

# Move PHP configuration
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# 1. Run apt update and install some dependencies
# 2. Install PHP extensions using the instal-php-extensions-project from https://github.com/mlocati/docker-php-extension-installer
# 3. Clean up APT cache
# Simpler installing of PHP Extensions using the docker-php-extension-installer project.
COPY docker/php-apache2/install-php-extensions.sh /install-php-extensions.sh
RUN chmod +x /install-php-extensions.sh && sync \
    && apt update && apt install -y locales apt-utils unzip zip libmcrypt-dev git nano \
    curl libicu-dev libxml2-dev libssl-dev libcurl4 libcurl4-openssl-dev \
    && rm -rf /var/lib/apt/lists/*

RUN /bin/sh /install-php-extensions.sh zip curl 

COPY docker/php-apache2/conf.d/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php-apache2/conf.d/memory_limit.ini /usr/local/etc/php/conf.d/memory_limit.ini

# install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

#RUN export PATH="$HOME/.composer/vendor/bin:$PATH"
ENV PATH /root/.composer/vendor/bin:$PATH

FROM php_base AS php_apache_cs_app

COPY . /var/opt/app
RUN rm -rf /var/opt/app/composer_modules
RUN rm /var/opt/app/.env
RUN cd /var/opt/app && composer install 