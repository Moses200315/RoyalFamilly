FROM php:8.2-apache

# Wezesha extensions za MySQL PDO na mysqli
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Wezesha Apache modules zote zinazotumiwa na .htaccess hii
RUN a2enmod rewrite headers access_compat env expires deflate

# Wezesha AllowOverride All
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

COPY . /var/www/html/

RUN chmod -R 777 /var/www/html

EXPOSE 80
