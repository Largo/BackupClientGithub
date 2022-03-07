#!/bin/bash
chmod 777 /var/www/html
chmod 777 /var/www/html/httpdocs
chmod 655 /var/www/html/httpdocs/.htaccess
cd /var/www/html/httpdocs
#chmod 777 /var/www/html/httpdocs/logs && cd /var/www/html/httpdocs
#touch /var/www/html/httpdocs/logs/.gitkeep 
composer install -d webbackupclient
composer install -d webbackupserver