#!/bin/bash

# ========================================
# Script de Refactorisation Laravel Boost
# ========================================

echo "🚀 Démarrage de la refactorisation Laravel Boost..."
echo ""

# 1. Créer les API Resources manquantes
echo "📦 1/5 - Création des API Resources..."
php artisan make:resource AudioRecordResource --force
php artisan make:resource BaePrevoyanceResource --force
php artisan make:resource BaeRetraiteResource --force
php artisan make:resource BaeEpargneResource --force

# 2. Créer les Form Requests manquants
echo "📝 2/5 - Création des Form Requests..."
php artisan make:request StoreAudioRequest
php artisan make:request UpdateAudioRequest

# 3. Optimiser le code avec Pint
echo "🎨 3/5 - Formatage du code avec Laravel Pint..."
./vendor/bin/pint

# 4. Créer les tests de base
echo "🧪 4/5 - Création des tests Feature..."
php artisan make:test ClientControllerTest
php artisan make:test AudioControllerTest

# 5. Vérifier la qualité du code
echo "✅ 5/5 - Vérification finale..."
php artisan about

echo ""
echo "✨ Refactorisation terminée !"
echo ""
echo "📋 Prochaines étapes:"
echo "  1. Examiner les fichiers dans app/Http/Resources/"
echo "  2. Implémenter les Resources dans les Controllers"
echo "  3. Lancer les tests: php artisan test"
echo ""
