FROM mcr.microsoft.com/vscode/devcontainers/php:8.1

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /workspaces/app
WORKDIR /var/www/html
COPY backend/public ./public
COPY backend/src ./src
