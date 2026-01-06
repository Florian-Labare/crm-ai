# Documentation Technique CRM IA Courtier

## À propos de cette documentation

Cette documentation exhaustive a été créée pour analyser l'infrastructure et proposer des recommandations de scaling pour supporter **10-20 cabinets de courtage utilisant simultanément** cette solution.

**Date :** 2026-01-02
**Objectif :** Fournir à Claude Chat (ou tout expert infrastructure) les informations nécessaires pour recommander l'architecture optimale multi-tenants.

---

## 📑 Table des Matières

### [01 - Architecture Globale](./01_ARCHITECTURE.md)

Vue d'ensemble complète du système :
- Stack technologique (Laravel, React, Docker, IA)
- Architecture applicative (backend MVC, frontend React)
- Flux de données principal
- Pipeline IA (RouterService → Extracteurs → Normalisation → Sync)
- Patterns & principes appliqués (SOLID, Service Layer, Strategy)
- Dépendances externes (OpenAI, HuggingFace)

**Points clés :**
- Application full-stack avec IA embarquée
- Multi-tenancy via TeamScope
- Queues asynchrones Redis pour traitement audio
- Architecture modulaire avec 10+ extracteurs IA spécialisés

---

### [02 - Base de Données](./02_DATABASE.md)

Schéma complet avec 24 tables :
- **Tables principales :** teams, users, clients (hub central)
- **Relations :** conjoints, enfants, sante_souhaits, bae_*, revenus, passifs, actifs, etc.
- **Multi-tenancy :** Isolation stricte par team_id
- **Volumétrie estimée :** 250 MB BDD + 140 GB fichiers par an (20 cabinets)
- **Optimisations :** Indexes, partitionnement, réplication recommandée
- **RGPD :** Audit logs, chiffrement, droit à l'oubli

**Points clés :**
- Schéma normalisé avec 24 tables
- One-to-one (conjoint, BAE sections) et one-to-many (enfants, revenus)
- TeamScope automatique sur tous les modèles critiques
- Conformité RGPD avec audit_logs exhaustif

---

### [03 - Infrastructure Actuelle](./03_INFRASTRUCTURE.md)

Configuration Docker Compose complète :
- **8 services :** backend (Laravel+Apache+Python), frontend (Vite), db (MariaDB), redis, queue-worker, gotenberg, mailhog, phpmyadmin
- **Volumes persistants :** mariadb_data, redis_data
- **Dockerfile backend :** PHP 8.3 + Swoole + Python 3 + Pyannote
- **Configuration Apache/PHP :** Upload 200MB, timeout 300s, OpCache
- **Ressources actuelles :** ~1.5 GB RAM, ~2 GB disque (dev)
- **Sécurité :** Réseau bridge, secrets .env, HTTPS recommandé production
- **Backup :** Stratégie recommandée (dump quotidien, S3 sync)

**Points clés :**
- Docker Compose pour dev ET production possible
- Laravel Octane (Swoole) pour performance x10-100
- Python embarqué dans container Laravel (Whisper, Pyannote)
- Aucun backup automatisé actuellement (⚠️ à mettre en place)

---

### [04 - Scaling Multi-Cabinets](./04_SCALING.md)

**Analyse critique et recommandations pour 10-20 cabinets :**

#### Goulots d'étranglement identifiés
1. **🔴 Base de données single-node** → Sharding + réplication recommandée
2. **🔴 Stockage local** → Migration vers S3/Object Storage obligatoire
3. **🔴 API OpenAI coûts variables** → $3000-3600/an → Whisper local GPU
4. **🔴 Queue worker unique** → Multi-workers + prioritized queues
5. **🟠 Frontend Vite dev** → Build production + Nginx
6. **🟠 Absence load balancer** → HAProxy/Nginx LB requis

#### Architecture cible recommandée
```
Cloudflare CDN + WAF
      ↓
Nginx Load Balancer (round-robin)
      ↓
3 Backend Instances (Laravel Octane)
      ↓
2 Shards MariaDB (master-slave chacun)
      ↓
Redis Cluster (3 nodes)
      ↓
S3/MinIO Object Storage
```

#### Coûts estimés
| Infrastructure | Mensuel | Annuel |
|----------------|---------|--------|
| **AWS (HA)** | $3570 | $42 840 |
| **OVH/Hetzner (optimisé)** | €855 | €10 260 |

**Recommandation :** OVH/Hetzner (économie -76% vs AWS)

#### Modèle de tarification SaaS
- **Starter :** €99/mois (5 users)
- **Pro :** €249/mois (20 users) ← Cible
- **Enterprise :** €499/mois (illimité)

**Revenus estimés (20 cabinets Pro) :**
- MRR : €4980/mois
- Coûts : €1084/mois
- **Marge brute : 78% (€3896/mois)**

---

### [05 - Pipeline IA](./05_AI_PIPELINE.md)

Détail exhaustif du traitement audio → données structurées :

#### Étapes du pipeline
1. **Transcription** (Whisper API/local) : 20-60s
2. **Diarisation** (Pyannote) : 30-60s
3. **Routing** (GPT-4o-mini) : 1-3s → Détecte sections concernées
4. **Extraction modulaire** (10+ extracteurs GPT) : 10-20s
5. **Normalisation** (dates, phones, booléens) : <1s
6. **Sync BDD** (SyncServices) : 2-5s

**Durée totale :** 60-130s (1-2 minutes) pour 10 min audio
**Coût OpenAI :** $0.06-0.10 par enregistrement

#### Extracteurs spécialisés
- **ClientExtractor :** Identité, coordonnées, situation familiale/pro
- **ConjointExtractor :** Données du conjoint uniquement
- **PrevoyanceExtractor :** Besoins prévoyance (ITT, décès)
- **RetraiteExtractor :** Besoins retraite (PER, TMI)
- **EpargneExtractor :** Besoins épargne/patrimoine
- **5+ autres extracteurs** pour revenus, passifs, actifs, biens immo, etc.

#### Optimisations possibles
- **Cache extractions :** 10-20% économie si transcriptions similaires
- **Batch extraction :** 1 requête GPT vs 10 → économie 80%
- **Whisper local GPU :** Rentable si >2000 enregistrements/mois

**Points clés :**
- Architecture modulaire avec Strategy Pattern
- Garde-fous multiples (confusion client/conjoint, détection conjoint forcée)
- Normalisation robuste (dates, phones, négations)
- Logs structurés à chaque étape pour monitoring

---

## 🎯 Cas d'Usage de cette Documentation

### Pour un Expert Infrastructure / Architecte Cloud

**Question :** _"Comment scaler cette solution pour 10-20 cabinets (400 users, 4000 enregistrements audio/mois) de manière stable et rentable ?"_

**Parcours recommandé :**
1. **[01_ARCHITECTURE.md](./01_ARCHITECTURE.md)** → Comprendre l'application globale
2. **[04_SCALING.md](./04_SCALING.md)** → Analyse des goulots d'étranglement et architecture cible
3. **[02_DATABASE.md](./02_DATABASE.md)** → Volumétrie et stratégie sharding/réplication
4. **[03_INFRASTRUCTURE.md](./03_INFRASTRUCTURE.md)** → État actuel Docker et migration production

**Recommandations attendues :**
- Validation/ajustement architecture cible (sharding, load balancing)
- Choix cloud provider (AWS, OVH, Hetzner, GCP, Azure)
- Stratégie backup & disaster recovery
- Monitoring & alerting (Prometheus, Grafana, Sentry)
- Sécurité multi-tenancy (isolation réseau, chiffrement)
- Optimisations coûts (Whisper local vs API, cache GPT)

---

### Pour un Développeur Backend Reprenant le Projet

**Question :** _"Comment fonctionne le pipeline IA et comment ajouter un nouvel extracteur ?"_

**Parcours recommandé :**
1. **[05_AI_PIPELINE.md](./05_AI_PIPELINE.md)** → Pipeline complet avec exemples
2. **[01_ARCHITECTURE.md](./01_ARCHITECTURE.md)** → Services et architecture modulaire
3. **[02_DATABASE.md](./02_DATABASE.md)** → Schéma BDD pour comprendre sync

**Guide pratique :**
- Créer un extracteur : Copier `ClientExtractor.php`, adapter prompt et champs
- Ajouter au routing : Modifier `RouterService::detectSections()`
- Créer SyncService : Hériter de `AbstractSyncService`
- Tester : `ProcessAudioRecording` job

---

### Pour un Product Owner / Business

**Question :** _"Combien coûte l'infrastructure pour 20 cabinets et quel pricing SaaS adopter ?"_

**Parcours recommandé :**
1. **[04_SCALING.md](./04_SCALING.md)** → Section "Coûts Totaux Estimés" et "Modèle de Tarification"
2. **[05_AI_PIPELINE.md](./05_AI_PIPELINE.md)** → Section "Performance & Optimisations" (coûts IA)

**Résumé business :**
- **Coûts infrastructure :** €855-1084/mois (OVH/Hetzner) ou $3570/mois (AWS)
- **Pricing recommandé :** €99 (Starter), €249 (Pro), €499 (Enterprise)
- **MRR potentiel (20 cabinets Pro) :** €4980/mois
- **Marge brute :** 78% (€3896/mois profit)
- **Break-even :** 5 cabinets payants

---

## 📊 Métriques Clés du Projet

### Volumétrie (par cabinet moyen)
- **Clients actifs :** 500-2000
- **Utilisateurs :** 5-20
- **Enregistrements audio/mois :** 50-200
- **Documents générés/mois :** 500-2000

### Performance
- **Traitement audio (10 min) :** 60-130 secondes
- **Latence API :** p95 < 500ms
- **Uptime cible :** 99.5% (43h downtime/an max)

### Base de Données (20 cabinets)
- **Tables :** 24
- **Lignes estimées :** ~200 000 (clients, relations, documents)
- **Taille BDD :** ~250 MB/an
- **Fichiers :** 80 GB audio + 60 GB documents par an

### Coûts IA (20 cabinets)
- **Whisper API :** $240/mois (4000 enregistrements × 10 min × $0.006)
- **GPT-4o-mini :** $15/mois (extractions)
- **Total IA :** ~$255/mois = $3060/an

---

## 🛠️ Technologies Utilisées

### Backend
- **Framework :** Laravel 12 (PHP 8.3)
- **Performance :** Laravel Octane + Swoole
- **Base de données :** MariaDB 11
- **Cache & Queues :** Redis 7
- **Auth :** Laravel Sanctum
- **Permissions :** Spatie Laravel Permission

### Frontend
- **Framework :** React 19 + TypeScript 5.9
- **Build :** Vite 7
- **Styling :** Tailwind CSS 4 (thème Vuexy)
- **Routing :** React Router DOM 7

### Intelligence Artificielle
- **Transcription :** OpenAI Whisper API (ou Whisper local large-v3)
- **Diarisation :** Pyannote.audio 3.1 (HuggingFace)
- **Extraction NLP :** OpenAI GPT-4o-mini
- **Traitement audio :** FFmpeg, Python 3

### Infrastructure
- **Conteneurisation :** Docker + Docker Compose
- **Reverse Proxy :** Nginx (recommandé production)
- **CDN :** Cloudflare (recommandé)
- **Object Storage :** S3 / OVH Object Storage / MinIO
- **Monitoring :** Prometheus + Grafana + Sentry (recommandé)

---

## 🔐 Sécurité & Conformité

### Multi-Tenancy
- **Isolation :** TeamScope appliqué automatiquement sur tous les modèles
- **Database :** Foreign key `team_id` sur toutes les tables critiques
- **Application :** Middleware validation team ownership
- **Network :** Isolation réseau par VPC recommandée en production

### RGPD
- **Audit logs :** Traçabilité complète des actions
- **Chiffrement :** APP_KEY Laravel (data at rest)
- **Consentement :** Champ `consentement_audio` obligatoire
- **Droit à l'oubli :** Script `client:gdpr-delete` (cascade complet)
- **Export données :** Script `client:gdpr-export` (JSON complet)

### Sécurité API
- **Rate limiting :** Throttle sur routes critiques (audio upload, IA)
- **CORS :** Configuration stricte (domaines autorisés)
- **Tokens :** Laravel Sanctum (SHA-256, expiration configurable)
- **HTTPS :** Obligatoire production (Let's Encrypt)

---

## 📈 Roadmap Scaling

### Phase 1 : Préparation (Semaines 1-2)
- [ ] Provisionner serveurs (OVH/Hetzner)
- [ ] Setup MariaDB sharding (2 shards)
- [ ] Configurer Object Storage S3
- [ ] CI/CD pipeline (GitLab/GitHub)

### Phase 2 : Migration Données (Semaine 3)
- [ ] Export BDD locale + shard par team_id
- [ ] Sync fichiers → S3
- [ ] Tests end-to-end + load testing

### Phase 3 : Go-Live (Semaine 4)
- [ ] DNS cutover production
- [ ] Monitoring actif (Sentry, Prometheus)
- [ ] 5 cabinets pilotes

### Phase 4 : Optimisation (Semaines 5-8)
- [ ] Tuning BDD (indexes, cache)
- [ ] Whisper local GPU (si >2000 enreg/mois)
- [ ] CDN documents (CloudFront/Cloudflare)

---

## 📞 Support & Maintenance

### Monitoring Recommandé
- **Application :** Sentry (errors), Telescope (dev)
- **Infrastructure :** Prometheus + Grafana
- **Logs :** ELK Stack (Elasticsearch + Kibana)
- **Uptime :** UptimeRobot, Pingdom

### Backup Strategy
- **BDD :** Dump quotidien + binlogs incrémentaux (6h)
- **Fichiers :** S3 versioning + cross-region replication
- **Snapshots :** Hebdomadaire (rétention 4 semaines)
- **RTO/RPO :** <1h / <15min

### Alertes Critiques
- CPU/RAM/Disk > 80%
- Database slow queries > 1s
- Queue depth > 100 jobs
- API error rate > 0.1%
- Coûts OpenAI dépassement > 20%

---

## 🎓 Ressources Complémentaires

### Commandes Utiles

```bash
# Démarrage Docker
docker compose up -d --build

# Migrations
docker compose exec backend php artisan migrate

# Queues
docker compose exec backend php artisan queue:work redis

# Logs
docker compose logs -f backend

# Tests
docker compose exec backend php artisan test
```

### Fichiers Clés à Connaître

```
backend/
├── app/Services/Ai/
│   ├── AnalysisService.php      # Orchestrateur extraction
│   ├── RouterService.php        # Détection sections
│   └── Extractors/              # 10+ extracteurs spécialisés
├── app/Jobs/
│   └── ProcessAudioRecording.php # Job principal traitement audio
├── app/Models/
│   └── Client.php               # Hub central avec 15+ relations
└── routes/api.php               # 60+ routes API

frontend/
├── src/pages/
│   ├── ClientEditPage.tsx       # Page édition client (1500+ lignes)
│   └── ClientDetailPage.tsx     # Fiche client complète
└── src/components/
    └── LongRecorder.tsx         # Enregistrement audio long (2h max)
```

---

## 🚀 Conclusion

Cette documentation fournit une **vision exhaustive** du CRM IA Courtier, de l'architecture applicative à l'infrastructure de production multi-cabinets.

**Points forts du projet :**
- ✅ Architecture modulaire et scalable
- ✅ Pipeline IA robuste avec garde-fous
- ✅ Multi-tenancy strict (RGPD-ready)
- ✅ Docker Compose facilitant déploiement
- ✅ Coûts maîtrisés (€855/mois pour 20 cabinets)

**Axes d'amélioration identifiés :**
- ⚠️ Sharding BDD requis pour scaling
- ⚠️ Migration Object Storage obligatoire
- ⚠️ Load balancer + multi-instances backend
- ⚠️ Backup automatisé à mettre en place
- ⚠️ Monitoring/alerting production

**Rentabilité estimée (20 cabinets Pro) :**
- **Revenus :** €4980/mois
- **Coûts :** €1084/mois
- **Marge brute :** 78% (€3896/mois)

---

**Version documentation :** 1.0
**Dernière mise à jour :** 2026-01-02
**Auteur :** Documentation technique pour scaling multi-cabinets
**Contact :** Pour questions techniques, consulter les fichiers détaillés ci-dessus
