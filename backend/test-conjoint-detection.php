<?php

/**
 * Script de test pour vérifier la détection de la section "conjoint"
 *
 * Usage: php test-conjoint-detection.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\Ai\RouterService;

echo "🧪 Test de détection de la section 'conjoint'\n";
echo str_repeat('=', 60)."\n\n";

$routerService = app(RouterService::class);

// Test 1: Transcription avec "ma femme"
$test1 = "Je m'appelle Jean Dupont. Ma femme s'appelle Sophie Martin.";
echo "📝 Test 1: Transcription avec 'ma femme'\n";
echo "Transcription: $test1\n";
$sections1 = $routerService->detectSections($test1);
echo 'Sections détectées: '.json_encode($sections1)."\n";
echo in_array('conjoint', $sections1) ? "✅ PASSED\n" : "❌ FAILED\n";
echo "\n";

// Test 2: Transcription avec "mon mari"
$test2 = 'Je suis Sophie. Mon mari est architecte.';
echo "📝 Test 2: Transcription avec 'mon mari'\n";
echo "Transcription: $test2\n";
$sections2 = $routerService->detectSections($test2);
echo 'Sections détectées: '.json_encode($sections2)."\n";
echo in_array('conjoint', $sections2) ? "✅ PASSED\n" : "❌ FAILED\n";
echo "\n";

// Test 3: Transcription avec "mon épouse"
$test3 = 'Mon épouse travaille comme infirmière.';
echo "📝 Test 3: Transcription avec 'mon épouse'\n";
echo "Transcription: $test3\n";
$sections3 = $routerService->detectSections($test3);
echo 'Sections détectées: '.json_encode($sections3)."\n";
echo in_array('conjoint', $sections3) ? "✅ PASSED\n" : "❌ FAILED\n";
echo "\n";

// Test 4: Transcription sans mention du conjoint
$test4 = 'Je suis Jean Dupont, je suis architecte.';
echo "📝 Test 4: Transcription SANS mention du conjoint\n";
echo "Transcription: $test4\n";
$sections4 = $routerService->detectSections($test4);
echo 'Sections détectées: '.json_encode($sections4)."\n";
echo ! in_array('conjoint', $sections4) ? "✅ PASSED (conjoint absent comme attendu)\n" : "❌ FAILED (conjoint détecté à tort)\n";
echo "\n";

// Résumé
$passed = 0;
$passed += in_array('conjoint', $sections1) ? 1 : 0;
$passed += in_array('conjoint', $sections2) ? 1 : 0;
$passed += in_array('conjoint', $sections3) ? 1 : 0;
$passed += ! in_array('conjoint', $sections4) ? 1 : 0;

echo str_repeat('=', 60)."\n";
echo "📊 Résultat: $passed/4 tests passés\n";
if ($passed === 4) {
    echo "🎉 Tous les tests sont passés ! La détection fonctionne correctement.\n";
} else {
    echo "⚠️  Certains tests ont échoué. Vérifiez les logs ci-dessus.\n";
}
