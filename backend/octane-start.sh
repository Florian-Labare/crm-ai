#!/bin/bash

# Script de démarrage rapide pour Laravel Octane
# Usage: ./octane-start.sh [options]

set -e

echo "🚀 Démarrage de Laravel Octane avec Swoole..."
echo ""

# Vérifier que Swoole est installé
if ! php -m | grep -q swoole; then
    echo "❌ ERREUR: L'extension Swoole n'est pas installée."
    echo ""
    echo "Pour l'installer :"
    echo "  - macOS: brew install swoole"
    echo "  - Ubuntu: sudo apt-get install php-swoole"
    echo "  - PECL: pecl install swoole"
    echo ""
    exit 1
fi

echo "✅ Extension Swoole détectée"
echo ""

# Cache des configurations pour de meilleures performances
echo "📦 Optimisation des caches..."
php artisan config:cache
php artisan route:cache
echo ""

# Démarrer Octane
echo "🔥 Lancement d'Octane..."
echo "    Serveur: Swoole"
echo "    Host: 0.0.0.0"
echo "    Port: 8000"
echo "    Workers: auto"
echo ""
echo "👉 Accédez à votre application sur: http://localhost:8000"
echo "👉 Appuyez sur Ctrl+C pour arrêter"
echo ""

# Démarrer avec watch si --watch est passé en argument
if [ "$1" == "--watch" ]; then
    echo "👀 Mode watch activé - redémarrage automatique lors des modifications"
    php artisan octane:start --server=swoole --host=0.0.0.0 --port=8000 --watch
else
    php artisan octane:start --server=swoole --host=0.0.0.0 --port=8000 --workers=auto --task-workers=auto --max-requests=500
fi
