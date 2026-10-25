# Use the official PHP image with Apache
FROM php:8.3-apache

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd zip pdo pdo_mysql

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set the base working directory
WORKDIR /var/www/html

# Copy the entire project
COPY . /var/www/html/

# Run composer install inside the backend folder where composer.json lives
WORKDIR /var/www/html/backend
RUN composer install --no-dev --optimize-autoloader

# Go back to the root
WORKDIR /var/www/html

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Change Apache to listen on the port Railway provides
RUN sed -i 's/Listen 80/Listen ${PORT}/g' /etc/apache2/ports.conf

# Start Apache
CMD ["apache2-foreground"]