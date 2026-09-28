#!/usr/bin/env bash
# safe-artisan.sh: Envoltorio seguro para ejecutar comandos Artisan sin prompts bloqueantes
CMD="$1"
shift

if [ -z "$CMD" ]; then
    echo "Uso: safe-artisan.sh <comando-artisan> [argumentos...]"
    exit 1
fi

case "$CMD" in
    "migrate:fresh"|"migrate:reset"|"db:wipe")
        if [ "$APP_ENV" = "production" ]; then
            echo "❌ ERROR: Prohibido ejecutar comandos destructivos en entorno de producción."
            exit 1
        fi
        php artisan "$CMD" --no-interaction "$@"
        ;;
    *)
        php artisan "$CMD" --no-interaction "$@"
        ;;
esac
