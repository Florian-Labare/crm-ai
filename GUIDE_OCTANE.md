# Guide Laravel Octane - Performance Boost

## 📖 Qu'est-ce que Laravel Octane ?

Laravel Octane booste les performances de votre application Laravel en la servant via des serveurs d'applications hautes performances comme Swoole ou RoadRunner. Il garde votre application en mémoire entre les requêtes, éliminant le bootstrap de Laravel à chaque fois.

### Avantages
- ⚡ **Performances accrues** : 10x à 100x plus rapide que PHP-FPM/Apache traditionnel
- 🔄 **Application persistante** : Le framework reste chargé en mémoire
- 🚀 **Gestion concurrente** : Traite plusieurs requêtes simultanément
- 💾 **Cache mémoire** : Réutilise les ressources entre les requêtes

## 🔧 Installation

Octane a été installé dans ce projet avec Swoole. Voici les composants ajoutés :

```bash
# Package Composer
composer require laravel/octane

# Configuration
php artisan octane:install --server=swoole
```

## 🐳 Utilisation avec Docker

### Méthode 1 : Docker Compose avec Octane

Pour démarrer le projet avec Octane au lieu d'Apache :

```bash
# Arrêter les containers existants
docker-compose down

# Reconstruire avec Swoole
docker-compose build

# Démarrer avec Octane
docker-compose -f docker-compose.yml -f docker-compose.octane.yml up -d

# Vérifier les logs
docker-compose logs -f backend
```

### Méthode 2 : Mode Standard (Apache)

Pour revenir au mode Apache standard :

```bash
docker-compose down
docker-compose up -d
```

## 💻 Utilisation en Local (sans Docker)

### Prérequis

1. **Installer l'extension Swoole**

```bash
# Sur macOS (avec Homebrew)
brew install swoole

# Sur Ubuntu/Debian
sudo apt-get install php-swoole

# Avec PECL (toutes plateformes)
pecl install swoole
```

2. **Activer Swoole dans php.ini**

```ini
extension=swoole.so
```

### Démarrer Octane

```bash
cd backend

# Démarrer le serveur Octane
php artisan octane:start

# Avec options personnalisées
php artisan octane:start \
  --host=0.0.0.0 \
  --port=8000 \
  --workers=4 \
  --task-workers=6 \
  --max-requests=500
```

### Mode Watch (redémarre automatiquement lors de modifications)

```bash
php artisan octane:start --watch
```

## ⚙️ Configuration

### Fichier .env

Les variables d'environnement pour Octane :

```env
# Serveur Octane (swoole, roadrunner, frankenphp)
OCTANE_SERVER=swoole

# HTTPS (true/false)
OCTANE_HTTPS=false

# Nombre de requêtes avant redémarrage d'un worker
OCTANE_MAX_REQUESTS=500

# Nombre de workers (auto = nb de CPU)
OCTANE_WORKERS=auto

# Nombre de task workers pour les tâches async
OCTANE_TASK_WORKERS=auto
```

### Fichier config/octane.php

Configuration avancée disponible dans `config/octane.php` :

- Listeners d'événements
- Tables Swoole (cache partagé entre workers)
- Warmers (précharger des services)
- Garbage collection

## 🔥 Optimisations

### Cache de configuration

Avant de démarrer Octane en production :

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Workers

- `OCTANE_WORKERS=auto` détecte automatiquement le nombre de CPU
- En production, utilisez `OCTANE_WORKERS=4` (ou plus selon vos ressources)
- `OCTANE_TASK_WORKERS` gère les tâches asynchrones (upload de fichiers, etc.)

### Max Requests

- `OCTANE_MAX_REQUESTS=500` redémarre un worker après 500 requêtes
- Utile pour éviter les fuites mémoire
- En production, augmentez à 1000-5000

## ⚠️ Points d'attention

### Variables globales et statiques

Octane garde l'application en mémoire, donc :

```php
// ❌ MAUVAIS - Accumulera des données entre les requêtes
class MyController {
    public static $cache = [];

    public function index() {
        static::$cache[] = request()->user();
    }
}

// ✅ BON - Utiliser le cache Laravel ou les services injectés
class MyController {
    public function index(Request $request) {
        Cache::put('user', $request->user());
    }
}
```

### Services singleton

Certains services doivent être recréés pour chaque requête. Vérifiez `config/octane.php` pour la liste des services à flush.

### Sessions et Auth

Les sessions et l'authentification fonctionnent normalement avec Octane, mais assurez-vous de ne pas stocker d'état dans des variables de classe.

## 🧪 Tests

### Vérifier que Swoole est installé

```bash
php -m | grep swoole
```

### Test de performance simple

```bash
# Avec Apache/PHP-FPM
ab -n 1000 -c 10 http://localhost:8000/api/clients

# Avec Octane
ab -n 1000 -c 10 http://localhost:8000/api/clients
```

Vous devriez observer une amélioration significative avec Octane.

## 🚀 Production

### Démarrage avec Supervisor

Créer un fichier `/etc/supervisor/conf.d/octane.conf` :

```ini
[program:octane]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan octane:start --server=swoole --host=0.0.0.0 --port=8000 --workers=auto --task-workers=auto --max-requests=500
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/octane.log
stopwaitsecs=3600
```

### Redémarrage après déploiement

```bash
php artisan octane:reload
```

## 📊 Monitoring

### Vérifier le statut des workers

```bash
# Logs en temps réel
docker-compose logs -f backend

# Stats Swoole
php artisan octane:status
```

### Métriques importantes

- **Requests/sec** : Nombre de requêtes par seconde
- **Memory usage** : Utilisation mémoire par worker
- **Worker restarts** : Fréquence de redémarrage des workers

## 🔄 Migration depuis Apache

1. **Tester en développement** : Utilisez `docker-compose.octane.yml` pour tester
2. **Vérifier les logs** : Surveillez les erreurs liées à l'état partagé
3. **Tests de charge** : Comparez les performances
4. **Déployer progressivement** : Commencez avec un serveur de staging

## 📚 Ressources

- [Documentation Laravel Octane](https://laravel.com/docs/11.x/octane)
- [Documentation Swoole](https://www.swoole.co.uk/)
- [Octane GitHub](https://github.com/laravel/octane)

## 🐛 Dépannage

### Erreur "Swoole extension not found"

```bash
# Vérifier l'installation
php -m | grep swoole

# Réinstaller si nécessaire
pecl install swoole
```

### Workers qui crashent

- Vérifiez `storage/logs/octane.log`
- Réduisez `OCTANE_MAX_REQUESTS`
- Augmentez la mémoire PHP dans `php.ini`

### Fuites mémoire

- Utilisez `OCTANE_MAX_REQUESTS=500` ou moins
- Vérifiez les variables statiques/globales
- Utilisez `php artisan octane:reload` régulièrement

## 🎯 Conclusion

Laravel Octane transforme radicalement les performances de votre application Laravel. Utilisez-le en production pour une expérience utilisateur ultra-rapide !
