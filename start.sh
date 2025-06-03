#!/bin/sh

PORT=9000
echo "Starting server on PORT: $PORT"
exec php -S 0.0.0.0:$PORT -t public
php bin/console cache:clear --env=prod || true
php bin/console assets:install public --env=prod || true
