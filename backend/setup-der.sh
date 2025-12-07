#!/bin/bash

# Script de setup pour la fonctionnalité DER
# Exécute la migration et les seeders

echo "🚀 Setup DER - Document d'Entrée en Relation"
echo "============================================="
echo ""

# 1. Exécuter les migrations
echo "📦 1. Exécution des migrations..."
php artisan migrate

if [ $? -ne 0 ]; then
    echo "❌ Erreur lors de l'exécution des migrations"
    echo "ℹ️  Assurez-vous que la base de données est accessible"
    exit 1
fi

echo "✅ Migrations exécutées avec succès"
echo ""

# 2. Créer le rôle MIA
echo "👥 2. Création du rôle MIA..."
php artisan db:seed --class=RoleSeeder

if [ $? -ne 0 ]; then
    echo "⚠️  Erreur lors de la création du rôle MIA"
fi

echo "✅ Rôle MIA créé"
echo ""

# 3. Créer les utilisateurs MIA
echo "🧑‍💼 3. Création des utilisateurs MIA..."
php artisan db:seed --class=MiaUserSeeder

if [ $? -ne 0 ]; then
    echo "❌ Erreur lors de la création des utilisateurs MIA"
    exit 1
fi

echo ""
echo "============================================="
echo "🎉 Setup DER terminé avec succès !"
echo "============================================="
echo ""
echo "📋 Utilisateurs MIA créés :"
echo "  • Jean Dupont - jean.dupont@courtier.fr"
echo "  • Marie Martin - marie.martin@courtier.fr"
echo "  • Pierre Dubois - pierre.dubois@courtier.fr"
echo "  • Sophie Bernard - sophie.bernard@courtier.fr"
echo ""
echo "🔑 Mot de passe pour tous : password123"
echo ""
echo "🌐 Accédez au formulaire DER sur : /der/new"
echo ""
