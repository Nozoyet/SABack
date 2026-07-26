FROM php:8.2-apache

WORKDIR /var/www/html

# 1. Instalar dependencias del sistema y extensiones de PHP necesarias
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        zip \
        gd

# 2. Copiar Composer oficial
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 3. Copiar el código del proyecto
COPY . .

# 4. Instalar paquetes de Composer sin desarrollo
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# 5. Configurar permisos completos para Apache en storage y bootstrap/cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 777 /var/www/html/storage /var/www/html/bootstrap/cache

# 6. Habilitar mod_rewrite y redirigir el DocumentRoot a public/
RUN a2enmod rewrite
RUN sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf

# 7. Limpiar caché previa de Laravel por si se subió algún archivo local
RUN php artisan config:clear || true
# Crear carpeta de logs y archivo por si no existen
RUN mkdir -p /var/www/html/storage/logs \
    && touch /var/www/html/storage/logs/laravel.log \
    && chmod -R 777 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80

CMD ["sh", "-c", "php artisan config:clear && php artisan cache:clear && apache2-foreground"]