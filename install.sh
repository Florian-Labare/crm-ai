#!/bin/bash

# =============================================================================
# Script d'installation CRM Courtier IA
# =============================================================================

set -e

echo ""
echo "=============================================="
echo "   CRM Courtier IA - Installation"
echo "=============================================="
echo ""

# Couleurs pour les messages
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Fonction pour afficher les messages
info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
    exit 1
}

# Vérifier les prérequis
info "Vérification des prérequis..."

if ! command -v docker &> /dev/null; then
    error "Docker n'est pas installé. Veuillez l'installer: https://docs.docker.com/get-docker/"
fi

if ! command -v docker-compose &> /dev/null && ! docker compose version &> /dev/null; then
    error "Docker Compose n'est pas installé."
fi

if ! command -v node &> /dev/null; then
    error "Node.js n'est pas installé. Veuillez l'installer: https://nodejs.org/"
fi

info "Prérequis OK !"

# =============================================================================
# 1. Configuration Backend
# =============================================================================
info "Configuration du backend..."

cd backend

# Copier .env si n'existe pas
if [ ! -f .env ]; then
    cp .env.example .env
    warn "Fichier .env créé. IMPORTANT: Configurez vos clés API:"
    warn "  - OPENAI_API_KEY (obligatoire)"
    warn "  - HUGGINGFACE_TOKEN (optionnel, pour diarisation)"
    warn "  - DB_PASSWORD"
    echo ""
    read -p "Appuyez sur Entrée pour continuer après avoir configuré .env..."
fi

cd ..

# =============================================================================
# 2. Configuration Frontend
# =============================================================================
info "Configuration du frontend..."

cd frontend

# Installer les dépendances npm
if [ ! -d node_modules ]; then
    info "Installation des dépendances npm..."
    npm install
fi

cd ..

# =============================================================================
# 3. Lancer Docker
# =============================================================================
info "Lancement des containers Docker..."

docker compose up -d --build

# Attendre que MySQL soit prêt
info "Attente du démarrage de MySQL..."
sleep 10

# Vérifier si MySQL est prêt
MAX_TRIES=30
TRIES=0
until docker compose exec -T db mysqladmin ping -h"localhost" --silent 2>/dev/null || [ $TRIES -eq $MAX_TRIES ]; do
    echo -n "."
    sleep 2
    TRIES=$((TRIES+1))
done
echo ""

if [ $TRIES -eq $MAX_TRIES ]; then
    error "MySQL n'a pas démarré correctement"
fi

info "MySQL prêt !"

# =============================================================================
# 4. Initialisation Laravel
# =============================================================================
info "Initialisation de Laravel..."

# Générer la clé d'application si nécessaire
docker compose exec -T backend php artisan key:generate --force 2>/dev/null || true

# Exécuter les migrations
info "Exécution des migrations..."
docker compose exec -T backend php artisan migrate --force

# Exécuter les seeders
info "Initialisation des données..."
docker compose exec -T backend php artisan db:seed --force

# Créer les liens de stockage
docker compose exec -T backend php artisan storage:link 2>/dev/null || true

# Vider le cache
docker compose exec -T backend php artisan optimize:clear

# =============================================================================
# 5. Démarrer le worker Redis
# =============================================================================
info "Démarrage du worker de traitement audio..."
docker compose exec -d backend php artisan queue:work redis --tries=3

# =============================================================================
# 6. Frontend
# =============================================================================
info "Démarrage du serveur de développement frontend..."

cd frontend
npm run dev &
FRONTEND_PID=$!
cd ..

# =============================================================================
# Terminé !
# =============================================================================
echo ""
echo "=============================================="
echo -e "${GREEN}   Installation terminée !${NC}"
echo "=============================================="
echo ""
echo "URLs d'accès:"
echo "  - Frontend:  http://localhost:5173"
echo "  - Backend:   http://localhost:8000"
echo ""
echo "Identifiants par défaut:"
echo "  - Email:     admin@courtier.fr"
echo "  - Mot de passe: password"
echo ""
echo "Pour arrêter les services:"
echo "  docker compose down"
echo "  # Arrêter le frontend avec Ctrl+C"
echo ""
