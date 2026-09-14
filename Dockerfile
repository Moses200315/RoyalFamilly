FROM php:8.2-apache

RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

RUN docker-php-ext-install pdo pdo_mysql mysqli

RUN a2enmod rewrite headers access_compat env expires deflate

RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

COPY . /var/www/html/

RUN chmod -R 777 /var/www/html
RUN chmod +x /var/www/html/entrypoint.sh

ENTRYPOINT ["/var/www/html/entrypoint.sh"]
