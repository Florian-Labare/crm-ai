# 🎧 CRM Courtier IA - CRM avec reconnaissance vocale

CRM intelligent avec analyse vocale pour conseillers en assurance et gestion de patrimoine.

## ✨ Fonctionnalités

- **Enregistrement vocal** : Enregistrez vos entretiens clients
- **Transcription automatique** : Whisper (OpenAI)
- **Diarisation** : Séparation courtier/client (Pyannote)
- **Extraction intelligente** : GPT-4 extrait les informations automatiquement
- **Génération de documents** : Documents réglementaires (recueil, mandat...)
- **Fiche client complète** : État civil, famille, BAE (santé, prévoyance, retraite, épargne)

## 🚀 Installation rapide

```bash
# Cloner et lancer l'installation
git clone <repository-url>
cd crm-ai-copie
./install.sh
```

## 📋 Installation manuelle

### 1. Prérequis

- **Docker** et **Docker Compose**
- **Node.js** 18+ et **npm**
- Clé API **OpenAI** (obligatoire) - https://platform.openai.com/api-keys
- Token **HuggingFace** (optionnel) - https://huggingface.co/settings/tokens

### 2. Configuration Backend

```bash
cd backend
cp .env.example .env
```

**Éditez `.env` et configurez :**
- `OPENAI_API_KEY` : Votre clé API OpenAI (obligatoire)
- `DB_PASSWORD` : Mot de passe MySQL
- `HUGGINGFACE_TOKEN` : Token HuggingFace (optionnel, pour la diarisation)

### 3. Lancer les containers Docker

```bash
docker compose up -d --build
```

### 4. Initialiser la base de données

```bash
# Générer la clé Laravel
docker compose exec backend php artisan key:generate

# Migrations
docker compose exec backend php artisan migrate

# Données initiales (utilisateur admin, templates, équipe)
docker compose exec backend php artisan db:seed

# Lien de stockage
docker compose exec backend php artisan storage:link
```

### 5. Installer et lancer le frontend

```bash
cd frontend
npm install
npm run dev
```

### 6. Démarrer le worker audio (IMPORTANT)

```bash
docker compose exec backend php artisan queue:work redis --tries=3
```

## 🔑 Accès à l'application

| Service | URL |
|---------|-----|
| Frontend | http://localhost:5173 |
| Backend API | http://localhost:8000 |

### Identifiants par défaut

- **Email** : `admin@courtier.fr`
- **Mot de passe** : `password`

## 🔐 Sécurité

### Fichiers sensibles ignorés par Git

Le projet est configuré pour ignorer automatiquement :
- ✅ Tous les fichiers `.env` (variables d'environnement)
- ✅ Clés API OpenAI
- ✅ Mots de passe base de données
- ✅ Fichiers audio enregistrés (données clients sensibles)
- ✅ Logs applicatifs
- ✅ Données MySQL (`mysql-data/`, `mariadb-data/`)
- ✅ Fichiers IDE (`.idea/`, `.vscode/`)

### Checklist avant commit

Avant chaque commit, vérifier :
1. Aucun fichier `.env` n'est tracké
2. Aucune clé API n'est présente dans le code
3. Les fichiers `.env.example` ne contiennent que des valeurs d'exemple
4. Les données de test ne contiennent pas d'informations réelles

### Que faire si vous avez commité des secrets par erreur ?

```bash
# 1. Retirer le fichier du tracking (sans le supprimer)
git rm --cached chemin/vers/fichier-sensible

# 2. L'ajouter au .gitignore si pas déjà fait

# 3. Commiter la suppression
git commit -m "Retrait fichier sensible du tracking"

# 4. IMPORTANT : Révoquer immédiatement les clés API compromises
# Aller sur https://platform.openai.com/api-keys et révoquer la clé
```

## 🏗️ Architecture

- **Backend** : Laravel 12 + Fortify + Sanctum (port 8000)
- **Frontend** : React 19 + TypeScript + Vite (port 5173)
- **Base de données** : MariaDB 11 (port 3306)
- **phpMyAdmin** : Interface BDD (port 8082)
- **IA** : OpenAI GPT-4o-mini + Whisper API

## 📝 Fonctionnalités

- ✅ Enregistrement vocal des informations client
- ✅ Transcription automatique avec Whisper
- ✅ Extraction intelligente des données avec GPT-4
- ✅ Gestion complète des fiches clients
- ✅ Authentification sécurisée (Laravel Sanctum)
- ✅ Validation et normalisation des données
- ✅ Interface moderne avec Vuexy design

## 🔧 Développement

### Accès aux services

- Frontend : http://localhost:5173
- Backend API : http://localhost:8000/api
- phpMyAdmin : http://localhost:8082
  - Utilisateur : `root`
  - Mot de passe : Celui défini dans `.env`

### Logs

```bash
# Logs backend Laravel
docker compose logs -f backend

# Logs base de données
docker compose logs -f db

# Logs en temps réel
docker compose logs -f
```

### Commandes utiles

```bash
# Redémarrer un service
docker compose restart backend

# Accéder au shell du container
docker compose exec backend bash

# Exécuter une commande artisan
docker compose exec backend php artisan [commande]

# Vider le cache
docker compose exec backend php artisan cache:clear
docker compose exec backend php artisan config:clear
```

## 📦 Structure du projet

```
crm-ai/
├── backend/           # Laravel API
│   ├── app/
│   ├── config/
│   ├── database/
│   └── .env.example
├── frontend/          # React SPA
│   ├── src/
│   ├── public/
│   └── .env.example
├── docker-compose.yml
├── .gitignore
└── .env.example
```

## 🆘 Résolution de problèmes

### ❌ Erreur de migration (FK team_id)

Si vous avez une erreur de foreign key sur `team_id` :

```bash
# Réinitialiser complètement la base de données
docker compose exec backend php artisan migrate:fresh --seed
```

### ❌ L'enregistrement vocal ne remplit pas les champs client

1. **Vérifiez que le worker est lancé** (OBLIGATOIRE) :
   ```bash
   docker compose exec backend php artisan queue:work redis --tries=3
   ```

2. **Vérifiez les logs** :
   ```bash
   docker compose logs -f backend
   cat backend/storage/logs/laravel.log | tail -100
   ```

3. **Vérifiez que `OPENAI_API_KEY` est configuré** dans `backend/.env`

### ❌ La diarisation (Pyannote) ne fonctionne pas

1. Configurez `HUGGINGFACE_TOKEN` dans `backend/.env`
2. Téléchargez le modèle :
   ```bash
   docker compose exec backend bash -c 'export HUGGINGFACE_TOKEN=$(grep HUGGINGFACE_TOKEN .env | cut -d= -f2) && python3 scripts/init_pyannote.py --download-model'
   ```

### ❌ Erreur 500 lors de la création/modification de client

Le cache Laravel est peut-être corrompu :
```bash
docker compose exec backend php artisan optimize:clear
```

### ❌ Les documents ne se génèrent pas

1. Vérifiez que Gotenberg est lancé : `docker compose ps gotenberg`
2. Vérifiez que les templates existent : `ls backend/storage/app/templates/`

## 📦 Services Docker

| Service | Description | Port |
|---------|-------------|------|
| backend | Laravel (Apache + PHP 8.3) | 8000 |
| db | MySQL 8 | 3306 |
| redis | Redis (cache, queues) | 6379 |
| gotenberg | Conversion DOCX → PDF | 3000 |

## 🔧 Commandes utiles

```bash
# Voir les logs en temps réel
docker compose logs -f backend

# Accéder au container backend
docker compose exec backend bash

# Relancer le worker
docker compose exec backend php artisan queue:restart

# Vider le cache Laravel
docker compose exec backend php artisan optimize:clear

# Rebuilder le backend (après modification Dockerfile)
docker compose build backend && docker compose up -d backend

# Réinitialiser complètement la BDD
docker compose exec backend php artisan migrate:fresh --seed
```

## 📄 Licence

Projet privé - Tous droits réservés
