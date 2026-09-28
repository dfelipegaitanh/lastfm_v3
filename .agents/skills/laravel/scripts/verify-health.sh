#!/usr/bin/env bash
set -e

echo "🔍 [1/3] Verificando estilo de código con Laravel Pint..."
./vendor/bin/pint --test

echo "🛣️  [2/3] Verificando integridad del enrutador..."
php artisan route:list --except-vendor > /dev/null

echo "🧪 [3/3] Ejecutando suite de pruebas Pest..."
./vendor/bin/pest --compact

echo "✅ [OK] La aplicación pasó todas las validaciones de salud con éxito."
