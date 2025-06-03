#!/bin/sh

if [ -z "$PORT" ]; then
  echo "Railway PORT env variable not set. Exiting."
  exit 1
fi

echo "Starting server on PORT: $PORT"
php -S 0.0.0.0:$PORT -t public