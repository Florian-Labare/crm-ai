# Scaling Strategy - Multi-Cabinets (10-20 Simultanés)

## Executive Summary

### Objectif
Vendre cette solution CRM IA à **10-20 cabinets de courtage** utilisant l'outil **simultanément**, avec une infrastructure **stable, performante et rentable**.

### Hypothèses de charge

**Par cabinet moyen :**
- 500-2000 clients actifs
- 5-20 utilisateurs (courtiers)
- 50-200 enregistrements audio/mois
- 500-2000 documents générés/mois
- Pics d'activité : 9h-12h et 14h-18h (heures bureau)

**Total (20 cabinets) :**
- **40 000 clients** actifs
- **400 utilisateurs** simultanés (pic)
- **4000 enregistrements audio/mois** (~130/jour)
- **40 000 documents/mois** (~1300/jour)

### Contraintes critiques

1. **Isolation données :** RGPD strict, aucune fuite entre cabinets
2. **Performance IA :** Temps traitement audio < 2 minutes
3. **Disponibilité :** SLA 99.5% minimum (43h downtime/an max)
4. **Coûts IA :** OpenAI API peut représenter 60-80% des coûts variables
5. **Stockage :** ~1.6 TB audio + 1.2 TB documents par an

---

## I. Goulots d'Étranglement Actuels

### 🔴 CRITIQUE

#### 1. Base de Données Single-Node

**Problème :**
- MariaDB single-instance ne peut pas scaler horizontalement
- Tous les 20 cabinets partagent la même instance
- Connexions limitées (max_connections = 200 par défaut)
- Pas de réplication → SPOF (Single Point of Failure)

**Impact à 20 cabinets :**
- **Connexions :** 400 users × 10 connexions/user = 4000 connexions (dépassement)
- **CPU :** Requêtes complexes (Eloquent + relations) saturent CPU
- **Disk I/O :** Écritures simultanées (audio records, clients) saturent disque HDD
- **Latence :** Requêtes >1s aux heures de pointe

**Solution :**
→ Voir section II.A (Sharding + Réplication)

---

#### 2. Stockage Fichiers Local (Volume Docker)

**Problème :**
- Audio (10 MB/fichier × 4000/mois = 40 GB/mois)
- Documents (2 MB/fichier × 40 000/mois = 80 GB/mois)
- Volume local → pas de réplication, pas de CDN

**Impact à 20 cabinets :**
- **Espace disque :** ~1.4 TB/an (audio + docs)
- **Bande passante :** Download documents sature bande passante serveur
- **Backup :** Backup de 1.4 TB/an = coût + temps énorme

**Solution :**
→ Voir section II.B (Object Storage S3)

---

#### 3. API OpenAI - Coûts Variables

**Problème :**
- Whisper : $0.006/minute audio
- GPT-4o-mini : ~$0.0001-0.0005/requête extraction

**Impact à 20 cabinets :**
- **Audio (4000 enregistrements/mois × 10 min × $0.006) :** $240/mois
- **Extraction (4000 enreg × 10 extracteurs × $0.0003) :** $12/mois
- **Total OpenAI :** ~$250-300/mois = **$3000-3600/an**

**Risques :**
- Facture imprévisible si usage explose
- Rate limits OpenAI (50 req/min sur plan standard)

**Solution :**
→ Voir section II.E (Whisper local + cache)

---

#### 4. Queue Worker Unique

**Problème :**
- 1 seul worker traite tous les jobs (ProcessAudioRecording)
- Job audio = 60-120s de traitement (Whisper + GPT)
- **Capacité max :** 720-1440 jobs/jour (1 worker)

**Impact à 20 cabinets :**
- **Besoin :** 4000 jobs/mois ÷ 30 jours = ~130 jobs/jour
- **Avec 1 worker :** OK pour la moyenne, mais **pics** dépassent capacité
- **File d'attente :** Délai d'attente >30 minutes aux heures de pointe

**Solution :**
→ Voir section II.C (Multiple queue workers)

---

### 🟠 IMPORTANT

#### 5. Frontend Vite Dev Server (Production)

**Problème :**
- Vite dev server n'est PAS conçu pour production
- Pas de minification, pas de caching agressif
- Consomme plus de ressources

**Solution :**
→ Build production (`npm run build`) + Nginx static files

---

#### 6. Absence de Load Balancer

**Problème :**
- Backend single-instance ne peut pas distribuer charge
- Pas de failover automatique

**Solution :**
→ Voir section II.D (Load balancer + multi-instances)

---

## II. Architecture Cible - Production Multi-Cabinets

### Vue d'ensemble

```
┌────────────────────────────────────────────────────────────────┐
│                         INTERNET                                │
└────────────────────────┬───────────────────────────────────────┘
                         │
                         ↓
         ┌───────────────────────────────┐
         │   Cloudflare CDN + WAF        │ (Protection DDoS, SSL, Cache)
         └───────────────┬───────────────┘
                         │
                         ↓
         ┌───────────────────────────────┐
         │  Load Balancer (Nginx/HAProxy) │ (Round-robin, Health checks)
         └───────────────┬───────────────┘
                         │
         ┌───────────────┴───────────────┐
         │                               │
    ┌────▼─────┐  ┌─────────────┐  ┌────▼─────┐
    │ Backend  │  │  Backend    │  │ Backend  │
    │ Instance │  │  Instance   │  │ Instance │
    │    #1    │  │     #2      │  │    #3    │
    └────┬─────┘  └──────┬──────┘  └────┬─────┘
         │                │              │
         └────────────────┼──────────────┘
                          │
          ┌───────────────┴───────────────────┐
          │                                   │
     ┌────▼─────────┐              ┌──────────▼────────┐
     │ DB Shard #1  │              │  DB Shard #2      │
     │ (Teams 1-10) │              │  (Teams 11-20)    │
     │              │              │                   │
     │  ┌─Master─┐  │              │  ┌─Master─┐      │
     │  └───┬────┘  │              │  └───┬────┘      │
     │      │       │              │      │           │
     │  ┌───▼───┐   │              │  ┌───▼───┐       │
     │  │ Slave │   │              │  │ Slave │       │
     │  └───────┘   │              │  └───────┘       │
     └──────────────┘              └───────────────────┘
          │                                   │
          └───────────────┬───────────────────┘
                          │
                ┌─────────▼──────────┐
                │  Redis Cluster     │
                │  (3 nodes)         │
                │                    │
                │  Cache + Queues    │
                └─────────┬──────────┘
                          │
                ┌─────────▼──────────┐
                │  Object Storage    │
                │  (S3 / MinIO)      │
                │                    │
                │  Audio + Documents │
                └────────────────────┘
```

---

### A. Database Sharding + Réplication

#### 1. Sharding Strategy

**Principe :** Diviser les 20 cabinets sur plusieurs bases de données

**Approche :** Sharding par `team_id` (hash-based)

```sql
-- Shard 1: Teams 1-10
-- Shard 2: Teams 11-20
```

**Avantages :**
- Isolation totale données (RGPD)
- Scalabilité horizontale infinie
- Réduction charge par instance (÷2)

**Implémentation Laravel :**

```php
// config/database.php
'mysql_shard1' => [
    'driver' => 'mysql',
    'host' => env('DB_SHARD1_HOST', 'db-shard1'),
    'database' => env('DB_SHARD1_DATABASE', 'crm_ai_shard1'),
    // ...
],
'mysql_shard2' => [
    'driver' => 'mysql',
    'host' => env('DB_SHARD2_HOST', 'db-shard2'),
    'database' => env('DB_SHARD2_DATABASE', 'crm_ai_shard2'),
    // ...
],
```

```php
// app/Services/ShardingService.php
class ShardingService
{
    public function getConnectionForTeam(int $teamId): string
    {
        // Hash-based sharding
        $shard = ($teamId % 2) + 1; // 2 shards
        return "mysql_shard{$shard}";
    }
}

// Middleware pour sélectionner la connexion
class SelectShardMiddleware
{
    public function handle($request, Closure $next)
    {
        $teamId = auth()->user()->current_team_id;
        $connection = app(ShardingService::class)->getConnectionForTeam($teamId);

        DB::setDefaultConnection($connection);

        return $next($request);
    }
}
```

#### 2. Master-Slave Replication

**Principe :** 1 master (write) + 1+ slaves (read)

**Avantages :**
- Lecture distribuée (slaves)
- Failover automatique (promotion slave → master)
- Backup à chaud (dump sur slave)

**Configuration MariaDB :**

```ini
# Master
[mysqld]
server-id = 1
log-bin = /var/log/mysql/mysql-bin.log
binlog_format = ROW
binlog_do_db = crm_ai_shard1

# Slave
[mysqld]
server-id = 2
relay-log = /var/log/mysql/mysql-relay-bin
read_only = 1
```

**Laravel Read/Write Connections :**

```php
'mysql_shard1' => [
    'read' => [
        'host' => ['slave1.example.com', 'slave2.example.com'],
    ],
    'write' => [
        'host' => ['master.example.com'],
    ],
    // ...
],
```

---

### B. Object Storage (S3 / MinIO)

#### 1. Migration Fichiers Locaux → S3

**Avantages :**
- Stockage infini scalable
- CDN intégré (CloudFront, Cloudflare R2)
- Backup automatique (versioning S3)
- Lifecycle rules (archivage Glacier)
- Coût : ~$0.023/GB/mois (S3 Standard)

**Configuration Laravel :**

```php
// config/filesystems.php
'disks' => [
    's3_audio' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'eu-west-1'),
        'bucket' => env('AWS_AUDIO_BUCKET', 'crm-ai-audio'),
        'url' => env('AWS_AUDIO_URL'),
        'endpoint' => env('AWS_ENDPOINT'), // Pour MinIO
    ],
    's3_documents' => [
        'driver' => 's3',
        'bucket' => env('AWS_DOCUMENTS_BUCKET', 'crm-ai-documents'),
        // ...
    ],
],
```

**Migration code :**

```php
// Avant
Storage::disk('local')->put("recordings/{$filename}", $file);

// Après
Storage::disk('s3_audio')->put("recordings/{$filename}", $file);
$url = Storage::disk('s3_audio')->url("recordings/{$filename}");
```

#### 2. CDN pour Download Documents

**CloudFront (AWS) :**
```
S3 Bucket → CloudFront Distribution → clients.example.com
```

**Avantages :**
- Latence réduite (edge locations)
- Bande passante illimitée
- Cache agressif (TTL 1 an pour documents générés)

#### 3. Lifecycle Rules

```yaml
# Règle S3 Lifecycle
- id: archive-old-audio
  filter:
    prefix: recordings/
  transitions:
    - days: 180
      storage_class: GLACIER
  expiration:
    days: 730  # Suppression après 2 ans
```

**Coûts estimés :**
- **Audio (40 GB/mois) :** $0.92/mois (S3 Standard) → $0.16/mois après 6 mois (Glacier)
- **Documents (80 GB/mois) :** $1.84/mois (S3 Standard)
- **Total :** ~$3-5/mois/cabinet = **$60-100/mois pour 20 cabinets**

---

### C. Multiple Queue Workers

#### 1. Horizontal Scaling Workers

**Actuellement :** 1 worker = 720-1440 jobs/jour
**Besoin :** 4000 jobs/mois ÷ 30 = 130 jobs/jour (moyenne) + pics 300-500 jobs/jour

**Solution :** 3-5 queue workers en parallèle

**Docker Compose :**

```yaml
queue-worker-1:
  <<: *queue-worker-base
  container_name: queue_worker_1

queue-worker-2:
  <<: *queue-worker-base
  container_name: queue_worker_2

queue-worker-3:
  <<: *queue-worker-base
  container_name: queue_worker_3
```

**Ou avec scale :**

```bash
docker compose up -d --scale queue-worker=5
```

#### 2. Prioritized Queues

**Principe :** Séparer les jobs par priorité

```php
// Haute priorité (transcription audio)
ProcessAudioRecording::dispatch($audioRecord)->onQueue('high');

// Priorité normale (génération documents)
GenerateDocument::dispatch($client)->onQueue('default');

// Basse priorité (notifications email)
SendEmailNotification::dispatch($email)->onQueue('low');
```

**Workers dédiés :**

```bash
# Worker haute priorité
php artisan queue:work redis --queue=high --tries=3

# Worker mixte
php artisan queue:work redis --queue=high,default --tries=3

# Worker basse priorité
php artisan queue:work redis --queue=low --tries=3
```

#### 3. Supervisord (Production)

**Configuration supervisord :**

```ini
[program:laravel-worker-high]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work redis --queue=high --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=3
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/worker-high.log
stopwaitsecs=3600

[program:laravel-worker-default]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work redis --queue=default --sleep=3 --tries=3 --max-time=3600
numprocs=2
# ... (mêmes options)
```

---

### D. Load Balancer + Multi-Instances Backend

#### 1. Architecture Load Balancing

```
                    ┌──────────────┐
                    │ Nginx LB     │
                    │ (HAProxy)    │
                    └──────┬───────┘
                           │
        ┌──────────────────┼──────────────────┐
        │                  │                  │
   ┌────▼────┐      ┌──────▼─────┐    ┌──────▼─────┐
   │Backend 1│      │ Backend 2  │    │ Backend 3  │
   │ :8000   │      │  :8001     │    │  :8002     │
   └─────────┘      └────────────┘    └────────────┘
```

**Nginx Load Balancer :**

```nginx
upstream backend {
    least_conn;  # Ou ip_hash pour sticky sessions

    server backend1:8000 max_fails=3 fail_timeout=30s;
    server backend2:8001 max_fails=3 fail_timeout=30s;
    server backend3:8002 max_fails=3 fail_timeout=30s;
}

server {
    listen 443 ssl http2;
    server_name api.crm-courtier.com;

    ssl_certificate /etc/letsencrypt/live/api.crm-courtier.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.crm-courtier.com/privkey.pem;

    location / {
        proxy_pass http://backend;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        # Timeout pour upload audio
        proxy_read_timeout 300s;
        proxy_send_timeout 300s;
        client_max_body_size 200M;
    }

    # Health check
    location /health {
        access_log off;
        proxy_pass http://backend/api/ping;
    }
}
```

#### 2. Health Checks

```php
// routes/api.php
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'db' => DB::connection()->getPdo() ? 'ok' : 'error',
        'redis' => Redis::ping() ? 'ok' : 'error',
        'timestamp' => now(),
    ]);
});
```

**HAProxy alternative :**

```haproxy
backend laravel_backend
    mode http
    balance roundrobin
    option httpchk GET /health

    server backend1 backend1:8000 check
    server backend2 backend2:8001 check
    server backend3 backend3:8002 check
```

---

### E. Optimisation Coûts IA

#### 1. Whisper Local (Alternative OpenAI)

**Actuellement :** Whisper API $0.006/min = $240/mois
**Alternative :** Whisper large-v3 self-hosted

**Avantages :**
- Coût fixe (GPU server)
- Pas de rate limits
- Latence réduite (pas de réseau)

**Inconvénients :**
- Requiert GPU (NVIDIA RTX 3090, A100, etc.)
- Maintenance serveur GPU

**Configuration :**

```python
# scripts/whisper_local.py
import whisper

model = whisper.load_model("large-v3")  # 2.9 GB
result = model.transcribe("audio.wav", language="fr")
```

**Coût GPU Server :**
- **OVH GPU-1 (1x RTX 3070) :** ~€100/mois
- **OVH GPU-3 (1x A100) :** ~€500/mois
- **Hetzner GPU Server :** ~€200-300/mois

**ROI :**
- Whisper API : $240/mois = €220/mois
- GPU Server : €100-200/mois
- **Économie :** €20-120/mois (si >200 heures audio/mois)

#### 2. Cache Extractions GPT

**Principe :** Cacher les résultats extracteurs pour phrases identiques

```php
// app/Services/Ai/Extractors/ClientExtractor.php
public function extract(string $transcription): array
{
    $cacheKey = 'extraction:client:' . md5($transcription);

    return Cache::remember($cacheKey, 3600, function () use ($transcription) {
        // Appel OpenAI
        return $this->callOpenAI($transcription);
    });
}
```

**Économie estimée :** 10-20% requêtes (si clients répètent infos)

#### 3. Batch Processing GPT

**Principe :** Grouper plusieurs extractions en 1 requête

```php
// Au lieu de 10 requêtes (1 par extracteur)
// → 1 seule requête avec prompt global

$prompt = "Extrais client, conjoint, prévoyance, retraite, épargne de cette transcription";
$result = $this->callOpenAI($prompt); // 1 requête vs 10
```

**Économie :** ~80% coûts extraction GPT

---

### F. Frontend Production Build

#### 1. Build Optimisé

```bash
# Build production
npm run build

# Output: frontend/dist/
# - index.html
# - assets/index-[hash].js (minified)
# - assets/index-[hash].css (minified)
```

#### 2. Nginx Static Files

```nginx
server {
    listen 443 ssl http2;
    server_name app.crm-courtier.com;

    root /var/www/frontend/dist;
    index index.html;

    # Gzip compression
    gzip on;
    gzip_types text/css application/javascript application/json;
    gzip_min_length 1000;

    # Cache static assets
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # SPA routing
    location / {
        try_files $uri $uri/ /index.html;
    }

    # Proxy API requests
    location /api {
        proxy_pass http://backend_lb;
    }
}
```

---

## III. Coûts Totaux Estimés (20 Cabinets)

### A. Infrastructure Cloud (AWS/OVH/Hetzner)

#### Option 1: AWS (Haute disponibilité)

| Ressource | Specs | Quantité | Prix/mois |
|-----------|-------|----------|-----------|
| **Compute (Backend)** |
| EC2 c6i.2xlarge | 8 vCPU, 16 GB RAM | 3 | $820 |
| EC2 t3.large (Queue workers) | 2 vCPU, 8 GB RAM | 5 | $370 |
| **Database** |
| RDS db.r6i.xlarge (Master) | 4 vCPU, 32 GB | 2 | $840 |
| RDS db.r6i.large (Read replicas) | 2 vCPU, 16 GB | 2 | $420 |
| **Cache & Queues** |
| ElastiCache r6g.large (Redis) | 2 vCPU, 13 GB | 3 | $450 |
| **Storage** |
| S3 Standard (1.4 TB/an) | - | - | $35 |
| S3 Glacier (archivage) | - | - | $5 |
| EBS SSD gp3 (500 GB × 5) | - | - | $250 |
| **Load Balancer** |
| ALB (Application LB) | - | 1 | $25 |
| **Backup** |
| EBS Snapshots (2 TB) | - | - | $100 |
| **Total Infrastructure** | | | **$3315/mois** |
| **IA (OpenAI)** |
| Whisper API (4000h/mois) | - | - | $240 |
| GPT-4o-mini extractions | - | - | $15 |
| **Total IA** | | | **$255/mois** |
| **TOTAL MENSUEL AWS** | | | **$3570/mois** |
| **TOTAL ANNUEL AWS** | | | **$42 840/an** |

#### Option 2: OVH/Hetzner (Coût optimisé)

| Ressource | Specs | Quantité | Prix/mois |
|-----------|-------|----------|-----------|
| **Compute (Backend)** |
| VPS R-128 | 16 vCPU, 64 GB RAM | 2 | €200 |
| VPS R-64 (Queue workers) | 8 vCPU, 32 GB RAM | 2 | €140 |
| **Database** |
| Serveur dédié | 8 cores, 64 GB, SSD 1TB | 2 | €300 |
| **Cache & Queues** |
| VPS (Redis) | 4 vCPU, 16 GB | 1 | €40 |
| **Storage** |
| OVH Object Storage (2 TB) | - | - | €20 |
| **Load Balancer** |
| Nginx (sur VPS existant) | - | 0 | €0 |
| **Backup** |
| Snapshot (1 TB) | - | - | €40 |
| **Total Infrastructure** | | | **€740/mois** |
| **IA (OpenAI)** |
| Whisper local (GPU serveur) | GPU RTX 3070 | 1 | €100 |
| GPT-4o-mini extractions | - | - | €15 |
| **Total IA** | | | **€115/mois** |
| **TOTAL MENSUEL OVH** | | | **€855/mois** |
| **TOTAL ANNUEL OVH** | | | **€10 260/an** |

### B. Comparaison Coûts

| Infrastructure | Mensuel | Annuel | SLA | Scalabilité |
|----------------|---------|--------|-----|-------------|
| **AWS** | $3570 | $42 840 | 99.99% | Excellente |
| **OVH/Hetzner** | €855 | €10 260 | 99.5% | Bonne |
| **Économie OVH** | **-76%** | **-76%** | -0.49% | - |

### C. Modèle de Tarification Client

**Coût total OVH :** €855/mois ÷ 20 cabinets = **€43/cabinet/mois**

**Pricing recommandé (SaaS) :**

| Tiers | Prix/mois/cabinet | Marge |
|-------|-------------------|-------|
| **Starter** (5 users) | €99 | 56% |
| **Professional** (20 users) | €249 | 82% |
| **Enterprise** (50+ users) | €499 | 91% |

**Revenus estimés (20 cabinets moyenne Pro) :**
- 20 × €249 = **€4980/mois**
- Coûts : €855/mois
- **Marge brute : €4125/mois (83%)**
- **MRR annuel : €59 760**

---

## IV. Plan de Migration (Actuel → Production)

### Phase 1: Préparation (Semaine 1-2)

- [ ] **Infrastructure :**
  - Provisionner serveurs (OVH/AWS)
  - Configurer VPC, Security Groups, Firewall
  - Setup domaines DNS + SSL (Let's Encrypt)

- [ ] **Database :**
  - Setup MariaDB master-slave (2 shards)
  - Tester réplication binlog
  - Migrer schéma BDD (migrations Laravel)

- [ ] **Object Storage :**
  - Créer buckets S3/OVH Object Storage
  - Configurer Laravel Filesystem
  - Tester upload/download

- [ ] **CI/CD :**
  - Setup GitLab/GitHub CI
  - Automated tests (PHPUnit, Pest)
  - Deployment pipeline (SSH, Ansible)

### Phase 2: Migration Donnéesibiza (Semaine 3)

- [ ] **Data Migration :**
  - Export BDD locale (mysqldump)
  - Shard data par team_id
  - Import dans shards production

- [ ] **Fichiers :**
  - Sync storage/app → S3 (aws s3 sync)
  - Vérifier intégrité (checksums)

- [ ] **Testing :**
  - Tests end-to-end (audio upload, extraction, documents)
  - Load testing (Apache Bench, k6)

### Phase 3: Go-Live (Semaine 4)

- [ ] **Cutover :**
  - DNS switch (app.crm-courtier.com → production)
  - Monitoring actif (Sentry, Prometheus)
  - Support 24/7 première semaine

- [ ] **Validation :**
  - Tests utilisateurs (5 cabinets pilotes)
  - Ajustements performance
  - Documentation utilisateur

### Phase 4: Optimisation (Semaine 5-8)

- [ ] **Performance :**
  - Tuning BDD (indexes, cache)
  - Octane scaling (workers)
  - CDN configuration

- [ ] **Coûts :**
  - Monitoring coûts IA (OpenAI)
  - Ajustement Whisper (API vs local)
  - Optimisation stockage (lifecycle rules)

---

## V. Monitoring & Alerting

### Métriques Critiques

#### 1. Application

- **Latence API :** p50, p95, p99 < 200ms, 500ms, 1s
- **Erreurs :** Error rate < 0.1%
- **Throughput :** Req/s, RPM
- **Queue depth :** Jobs en attente < 100

#### 2. Infrastructure

- **CPU :** < 70% moyen, < 90% pic
- **RAM :** < 80% utilisée
- **Disk I/O :** < 80% IOPS
- **Network :** Bande passante < 70%

#### 3. Base de Données

- **Connexions :** < 80% max_connections
- **Slow queries :** < 1% requêtes > 1s
- **Replication lag :** < 5s

#### 4. Coûts IA

- **OpenAI :** < $300/mois
- **Whisper :** < $250/mois
- **Alert si dépassement 20%**

### Stack Monitoring Recommandée

```yaml
# Prometheus + Grafana
services:
  prometheus:
    image: prom/prometheus
    volumes:
      - ./prometheus.yml:/etc/prometheus/prometheus.yml
    ports:
      - "9090:9090"

  grafana:
    image: grafana/grafana
    ports:
      - "3000:3000"
    environment:
      GF_SECURITY_ADMIN_PASSWORD: admin

  # Exporters
  node-exporter:
    image: prom/node-exporter

  mysql-exporter:
    image: prom/mysqld-exporter

  redis-exporter:
    image: oliver006/redis_exporter
```

---

## VI. Disaster Recovery & Business Continuity

### RTO/RPO Targets

- **RTO (Recovery Time Objective) :** < 1 heure
- **RPO (Recovery Point Objective) :** < 15 minutes

### Backup Strategy

#### 1. Base de Données

```bash
# Backup quotidien complet (3h du matin)
0 3 * * * mysqldump --all-databases --single-transaction | gzip > /backups/full_$(date +\%Y\%m\%d).sql.gz

# Backup incrémental binlogs (toutes les 6h)
0 */6 * * * mysqlbinlog --start-datetime="$(date -d '6 hours ago' '+\%Y-\%m-\%d \%H:\%M:\%S')" /var/log/mysql/mysql-bin.* > /backups/incremental_$(date +\%Y\%m\%d_\%H).sql
```

#### 2. Fichiers (S3)

- **Versioning S3 :** Activé (restauration fichier deleted)
- **Cross-region replication :** S3 eu-west-1 → us-east-1
- **Glacier archivage :** Après 6 mois

#### 3. Snapshots Serveurs

- **Fréquence :** Hebdomadaire
- **Rétention :** 4 semaines
- **Test restore :** Mensuel

### Incident Response Plan

#### Panne Base de Données

1. **Détection :** Alerting Prometheus (<30s)
2. **Failover :** Promotion slave → master (Orchestrator)
3. **DNS update :** db-master.internal → nouvelle IP
4. **Validation :** Health checks OK
5. **Post-mortem :** Analyse logs, correction

#### Panne Backend

1. **Load balancer :** Retire instance faulty
2. **Auto-scaling :** Lance nouvelle instance
3. **Déploiement :** Pull latest image Docker
4. **Réintégration :** Health check OK → LB pool

---

## VII. Sécurité Multi-Tenancy

### Isolation Données (RGPD)

#### 1. Network Level

```yaml
# docker-compose.yml
networks:
  team1_network:
    driver: bridge
    internal: true
  team2_network:
    driver: bridge
    internal: true
```

#### 2. Database Level

```sql
-- RLS (Row-Level Security) avec Policies
CREATE POLICY team_isolation ON clients
  USING (team_id = current_setting('app.current_team_id')::int);

ALTER TABLE clients ENABLE ROW LEVEL SECURITY;
```

```php
// Laravel Middleware
DB::statement("SET app.current_team_id = {$teamId}");
```

#### 3. Application Level

```php
// TeamScope automatique (déjà implémenté)
protected static function booted()
{
    static::addGlobalScope(new TeamScope);
}
```

### Audit & Compliance

#### 1. Audit Logs Exhaustifs

```php
// Audit TOUTES les actions CRUD
AuditLog::create([
    'user_id' => auth()->id(),
    'action' => 'update',
    'model_type' => 'Client',
    'model_id' => $client->id,
    'changes' => $client->getDirty(),
    'ip_address' => request()->ip(),
    'user_agent' => request()->userAgent(),
]);
```

#### 2. Droit à l'Oubli (RGPD Article 17)

```php
// Commande Artisan
php artisan client:gdpr-delete {client_id}

// Suppression CASCADE:
// - Client
// - Conjoint
// - Enfants
// - Audio records
// - Documents
// - Audit logs (pseudonymisation)
```

#### 3. Export Données (RGPD Article 20)

```php
php artisan client:gdpr-export {client_id}

// Export JSON complet:
// - Données client
// - Historique audio (transcriptions)
// - Documents générés
```

---

## VIII. Recommandations Finales

### Architecture Recommandée (20 Cabinets)

✅ **Infrastructure :** OVH/Hetzner (optimisation coûts)
✅ **Database :** 2 shards MariaDB (10 teams/shard) + réplication master-slave
✅ **Backend :** 3 instances (load balanced)
✅ **Queue workers :** 5 workers (3 high priority, 2 default)
✅ **Storage :** OVH Object Storage + CDN
✅ **IA :** Whisper local (GPU) + GPT-4o-mini (avec cache)
✅ **Monitoring :** Prometheus + Grafana + Sentry
✅ **Backup :** Quotidien (BDD) + S3 versioning

### Budget Total

- **Infrastructure :** €740/mois
- **IA (Whisper local + GPT) :** €115/mois
- **Monitoring (Sentry Pro) :** €29/mois
- **Support/DevOps :** €200/mois (temps homme)
- **TOTAL :** **€1084/mois** = **€13 008/an**

### Pricing SaaS

- **Starter :** €99/mois (5 users)
- **Pro :** €249/mois (20 users) ← **Target**
- **Enterprise :** €499/mois (illimité)

### Rentabilité (20 Cabinets Pro)

- **Revenus :** 20 × €249 = **€4980/mois**
- **Coûts :** €1084/mois
- **Marge brute :** **€3896/mois (78%)**
- **MRR annuel :** **€59 760**
- **Break-even :** 5 cabinets

---

**Version :** 1.0
**Date :** 2026-01-02
**Cible :** 10-20 cabinets simultanés
**Budget recommandé :** €1100/mois (~€13k/an)
**ROI estimé :** 78% marge brute
