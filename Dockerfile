FROM php:8.2-apache

# Weka ServerName kuzuia Apache warnings
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Wezesha extensions za MySQL PDO na mysqli
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Wezesha Apache modules zote zinazohitajika na .htaccess
RUN a2enmod rewrite headers access_compat env expires deflate

# Wezesha AllowOverride All kwenye apache2.conf
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Nakili kodi zote za mradi kwenda kwenye web root ya Apache
COPY . /var/www/html/

# Weka ruhusa (permissions) sahihi za mafile
RUN chmod -R 777 /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
