# Base image
FROM php:8.0-apache as php_base

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
    && apt update && apt install -y locales apt-utils msmtp msmtp-mta mailutils mariadb-client unzip zip pngquant libpng-dev libmcrypt-dev git nano \
    curl libicu-dev libxml2-dev libssl-dev libcurl4 libcurl4-openssl-dev \
    && rm -rf /var/lib/apt/lists/*

RUN /bin/sh /install-php-extensions.sh mysqli bcmath intl gettext exif zip curl pdo_mysql pdo_sqlite opcache gd xdebug 

COPY docker/php-apache2/conf.d/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php-apache2/conf.d/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini
COPY docker/php-apache2/conf.d/memory_limit.ini /usr/local/etc/php/conf.d/memory_limit.ini

# install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Configure Sendmail to use MaiLhog to capture emails. See https://r.je/sendmail-php-docker and https://akrabat.com/using-mailhog-via-docker-for-testing-email/
# 1. PHP does not have the sendmail path set
RUN echo "sendmail_path=/usr/bin/msmtp -t" >> /usr/local/etc/php/conf.d/sendmail.ini 
# 2. Automatically start sendmail service each time container starts
RUN sed -i '/#!\/bin\/sh/aservice sendmail restart' /usr/local/bin/docker-php-entrypoint
# 3. Automatically set hostname for sendmail each time container starts
RUN sed -i '/#!\/bin\/sh/aecho "$(hostname -i)\t$(hostname) $(hostname).localhost" >> /etc/hosts' /usr/local/bin/docker-php-entrypoint

# https://www.media-techport.de/blog/2020/02/emails-versenden-mit-msmtp/
COPY docker/php-apache2/msmtprc /etc/msmtprc
RUN chmod 600 /etc/msmtprc && chown www-data /etc/msmtprc

# Update web root to httpdocs
# See: https://hub.docker.com/_/php#changing-documentroot-or-other-apache-configuration
ENV APACHE_DOCUMENT_ROOT /var/www/html/httpdocs
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# PHP REPL PsySH
RUN composer g require psy/psysh:@stable
#RUN export PATH="$HOME/.composer/vendor/bin:$PATH"
ENV PATH /root/.composer/vendor/bin:$PATH
RUN echo "alias repl='(cd $APACHE_DOCUMENT_ROOT && psysh --cwd $APACHE_DOCUMENT_ROOT $APACHE_DOCUMENT_ROOT/index.php)'" >> ~/.bashrc

# Enable mod_rewrite
RUN a2enmod rewrite && a2enmod headers

CMD sed -i "s/80/$PHP_PORT/g" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf && docker-php-entrypoint apache2-foreground
FROM php_base AS php_apache_cs_app

#COPY . /var/www/html/httpdocs
#RUN rm -rf /var/www/html/httpdocs/composer_modules & rm -rf /var/www/html/httpdocs/logs & mkdir -p /var/www/html/httpdocs/logs & chmod 777 /var/www/html/httpdocs/logs
#RUN cd /var/www/html/httpdocs && composer install 