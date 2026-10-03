#!/bin/bash

echo "[foxidna] Iniciando PHP-FPM..."
php-fpm8.4 -F &
PHP_PID=$!

echo "[foxidna] Iniciando Nginx..."
nginx -g 'daemon off;' &
NGINX_PID=$!

cleanup() {
    echo "[foxidna] Deteniendo servicios..."
    kill -TERM "$PHP_PID" "$NGINX_PID" 2>/dev/null || true
    wait "$PHP_PID" 2>/dev/null || true
    wait "$NGINX_PID" 2>/dev/null || true
}

trap cleanup TERM INT

wait -n "$PHP_PID" "$NGINX_PID"
STATUS=$?

cleanup
exit "$STATUS"
