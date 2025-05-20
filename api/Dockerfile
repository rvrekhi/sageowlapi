# Use Ubuntu 22.04 as the base image
FROM ubuntu:22.04

# Avoid prompts from apt
ENV DEBIAN_FRONTEND=noninteractive
ENV COMPOSER_ALLOW_SUPERUSER=1

# Set working directory
WORKDIR /var/www/api

# Install system dependencies
RUN apt-get update -y && \
    apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libpng-dev \
    curl \
    software-properties-common \
    apt-transport-https \
    ca-certificates \
    gnupg \
    g++ \
    make \
    nginx \
    python3 \
    python3-pip

# Add PHP repository
RUN add-apt-repository ppa:ondrej/php -y

# Update package list after adding new repository
RUN apt-get update -y

# Install PHP and required extensions
RUN apt-get install -y \
    php7.4 \
    php7.4-fpm \
    php7.4-cli \
    php7.4-common \
    php7.4-mysql \
    php7.4-xml \
    php7.4-mbstring \
    php7.4-curl \
    php7.4-zip \
    php7.4-gd \
    php7.4-intl \
    php7.4-bcmath \
    php7.4-soap \
    php7.4-opcache

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install timezonefinder Python library
RUN pip3 install timezonefinder

# Clean up after installation
RUN apt-get clean && \
    rm -rf /var/lib/apt/lists/*

# Copy composer.json and composer.lock
COPY composer.json ./

# Install composer dependencies
RUN set -eux; \
    composer install --verbose --no-interaction; \
    composer clear-cache

# Copy rest of the application
COPY . .

# After copying application files but before clearing cache
RUN mkdir -p /var/www/api/var/cache /var/www/api/var/log && \
    chown -R www-data:www-data /var/www/api/var && \
    chmod -R 777 /var/www/api/var

# Run composer dump-autoload optimizing for production
RUN set -eux; \
    composer dump-autoload --optimize;

# Set proper permissions


# Configure Nginx
RUN echo ' \
server { \
    listen 9393; \
    root /var/www/api/public; \
    index index.php index.html index.htm; \
    server_name _; \
    \
    access_log /var/log/nginx/access.log; \
    error_log /var/log/nginx/error.log; \
    \
    location / { \
        try_files $uri $uri/ /index.php?$query_string; \
    } \
    \
    location ~ \.php$ { \
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock; \
        fastcgi_index index.php; \
        include fastcgi_params; \
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; \
    } \
}' > /etc/nginx/sites-available/default

# Configure PHP-FPM logging
RUN sed -i 's/;error_log = php_errors.log/error_log = \/var\/log\/php7.4-fpm.log/' /etc/php/7.4/fpm/php-fpm.conf

# Create log directories and set permissions
RUN mkdir -p /var/log/nginx /var/log/php7.4-fpm && \
    chown -R www-data:www-data /var/log/nginx /var/log/php7.4-fpm

# Install Symfony assets
RUN php bin/console assets:install --symlink

# Clear Symfony cache
RUN php bin/console cache:clear

# Expose port
EXPOSE 9393

# Start Nginx and PHP-FPM with logging
CMD service php7.4-fpm start && \
    tail -f /var/log/nginx/error.log /var/log/nginx/access.log /var/log/php7.4-fpm.log & \
    nginx -g 'daemon off;'
