FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    libsqlite3-dev curl \
    && docker-php-ext-install pdo pdo_sqlite \
    && apt-get clean

WORKDIR /app
COPY . .

RUN mkdir -p /app/data && chmod 777 /app/data

CMD php -S 0.0.0.0:${PORT:-8080} -t /app /app/index.php
