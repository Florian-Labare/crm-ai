# 🚀 Guide de mise en place des Jobs Asynchrones

## 📋 Résumé des modifications

Votre CRM Whisper utilise maintenant un système de **jobs asynchrones** pour le traitement audio. Cela améliore considérablement :

- ⚡ **Performance** : L'upload retourne immédiatement (pas de timeout)
- 🔄 **Fiabilité** : Retry automatique en cas d'échec (3 tentatives)
- 📊 **Suivi** : Statut en temps réel du traitement
- 🛡️ **Robustesse** : Gestion des erreurs et logs détaillés

---

## 🔧 Modifications apportées

### Backend

1. **Job ProcessAudioRecording** (`backend/app/Jobs/ProcessAudioRecording.php`)
   - Traite l'audio de façon asynchrone
   - Retry automatique : 3 tentatives avec backoff exponentiel (30s, 60s, 120s)
   - Timeout : 5 minutes par tentative
   - Logs détaillés à chaque étape

2. **AudioController modifié** (`backend/app/Http/Controllers/AudioController.php`)
   - `upload()` : Retourne immédiatement un 202 Accepted avec `audio_record_id`
   - `status($id)` : Nouvelle route pour vérifier le statut du traitement

3. **Route ajoutée** (`backend/routes/api.php`)
   - `GET /api/audio/status/{id}` : Vérifier le statut d'un enregistrement

4. **Queue Worker** (nouveau service Docker)
   - Conteneur dédié qui consomme les jobs de la queue
   - Redémarre automatiquement en cas de crash

### Frontend

5. **AudioRecorder modifié** (`frontend/src/components/AudioRecorder.tsx`)
   - Upload immédiat de l'audio
   - Polling automatique toutes les 2 secondes
   - Affichage du statut en temps réel :
     - 📤 Upload de l'audio...
     - ⏳ En attente de traitement...
     - 🧠 Transcription et analyse IA en cours...
     - ✅ Traitement terminé !
   - Arrêt automatique du polling quand terminé ou échoué

### Docker

6. **docker-compose.yml** : Nouveau service `queue-worker`
   - Partage la même config que le backend
   - Commande : `php artisan queue:work --verbose --tries=3 --timeout=300`

---

## 🛠️ Étapes de déploiement

### 1. Reconstruire et redémarrer les services Docker

```bash
# Arrêter les conteneurs actuels
docker compose down

# Reconstruire les images (optionnel mais recommandé)
docker compose build

# Démarrer tous les services (incluant le nouveau queue-worker)
docker compose up -d

# Vérifier que tous les services sont bien lancés
docker compose ps
```

Vous devriez voir **5 conteneurs** en cours d'exécution :
- `laravel_app` (backend)
- `laravel_queue_worker` ✨ **NOUVEAU**
- `mariadb_db` (base de données)
- `phpmyadmin`
- `crm_ai_frontend`

### 2. Exécuter les migrations (si nécessaire)

Les tables `jobs`, `job_batches` et `failed_jobs` doivent exister :

```bash
# Vérifier les migrations en attente
docker exec laravel_app php artisan migrate:status

# Exécuter les migrations si nécessaire
docker exec laravel_app php artisan migrate
```

### 3. Vérifier les logs du queue worker

```bash
# Voir les logs en temps réel du queue worker
docker logs -f laravel_queue_worker

# Vous devriez voir :
# [2025-01-07 22:00:00] Processing: App\Jobs\ProcessAudioRecording
```

---

## 🧪 Tests

### Test 1 : Upload audio simple

1. Ouvrez l'interface : http://localhost:5173
2. Connectez-vous avec vos identifiants
3. Accédez à la page d'accueil
4. Cliquez sur **"Démarrer l'enregistrement"**
5. Parlez quelques secondes (ex: "Bonjour, je m'appelle Jean Dupont, j'ai 35 ans")
6. Cliquez sur **"Arrêter l'enregistrement"**

**Résultat attendu :**
- Message : **"📤 Upload de l'audio..."** (1-2s)
- Message : **"⏳ En attente de traitement..."** (quelques secondes)
- Message : **"🧠 Transcription et analyse IA en cours..."** (10-60s selon Whisper/GPT)
- Toast : **"✅ Fiche client "Jean Dupont" créée !"**
- Le client apparaît dans le tableau

### Test 2 : Mise à jour client existant

1. Cliquez sur un client existant dans le tableau
2. Sur la page de détail, faites un nouvel enregistrement
3. Le système met à jour la fiche sans écraser les données existantes

**Résultat attendu :**
- Toast : **"✅ Fiche client "[Nom]" mise à jour !"**
- Les champs mentionnés dans l'audio sont mis à jour
- Les autres champs restent inchangés

### Test 3 : Gestion des erreurs

Pour tester le retry automatique, vous pouvez temporairement désactiver l'API OpenAI :

```bash
# Modifier temporairement la clé OpenAI pour la rendre invalide
docker exec laravel_app sed -i 's/OPENAI_API_KEY=.*/OPENAI_API_KEY=invalid/' /var/www/html/.env

# Faire un upload audio
# → Le job va échouer et retenter 3 fois automatiquement

# Remettre la bonne clé
docker exec laravel_app php artisan config:clear
```

Vérifiez les logs :
```bash
docker logs laravel_queue_worker
# Vous devriez voir les tentatives successives
```

### Test 4 : Vérifier la table `jobs`

Pendant le traitement, vous pouvez vérifier que les jobs sont bien enregistrés :

```bash
# Se connecter à MariaDB via phpMyAdmin : http://localhost:8080
# User: root / Password: (votre DB_PASSWORD)
# Regarder la table "jobs" → devrait être vide quand les jobs sont traités
# Regarder la table "audio_records" → champs status, transcription, processed_at
```

---

## 📊 Surveillance et debugging

### Logs du queue worker

```bash
# Logs en temps réel
docker logs -f laravel_queue_worker

# Dernières 50 lignes
docker logs --tail 50 laravel_queue_worker
```

### Logs Laravel (backend)

```bash
# Voir les logs Laravel stockés
docker exec laravel_app tail -f storage/logs/laravel.log
```

### Vérifier les jobs en échec

```bash
# Lister les jobs échoués
docker exec laravel_app php artisan queue:failed

# Voir les détails d'un job échoué
docker exec laravel_app php artisan queue:failed

# Relancer un job échoué
docker exec laravel_app php artisan queue:retry [id]

# Relancer TOUS les jobs échoués
docker exec laravel_app php artisan queue:retry all
```

### Redémarrer le queue worker

```bash
# Si le worker semble bloqué
docker restart laravel_queue_worker

# Ou via artisan (dans le conteneur backend)
docker exec laravel_app php artisan queue:restart
```

---

## 🎯 Prochaines améliorations possibles

Pour aller encore plus loin :

### 1. Passage à Redis (performance)

Actuellement : **database queue** (SQLite/MariaDB)
Recommandé en prod : **Redis**

**Avantages :**
- Beaucoup plus rapide
- Support natif du retry
- Moins de charge sur la BDD

**Modification simple :**
```yaml
# docker-compose.yml
services:
  redis:
    image: redis:7-alpine
    container_name: redis_cache
    restart: unless-stopped
    ports:
      - "6379:6379"
```

```env
# backend/.env
QUEUE_CONNECTION=redis
REDIS_HOST=redis
```

### 2. Horizon (monitoring avancé)

[Laravel Horizon](https://laravel.com/docs/horizon) offre :
- Dashboard visuel des queues
- Métriques en temps réel
- Retry automatique intelligent
- Alertes

```bash
composer require laravel/horizon
php artisan horizon:install
php artisan migrate
```

### 3. Rate limiting sur l'upload

Pour éviter l'abus :

```php
// routes/api.php
Route::post('/audio/upload', [AudioController::class, 'upload'])
    ->middleware(['auth:sanctum', 'throttle:10,1']); // 10 uploads/minute
```

### 4. Notifications (email, Slack, etc.)

Prévenir l'utilisateur quand le traitement est terminé :

```php
// Dans ProcessAudioRecording::handle()
Mail::to($user->email)->send(new AudioProcessedMail($audioRecord));
```

### 5. Supervision avec Supervisor (production)

En production, utilisez Supervisor pour garantir que le queue worker tourne toujours :

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasignal=QUIT
numprocs=2
```

---

## 🐛 Résolution de problèmes

### Problème : Le queue worker ne démarre pas

**Solution :**
```bash
# Vérifier les logs d'erreur
docker logs laravel_queue_worker

# Vérifier que les dépendances Composer sont installées
docker exec laravel_app composer install
```

### Problème : Les jobs restent en "pending" indéfiniment

**Causes possibles :**
1. Le queue worker n'est pas démarré → `docker compose ps`
2. Le worker a crashé → `docker logs laravel_queue_worker`
3. Erreur de connexion BDD → Vérifier les credentials dans `.env`

**Solution :**
```bash
docker restart laravel_queue_worker
```

### Problème : Timeout après 2 minutes

Si le traitement prend vraiment plus de 5 minutes :

```php
// backend/app/Jobs/ProcessAudioRecording.php
public $timeout = 600; // 10 minutes au lieu de 5
```

```yaml
# docker-compose.yml (service queue-worker)
command: php artisan queue:work --verbose --tries=3 --timeout=600
```

### Problème : Le polling frontend continue indéfiniment

**Cause :** Le statut reste bloqué sur "processing"

**Solution :**
```bash
# Vérifier l'état du job dans la BDD
docker exec mariadb_db mysql -u root -p[PASSWORD] -e "SELECT * FROM audio_records ORDER BY id DESC LIMIT 5;"

# Si status = processing depuis longtemps, forcer à failed
docker exec mariadb_db mysql -u root -p[PASSWORD] -e "UPDATE audio_records SET status='failed' WHERE id=[ID] AND status='processing';"
```

---

## ✅ Checklist de validation

Avant de considérer le déploiement comme réussi :

- [ ] Les 5 conteneurs Docker sont en cours d'exécution
- [ ] Les migrations sont à jour (`jobs`, `job_batches`, `failed_jobs` existent)
- [ ] Le queue worker affiche "Processing: App\Jobs\ProcessAudioRecording" dans les logs
- [ ] Un upload audio retourne un 202 Accepted avec `audio_record_id`
- [ ] Le polling frontend affiche les différents statuts
- [ ] Un enregistrement audio crée bien un client
- [ ] Les erreurs sont bien loguées dans `failed_jobs`
- [ ] Les retry fonctionnent (visible dans les logs)

---

## 📚 Documentation complémentaire

- [Laravel Queues](https://laravel.com/docs/12.x/queues)
- [Laravel Jobs & Queues Best Practices](https://laravel-news.com/laravel-queues-best-practices)
- [Docker Compose Documentation](https://docs.docker.com/compose/)

---

## 🎉 Conclusion

Votre CRM Whisper est maintenant équipé d'un système de jobs asynchrones robuste et scalable !

**Améliorations apportées :**
- ⚡ Pas de timeout côté utilisateur
- 🔄 Retry automatique (3 tentatives)
- 📊 Statut en temps réel
- 🛡️ Logs détaillés
- 🚀 Scalable (ajoutez plus de workers si besoin)

**Performance :**
- Avant : 30-60s d'attente bloquante
- Après : 1-2s upload + traitement en arrière-plan

Bonne utilisation ! 🎙️✨
