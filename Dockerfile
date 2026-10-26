FROM php:8.3-apache

# 1. Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd zip pdo pdo_mysql

# 2. Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# 3. Copy the entire project
COPY . /var/www/html/

# 4. Run composer install inside the backend folder
WORKDIR /var/www/html/backend
RUN composer install --no-dev --optimize-autoloader

WORKDIR /var/www/html

# 5. Enable Apache mod_rewrite and listen on Railway's PORT
RUN a2enmod rewrite
RUN sed -i 's/Listen 80/Listen ${PORT}/g' /etc/apache2/ports.conf

# 6. FIX: Disable conflicting MPMs and enable the required one
RUN a2dismod mpm_event mpm_worker
RUN a2enmod mpm_prefork

# 7. Start Apache
CMD ["apache2-foreground"]
