#!/bin/sh
set -e

# Instala as dependências se o vendor ainda não existir
if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

# Gera a APP_KEY apenas na primeira execução
if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

php artisan migrate --force

exec "$@"
