# Laravel Octane - Démarrage Rapide

## 🚀 Démarrage Local

```bash
# Démarrer Octane
./octane-start.sh

# Ou avec mode watch (redémarre automatiquement)
./octane-start.sh --watch

# Ou manuellement
php artisan octane:start --server=swoole --host=0.0.0.0 --port=8000
```

## 🐳 Démarrage Docker

```bash
# Avec Octane
docker-compose -f docker-compose.yml -f docker-compose.octane.yml up -d

# Mode standard (Apache)
docker-compose up -d
```

## 📚 Documentation Complète

Voir [GUIDE_OCTANE.md](../GUIDE_OCTANE.md) pour la documentation complète.

## ⚡ Performance

Octane boost les performances de 10x à 100x comparé à Apache/PHP-FPM traditionnel !

## 🔧 Commandes Utiles

```bash
# Arrêter Octane
php artisan octane:stop

# Redémarrer les workers
php artisan octane:reload

# Statut des workers
php artisan octane:status

# Nettoyer les caches
php artisan config:clear
php artisan route:clear
php artisan cache:clear
```
