FROM php:8.2-apache

# Metadata
LABEL maintainer="OMIT Systems"
LABEL description="Sistem Laporan Serah Terima Shift Operasional (PHP 8.2 + Apache + MySQL + Redis)"

# Install System Dependencies & Build Tools
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libwebp-dev \
    libzip-dev \
    zip \
    unzip \
    curl \
    default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# Configure & Install PHP Core Extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mysqli \
        gd \
        zip \
        opcache

# Install & Enable Redis via PECL (for high-speed cache & session)
RUN pecl install redis \
    && docker-php-ext-enable redis

# Enable Apache Modules
RUN a2enmod rewrite headers expires

# Copy Optimized PHP Configuration & Apache VirtualHost
COPY docker/php.ini $PHP_INI_DIR/conf.d/custom-php.ini
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Set Working Directory
WORKDIR /var/www/html

# Copy Project Files
COPY . /var/www/html/

# Create required writable directories & set ownership
RUN mkdir -p /var/www/html/uploads /var/www/html/storage/backups \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/uploads /var/www/html/storage/backups

# Expose HTTP Port
EXPOSE 80

# Default Command
CMD ["apache2-foreground"]
