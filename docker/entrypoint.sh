#!/usr/bin/env sh
set -e

cd /var/www/html

# Garante que storage e cache estejam usáveis
chmod -R ug+rwx storage bootstrap/cache 2>/dev/null || true

# Copia .env.example para .env se não existir (ambiente dev)
if [ ! -f .env ] && [ -f .env.example ]; then
  cp .env.example .env || true
fi

# Gera APP_KEY se não existir
if [ -z "${APP_KEY}" ]; then
  php artisan key:generate --force >/dev/null 2>&1 || true
fi

# Link de storage (ignora se já existir)
if [ ! -L public/storage ]; then
  php artisan storage:link >/dev/null 2>&1 || true
fi

# Gera documentação Swagger (se configurado)
php artisan l5-swagger:generate >/dev/null 2>&1 || true

# Otimizações leves
php artisan config:cache >/dev/null 2>&1 || true
php artisan route:cache >/dev/null 2>&1 || true

echo "Starting Laravel at 0.0.0.0:${PORT:-8000}"
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}