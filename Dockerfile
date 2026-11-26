# MADAM CAKES — production image: Apache 2.4 + PHP 8.4 (mod_php) + PDO MySQL.
FROM php:8.4-apache

# pdo_mysql for the application, mbstring for its form validators, fileinfo for
# upload MIME checks (fileinfo and curl are already compiled into the image).
RUN apt-get update \
 && apt-get install -y --no-install-recommends libonig-dev \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-madam-cakes.ini
COPY docker/apache.conf /etc/apache2/conf-available/madam-cakes.conf
RUN a2enconf madam-cakes

WORKDIR /var/www/html
COPY . /var/www/html

# Runtime-writable directories; compose backs them with named volumes so uploads
# and logs survive image rebuilds. Everything else stays owned by root.
RUN mkdir -p logs \
      assets/uploads/products assets/uploads/categories assets/uploads/gallery \
      assets/uploads/custom-orders assets/uploads/reviews assets/uploads/content \
      assets/uploads/settings assets/uploads/avatars \
 && chown -R www-data:www-data logs assets/uploads \
 && chmod -R 775 logs assets/uploads

COPY docker/entrypoint.sh /usr/local/bin/madam-cakes-entrypoint
RUN chmod 755 /usr/local/bin/madam-cakes-entrypoint

ENTRYPOINT ["madam-cakes-entrypoint"]
CMD ["apache2-foreground"]
