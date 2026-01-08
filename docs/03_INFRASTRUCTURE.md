# Infrastructure Actuelle & Déploiement

## Vue d'ensemble

**Environnement :** Docker Compose (développement + production possible)
**Orchestration :** Docker Compose v2+ (sans version key)
**Réseau :** Bridge network par défaut
**Volumes persistants :** 2 volumes Docker (mariadb_data, redis_data)

## Architecture des Conteneurs

```
┌─────────────────────────────────────────────────────────────────┐
│                         Docker Host                              │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐         │
│  │   frontend   │  │   backend    │  │ queue-worker │         │
│  │ (Vite React) │  │(Laravel+Py)  │  │  (Laravel)   │         │
│  │   :5173      │  │   :80        │  │              │         │
│  └───────┬──────┘  └───────┬──────┘  └──────┬───────┘         │
│          │                 │                 │                  │
│          │                 └─────────┬───────┘                  │
│          │                           │                          │
│          │         ┌─────────────────┴────────────┐            │
│          │         │                               │            │
│    ┌─────▼─────┐  ┌▼──────────┐  ┌───────▼──────┐│            │
│    │    db     │  │   redis    │  │  mailhog     ││            │
│    │ MariaDB   │  │  (cache+   │  │   :1025      ││            │
│    │  :3306    │  │  queues)   │  │   :8025      ││            │
│    └───────────┘  └────────────┘  └──────────────┘│            │
│                                                     │            │
│    ┌──────────────┐  ┌──────────────┐            │            │
│    │  phpmyadmin  │  │  gotenberg   │            │            │
│    │    :8080     │  │    :3001     │            │            │
│    └──────────────┘  └──────────────┘            │            │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

## Détail des Services

### 1. backend (Laravel + Apache + Python)

**Container name :** `laravel_app`
**Image :** Build custom (PHP 8.3 + Apache + Python 3)
**Ports :** 8000:80 (configurable via APP_PORT)
**Restart policy :** unless-stopped

#### Dockerfile

```dockerfile
FROM php:8.3-apache

# Extensions PHP
RUN docker-php-ext-install pdo pdo_mysql gd mbstring exif pcntl bcmath opcache zip

# Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Swoole extension (Laravel Octane)
RUN pecl install swoole && docker-php-ext-enable swoole

# Python 3 + FFmpeg
RUN apt-get install python3 python3-pip python3-venv ffmpeg

# Dependencies Python (Whisper, Pyannote)
RUN pip3 install -r requirements.txt

# Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Composer install
RUN composer install --no-interaction --optimize-autoloader

# Apache config
COPY ./docker/apache/000-default.conf /etc/apache2/sites-available/
RUN a2enmod rewrite

# PHP custom config
COPY ./docker/php/custom.ini /usr/local/etc/php/conf.d/

# Entrypoint
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]
```

#### Volumes montés

```yaml
volumes:
  - ./backend:/var/www/html          # Code source
  - ./backend/.env:/var/www/html/.env # Config
```

#### Variables d'environnement critiques

```bash
APP_KEY=                    # Chiffrement Laravel
DB_HOST=db                  # Nom service Docker
DB_DATABASE=crm_ai
DB_USERNAME=root
DB_PASSWORD=                # À définir
REDIS_HOST=redis            # Nom service Docker
QUEUE_CONNECTION=redis
CACHE_STORE=redis
OPENAI_API_KEY=sk-proj-... # Clé API OpenAI
HUGGINGFACE_TOKEN=hf_...   # Token HuggingFace
TRANSCRIPTION_MODE=openai  # openai | whisper_local
```

#### Dépendances Python (requirements.txt)

```
openai-whisper==20231117
pyannote.audio==3.1.1
torch==2.1.2
torchaudio==2.1.2
faster-whisper==0.10.0
```

#### Configuration Apache (000-default.conf)

```apache
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html/public

    <Directory /var/www/html/public>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Upload limits
    LimitRequestBody 209715200  # 200 MB

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
```

#### Configuration PHP (custom.ini)

```ini
[PHP]
upload_max_filesize = 200M
post_max_size = 200M
max_execution_time = 300
max_input_time = 300
memory_limit = 512M

; OpCache
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
```

#### docker-entrypoint.sh

```bash
#!/bin/bash
set -e

# Attendre que la BDD soit prête
until php artisan db:show 2>/dev/null; do
  echo "Waiting for database..."
  sleep 2
done

# Migrations automatiques
php artisan migrate --force

# Cache optimization
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Démarrer Apache
exec "$@"
```

---

### 2. frontend (Vite React)

**Container name :** `crm_ai_frontend`
**Image :** Build custom (Node 20 Alpine)
**Ports :** 5173:5173
**Restart policy :** unless-stopped

#### Dockerfile.dev

```dockerfile
FROM node:20-alpine

WORKDIR /app

COPY package*.json ./
RUN npm install

COPY . .

EXPOSE 5173

CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0", "--port", "5173"]
```

#### Volumes montés

```yaml
volumes:
  - ./frontend:/app
  - /app/node_modules    # Volume anonyme (évite conflit local)
```

#### Variables d'environnement

```bash
VITE_API_URL=http://localhost:8000/api
VITE_API_KEY=la-guigz-key
```

---

### 3. db (MariaDB 11)

**Container name :** `mariadb_db`
**Image :** mariadb:11 (officielle)
**Ports :** 3307:3306 (pour éviter conflit local)
**Restart policy :** always

#### Configuration

```yaml
environment:
  MARIADB_ROOT_PASSWORD: root          # À changer en production
  MARIADB_DATABASE: crm_ai
  MARIADB_USER: root
  MARIADB_PASSWORD: root
volumes:
  - mariadb_data:/var/lib/mysql        # Volume persistant
```

#### Optimisations recommandées (my.cnf)

```ini
[mysqld]
# InnoDB
innodb_buffer_pool_size = 1G           # 70% RAM disponible
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2     # Performance (perte 1s data si crash)
innodb_file_per_table = 1

# Query cache
query_cache_type = 1
query_cache_size = 128M

# Connections
max_connections = 200                   # Ajuster selon charge

# Slow query log
slow_query_log = 1
long_query_time = 2
```

---

### 4. redis (Cache & Queues)

**Container name :** `redis_cache`
**Image :** redis:7-alpine (officielle)
**Ports :** 6379:6379
**Restart policy :** unless-stopped

#### Configuration

```yaml
command: redis-server --appendonly yes  # AOF persistence
volumes:
  - redis_data:/data                    # Volume persistant
healthcheck:
  test: ["CMD", "redis-cli", "ping"]
  interval: 10s
  timeout: 3s
  retries: 3
```

#### Optimisations Redis (redis.conf)

```conf
# Memory
maxmemory 512mb
maxmemory-policy allkeys-lru           # Éviction LRU

# Persistence
appendonly yes
appendfsync everysec

# Databases
databases 2                             # DB 0 = queues, DB 1 = cache
```

---

### 5. queue-worker (Laravel Worker)

**Container name :** `laravel_queue_worker`
**Image :** Build custom (même que backend)
**Restart policy :** unless-stopped

#### Configuration

```yaml
command: sh -c "sleep 5 && php artisan queue:work redis --verbose --tries=3 --timeout=300"
depends_on:
  backend:
    condition: service_started
  db:
    condition: service_started
  redis:
    condition: service_healthy
```

#### Paramètres queue

- **tries=3 :** 3 tentatives max avant failed
- **timeout=300 :** 5 minutes max par job
- **sleep=3 :** 3s entre 2 polls si queue vide

#### Monitoring queues

```bash
# Voir les jobs en attente
docker compose exec backend php artisan queue:monitor

# Statistiques
docker compose exec backend php artisan queue:restart
```

---

### 6. mailhog (SMTP test server)

**Container name :** `mailhog`
**Image :** mailhog/mailhog:latest
**Ports :** 1025 (SMTP), 8025 (Web UI)

Interface web : http://localhost:8025

---

### 7. gotenberg (Conversion PDF)

**Container name :** `gotenberg`
**Image :** gotenberg/gotenberg:8
**Ports :** 3001:3000
**Restart policy :** unless-stopped

#### Configuration

```yaml
environment:
  DISABLE_GOOGLE_CHROME: "1"           # Utilise LibreOffice seulement
  DEFAULT_WAIT_TIMEOUT: "30"
command:
  - "gotenberg"
  - "--api-timeout=30s"
  - "--api-port=3000"
  - "--log-level=info"
```

#### Utilisation

```bash
# Conversion Word → PDF
curl --request POST \
  --url http://localhost:3001/forms/libreoffice/convert \
  --form files=@document.docx \
  --output result.pdf
```

---

### 8. phpmyadmin (Admin DB)

**Container name :** `phpmyadmin`
**Image :** phpmyadmin:latest
**Ports :** 8080:80

Interface web : http://localhost:8080
Credentials : root / root (défaut)

---

## Volumes Docker

### mariadb_data

- **Type :** Named volume
- **Chemin container :** /var/lib/mysql
- **Contenu :** Bases de données MariaDB
- **Taille estimée :** 250 MB/cabinet/an (données) + 80 GB/an (audio metadata)
- **Backup :** Dump SQL quotidien recommandé

### redis_data

- **Type :** Named volume
- **Chemin container :** /data
- **Contenu :** AOF persistence Redis (cache + queues)
- **Taille estimée :** 100-500 MB
- **Backup :** Optionnel (données reconstruisibles)

---

## Réseau Docker

**Mode :** Bridge network par défaut
**DNS interne :** Résolution par nom de service (backend, db, redis, etc.)

### Communication inter-services

```
frontend → backend:80 (API REST)
backend → db:3306 (MySQL)
backend → redis:6379 (Cache + Queues)
backend → gotenberg:3000 (Conversion PDF)
queue-worker → db:3306
queue-worker → redis:6379
```

---

## Démarrage & Gestion

### Commandes principales

```bash
# Build et démarrage
docker compose up -d --build

# Logs en temps réel
docker compose logs -f backend
docker compose logs -f queue-worker

# Arrêt
docker compose down

# Arrêt avec suppression volumes (⚠️ PERTE DONNÉES)
docker compose down -v

# Rebuild un service spécifique
docker compose up -d --build backend

# Restart un service
docker compose restart backend
```

### Migrations & Initialisation

```bash
# Générer APP_KEY
docker compose exec backend php artisan key:generate

# Migrations
docker compose exec backend php artisan migrate

# Seed données test
docker compose exec backend php artisan db:seed

# Cache clear
docker compose exec backend php artisan cache:clear
docker compose exec backend php artisan config:clear
docker compose exec backend php artisan route:clear
```

### Vérifications santé

```bash
# Vérifier FFmpeg
docker compose exec backend which ffmpeg

# Vérifier Pyannote
docker compose exec backend python3 -c "import pyannote.audio"

# Vérifier Redis
docker compose exec redis redis-cli ping

# Vérifier BDD
docker compose exec backend php artisan db:show
```

---

## Ressources Système

### Consommation actuelle (développement)

| Service | CPU (idle) | CPU (load) | RAM | Disk |
|---------|------------|------------|-----|------|
| backend | 5% | 50-80% | 300 MB | - |
| frontend | 2% | 10% | 200 MB | - |
| db | 1% | 10-30% | 400 MB | 250 MB |
| redis | <1% | 5% | 50 MB | 100 MB |
| queue-worker | 5% | 60-100% | 300 MB | - |
| gotenberg | <1% | 20% | 100 MB | - |
| mailhog | <1% | <1% | 50 MB | - |
| phpmyadmin | <1% | 5% | 50 MB | - |

**Total développement :** ~1.5 GB RAM, ~2 GB disque (sans données)

### Consommation estimée (production - 2000 clients)

| Ressource | Minimum | Recommandé | Optimal |
|-----------|---------|------------|---------|
| **CPU** | 4 cores | 8 cores | 16 cores |
| **RAM** | 8 GB | 16 GB | 32 GB |
| **Disk** | 200 GB SSD | 500 GB SSD | 1 TB NVMe |
| **Network** | 100 Mbps | 1 Gbps | 10 Gbps |

---

## Sécurité

### Actuellement implémenté

✅ **Réseau isolé :** Bridge network Docker
✅ **Secrets :** Variables d'environnement (.env)
✅ **HTTPS :** Non (recommandé en production via reverse proxy)
✅ **Firewall :** Ports internes non exposés (sauf nécessaires)

### Recommandations production

⚠️ **Secrets management :** Docker secrets ou Vault
⚠️ **Reverse proxy :** Nginx/Traefik avec HTTPS (Let's Encrypt)
⚠️ **WAF :** ModSecurity ou Cloudflare
⚠️ **Rate limiting :** Nginx ou Cloudflare
⚠️ **DB credentials :** Utilisateur dédié (pas root)
⚠️ **Redis password :** Activer authentification
⚠️ **Firewall :** UFW ou iptables (autoriser 80, 443 seulement)

---

## Monitoring & Logs

### Logs Docker

```bash
# Logs backend
docker compose logs -f backend

# Logs avec timestamp
docker compose logs -f --timestamps backend

# Dernières 100 lignes
docker compose logs --tail=100 backend

# Tous les services
docker compose logs -f
```

### Logs Laravel

```bash
# Log file
docker compose exec backend tail -f storage/logs/laravel.log

# Pail (log viewer temps réel)
docker compose exec backend php artisan pail
```

### Métriques système

```bash
# Stats containers
docker stats

# Disk usage
docker system df

# Disk usage détaillé
docker system df -v
```

### Monitoring recommandé (production)

- **Application :** Laravel Telescope (dev) + Sentry (prod)
- **Système :** Prometheus + Grafana
- **Logs :** ELK Stack (Elasticsearch + Logstash + Kibana)
- **Uptime :** UptimeRobot ou Pingdom
- **APM :** New Relic ou Datadog

---

## Backup & Disaster Recovery

### Stratégie actuelle

⚠️ **Aucun backup automatisé configuré**

### Stratégie recommandée

#### 1. Base de données

```bash
# Dump quotidien
docker compose exec db mysqldump -u root -p crm_ai > backup_$(date +%Y%m%d).sql

# Dump avec gzip
docker compose exec db mysqldump -u root -p crm_ai | gzip > backup_$(date +%Y%m%d).sql.gz

# Restore
docker compose exec -i db mysql -u root -p crm_ai < backup_20260102.sql
```

**Automatisation :** Cron job + rotation 30 jours

#### 2. Volumes Docker

```bash
# Backup volume mariadb_data
docker run --rm -v mariadb_data:/source -v $(pwd):/backup alpine tar czf /backup/mariadb_data_$(date +%Y%m%d).tar.gz -C /source .

# Restore
docker run --rm -v mariadb_data:/target -v $(pwd):/backup alpine tar xzf /backup/mariadb_data_20260102.tar.gz -C /target
```

#### 3. Fichiers audio/documents

```bash
# Sync vers S3
docker compose exec backend aws s3 sync storage/app/recordings s3://bucket/recordings --delete
docker compose exec backend aws s3 sync storage/app/documents s3://bucket/documents --delete
```

**Automatisation :** Cron job + lifecycle S3 (Glacier après 6 mois)

#### 4. Backup complet

**Recommandation :** Snapshot serveur + backup BDD + sync fichiers S3
**Fréquence :**
- Snapshot : Hebdomadaire
- BDD : Quotidien (incrémental toutes les 6h)
- Fichiers : Sync temps réel ou horaire
**Rétention :**
- 7 derniers jours
- 4 dernières semaines
- 12 derniers mois

---

## Performance & Optimisations

### PHP OpCache (custom.ini)

```ini
opcache.enable=1
opcache.memory_consumption=256          # 256 MB cache bytecode
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2               # Check fichiers tous les 2s
opcache.validate_timestamps=1           # 0 en production
```

### Laravel Octane (Swoole)

**Configuration actuelle :**
```env
OCTANE_SERVER=swoole
OCTANE_MAX_REQUESTS=500                # Recycler worker après 500 req
OCTANE_WORKERS=auto                    # Auto = nb CPU cores
OCTANE_TASK_WORKERS=auto
```

**Démarrage :**
```bash
docker compose exec backend php artisan octane:start --host=0.0.0.0 --port=8000
```

**Performance gain :** 10-100x vs PHP-FPM

### Redis persistence

**AOF (Append-Only File) :**
- Écrit chaque opération sur disque
- Garantit durabilité données
- Reconstruction rapide au redémarrage

**Alternative RDB (snapshot) :**
```conf
save 900 1      # Snapshot si 1 write en 15 min
save 300 10     # Snapshot si 10 writes en 5 min
save 60 10000   # Snapshot si 10k writes en 1 min
```

### Mise en cache

**Stratégie :**
- Config : `php artisan config:cache`
- Routes : `php artisan route:cache`
- Views : `php artisan view:cache`
- Queries : Cache Redis avec TTL

---

## Déploiement Production

### Prérequis serveur

- **OS :** Ubuntu 22.04 LTS ou Debian 12
- **Docker :** 24.0+ et Docker Compose v2+
- **Accès :** SSH avec clé publique
- **Domaine :** SSL/TLS (Let's Encrypt)

### Architecture production recommandée

```
Internet
   │
   ↓
[Cloudflare CDN + WAF]
   │
   ↓
[Load Balancer Nginx/HAProxy]
   │
   ├─→ [Backend Instance 1] ──┐
   ├─→ [Backend Instance 2] ──┼─→ [MariaDB Master]
   └─→ [Backend Instance N] ──┘        │
                                       ↓
                              [MariaDB Slave (read)]
                                       │
                                       ↓
                              [Redis Cluster 3 nodes]
                                       │
                                       ↓
                              [Object Storage S3/MinIO]
```

### Checklist déploiement

- [ ] Variables d'environnement production (.env)
- [ ] APP_DEBUG=false
- [ ] Secrets sécurisés (APP_KEY, DB_PASSWORD, API keys)
- [ ] HTTPS configuré (Nginx reverse proxy + Let's Encrypt)
- [ ] Firewall activé (UFW : 80, 443, 22 seulement)
- [ ] Backup automatisé (BDD + fichiers)
- [ ] Monitoring (Sentry, Prometheus, etc.)
- [ ] Logs centralisés (ELK ou Loki)
- [ ] Redis password activé
- [ ] DB user dédié (pas root)
- [ ] Rate limiting API (throttle)
- [ ] Queue workers avec supervisord
- [ ] Cron jobs (schedule:run)
- [ ] Healthchecks (UptimeRobot)

---

**Version :** 1.0
**Date :** 2026-01-02
**Infrastructure cible :** Multi-cabinets (10-20 simultanés)
