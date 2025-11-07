# 🎧 Whisper CRM - CRM avec reconnaissance vocale IA

CRM intelligent avec analyse vocale pour conseillers en assurance et gestion de patrimoine.

## 🚀 Démarrage rapide

### 1. Configuration des variables d'environnement

**⚠️ IMPORTANT : Ne jamais commiter les fichiers `.env` avec des données sensibles**

#### Configuration racine
```bash
cp .env.example .env
```
Éditer `.env` et renseigner :
- `API_ACCESS_KEY` : Votre clé API
- `DB_PASSWORD` : Mot de passe base de données
- `APP_KEY` : Généré automatiquement par Laravel

#### Configuration backend
```bash
cp backend/.env.example backend/.env
```
Éditer `backend/.env` et renseigner :
- `OPENAI_API_KEY` : Votre clé API OpenAI (obtenir sur https://platform.openai.com/api-keys)
- `OPENAI_PROJECT_ID` : Votre ID de projet OpenAI
- `DB_PASSWORD` : Mot de passe base de données (doit correspondre au .env racine)

Générer la clé Laravel :
```bash
docker compose exec backend php artisan key:generate
```

#### Configuration frontend
```bash
cp frontend/.env.example frontend/.env
```
Éditer `frontend/.env` et renseigner :
- `VITE_API_KEY` : Votre clé API (doit correspondre au .env racine)

### 2. Lancer le projet

```bash
docker compose up -d
```

### 3. Installer les dépendances

```bash
# Backend
docker compose exec backend composer install

# Frontend
cd frontend
npm install
npm run dev
```

### 4. Exécuter les migrations

```bash
docker compose exec backend php artisan migrate
```

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

## 🆘 Support

En cas de problème :
1. Vérifier les logs : `docker compose logs -f`
2. Vérifier que tous les services sont up : `docker compose ps`
3. Vérifier les variables d'environnement dans les `.env`
4. Redémarrer les containers : `docker compose restart`

## 📄 Licence

Projet privé - Tous droits réservés
