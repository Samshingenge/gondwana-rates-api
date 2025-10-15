# PHP 8.2 with devcontainer tooling
FROM mcr.microsoft.com/devcontainers/php:8.2

# Install Composer (binary already included in some images; ensure on PATH)
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Optional: curl, git, unzip (usually present)
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip ca-certificates && rm -rf /var/lib/apt/lists/*

# Set workdir to repo root (Codespaces mounts at /workspaces/<repo>)
WORKDIR /workspaces
