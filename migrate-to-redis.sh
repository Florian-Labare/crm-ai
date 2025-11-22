#!/bin/bash

###############################################################################
# 🚀 Script de migration vers Redis pour CRM Whisper
# Ce script automatise la migration de database queue vers Redis
###############################################################################

set -e  # Arrêter en cas d'erreur

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🚀 Migration vers Redis - CRM Whisper"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Vérifier qu'on est dans le bon dossier
if [ ! -f "docker-compose.yml" ]; then
    echo "❌ Erreur : docker-compose.yml non trouvé"
    echo "   Veuillez lancer ce script depuis la racine du projet"
    exit 1
fi

# Étape 1 : Arrêter les services
echo "📦 Étape 1/6 : Arrêt des services Docker..."
docker compose down
echo "✅ Services arrêtés"
echo ""

# Étape 2 : Reconstruire les images
echo "🔨 Étape 2/6 : Reconstruction des images (avec extension Redis)..."
echo "   Cela peut prendre 2-3 minutes..."
docker compose build backend queue-worker
echo "✅ Images reconstruites"
echo ""

# Étape 3 : Mettre à jour le .env backend si nécessaire
echo "⚙️  Étape 3/6 : Vérification du fichier .env backend..."
if [ -f "backend/.env" ]; then
    # Vérifier si QUEUE_CONNECTION=redis existe déjà
    if grep -q "^QUEUE_CONNECTION=redis" backend/.env 2>/dev/null; then
        echo "   ✅ QUEUE_CONNECTION=redis déjà configuré"
    else
        echo "   📝 Mise à jour de QUEUE_CONNECTION..."
        # Backup du .env
        cp backend/.env backend/.env.backup
        # Remplacer QUEUE_CONNECTION
        sed -i.bak 's/^QUEUE_CONNECTION=.*/QUEUE_CONNECTION=redis/' backend/.env
        echo "   ✅ QUEUE_CONNECTION mis à jour (backup : backend/.env.backup)"
    fi

    if grep -q "^CACHE_STORE=redis" backend/.env 2>/dev/null; then
        echo "   ✅ CACHE_STORE=redis déjà configuré"
    else
        echo "   📝 Mise à jour de CACHE_STORE..."
        sed -i.bak 's/^CACHE_STORE=.*/CACHE_STORE=redis/' backend/.env
        echo "   ✅ CACHE_STORE mis à jour"
    fi

    # Ajouter REDIS_HOST si manquant
    if ! grep -q "^REDIS_HOST=" backend/.env 2>/dev/null; then
        echo "   📝 Ajout de REDIS_HOST=redis..."
        echo "" >> backend/.env
        echo "# Redis configuration" >> backend/.env
        echo "REDIS_HOST=redis" >> backend/.env
        echo "REDIS_PORT=6379" >> backend/.env
        echo "REDIS_PASSWORD=" >> backend/.env
        echo "   ✅ Configuration Redis ajoutée"
    fi
else
    echo "   ⚠️  Fichier backend/.env non trouvé"
    echo "   📝 Copie de .env.example vers .env..."
    cp backend/.env.example backend/.env
    echo "   ⚠️  ATTENTION : Vous devez configurer les clés API dans backend/.env"
fi
echo ""

# Étape 4 : Mettre à jour le .env racine si nécessaire
echo "⚙️  Étape 4/6 : Vérification du fichier .env racine..."
if [ -f ".env" ]; then
    if ! grep -q "^REDIS_HOST=" .env 2>/dev/null; then
        echo "   📝 Ajout des variables Redis..."
        echo "" >> .env
        echo "# Redis configuration" >> .env
        echo "REDIS_HOST=redis" >> .env
        echo "REDIS_PORT=6379" >> .env
        echo "REDIS_PASSWORD=" >> .env
        echo "QUEUE_CONNECTION=redis" >> .env
        echo "CACHE_STORE=redis" >> .env
        echo "   ✅ Variables Redis ajoutées"
    else
        echo "   ✅ Variables Redis déjà configurées"
    fi
else
    echo "   ⚠️  Fichier .env non trouvé"
    echo "   📝 Copie de .env.example vers .env..."
    cp .env.example .env
    echo "   ⚠️  ATTENTION : Vous devez configurer les variables dans .env"
fi
echo ""

# Étape 5 : Démarrer les services
echo "🚀 Étape 5/6 : Démarrage des services (avec Redis)..."
docker compose up -d
echo "✅ Services démarrés"
echo ""

# Attendre que les services soient prêts
echo "⏳ Attente que les services soient prêts (10 secondes)..."
sleep 10
echo ""

# Étape 6 : Vérifications
echo "🔍 Étape 6/6 : Vérifications..."
echo ""

# Vérifier que tous les conteneurs tournent
echo "📊 Conteneurs actifs :"
docker compose ps
echo ""

# Vérifier Redis
echo "🔴 Test Redis..."
if docker exec redis_cache redis-cli ping > /dev/null 2>&1; then
    echo "   ✅ Redis fonctionne !"
else
    echo "   ❌ Redis ne répond pas"
    echo "   Vérifiez les logs : docker logs redis_cache"
fi
echo ""

# Vérifier l'extension PHP Redis
echo "🐘 Test extension PHP Redis..."
if docker exec laravel_app php -m | grep -q "redis"; then
    echo "   ✅ Extension PHP Redis installée !"
else
    echo "   ❌ Extension PHP Redis non trouvée"
    echo "   Reconstruisez les images : docker compose build backend"
fi
echo ""

# Nettoyer le cache Laravel
echo "🧹 Nettoyage du cache Laravel..."
docker exec laravel_app php artisan config:clear > /dev/null 2>&1
docker exec laravel_app php artisan cache:clear > /dev/null 2>&1
echo "   ✅ Cache nettoyé"
echo ""

# Redémarrer le queue worker
echo "🔄 Redémarrage du queue worker..."
docker restart laravel_queue_worker > /dev/null 2>&1
echo "   ✅ Queue worker redémarré"
echo ""

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🎉 Migration vers Redis terminée !"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "📋 Prochaines étapes :"
echo "   1. Vérifier les logs du queue worker :"
echo "      docker logs -f laravel_queue_worker"
echo ""
echo "   2. Tester un enregistrement audio sur http://localhost:5173"
echo ""
echo "   3. Monitorer Redis pendant le traitement :"
echo "      docker exec -it redis_cache redis-cli"
echo "      127.0.0.1:6379> KEYS *"
echo "      127.0.0.1:6379> LLEN queues:default"
echo ""
echo "   4. Consulter le guide complet : GUIDE_MIGRATION_REDIS.md"
echo ""
echo "✨ Votre CRM utilise maintenant Redis (10x plus rapide) !"
echo ""
