#!/bin/sh

echo "Starting server on PORT: $PORT"

if [ -z "$PORT" ]; then
  echo "PORT is not set. Defaulting to 8080"
  PORT=8080
fi

exec php -S 0.0.0.0:$PORT -t public