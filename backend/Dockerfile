FROM php:8.3-cli

# Install system dependencies and the PDO MySQL extension
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    && docker-php-ext-install zip pdo pdo_mysql

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy the backend files
COPY . /app/

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Start the WebSocket server (NOT Apache)
CMD ["php", "ws-server.php"]