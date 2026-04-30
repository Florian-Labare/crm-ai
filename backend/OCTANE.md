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
# Avec FrankenPHP (recommandé — HTTP/3, worker mode natif)
docker-compose -f docker-compose.yml -f docker-compose.frankenphp.yml up -d

# Avec Octane/Swoole
docker-compose -f docker-compose.yml -f docker-compose.octane.yml up -d

# Mode standard (Apache)
docker-compose up -d
```

## FrankenPHP

FrankenPHP est un serveur PHP moderne basé sur Caddy avec support HTTP/3, worker mode intégré et performances supérieures à Swoole.

### Démarrage local avec FrankenPHP

```bash
# Installer FrankenPHP via Octane
composer require laravel/octane
php artisan octane:install --server=frankenphp

# Démarrer
php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=8000
```

### Fichiers FrankenPHP

| Fichier | Description |
|---|---|
| `backend/Dockerfile.frankenphp` | Image Docker basée sur `dunglas/frankenphp:latest-php8.3` |
| `backend/docker/frankenphp/Caddyfile` | Configuration Caddy pour Laravel |
| `backend/docker-entrypoint.frankenphp.sh` | Entrypoint avec permissions + cache |
| `docker-compose.frankenphp.yml` | Override docker-compose |

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
