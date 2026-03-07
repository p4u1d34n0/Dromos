FROM php:8.3-cli

# Install build dependencies for OpenSwoole
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    libssl-dev \
    libc-ares-dev \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Install OpenSwoole
RUN pecl install openswoole && docker-php-ext-enable openswoole

# Enable OPcache with JIT for max performance
RUN docker-php-ext-install opcache
COPY benchmark/php.ini /usr/local/etc/php/conf.d/dromos.ini

WORKDIR /app
