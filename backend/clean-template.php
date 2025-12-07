<?php

/**
 * Script pour nettoyer un template Word et reconstruire toutes les variables fragmentées
 *
 * Usage: php clean-template.php "Template Mandat.docx"
 */

require __DIR__ . '/vendor/autoload.php';

$templateName = $argv[1] ?? 'Template Mandat.docx';
$templatePath = __DIR__ . '/storage/app/templates/' . $templateName;
$backupPath = __DIR__ . '/storage/app/templates/' . str_replace('.docx', '_backup_' . time() . '.docx', $templateName);

if (!file_exists($templatePath)) {
    die("❌ Template non trouvé : {$templatePath}\n");
}

echo "🔧 Nettoyage du template : {$templateName}\n";
echo "📁 Chemin : {$templatePath}\n";

// Créer une sauvegarde
copy($templatePath, $backupPath);
echo "💾 Sauvegarde créée : " . basename($backupPath) . "\n\n";

$zip = new ZipArchive();
if ($zip->open($templatePath) !== TRUE) {
    die("❌ Impossible d'ouvrir le template\n");
}

// Lire le document.xml
$xml = $zip->getFromName('word/document.xml');

echo "📊 Analyse du XML...\n";

// Compter les variables avant nettoyage
preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $xml, $textMatches);
$fullTextBefore = implode('', $textMatches[1]);
preg_match_all('/\{\{([^}]+)\}\}/', $fullTextBefore, $varsBefore);
echo "   Variables détectées AVANT : " . count(array_unique($varsBefore[1])) . "\n";

// Nettoyer le XML : supprimer toutes les balises entre { et }
$xml = preg_replace_callback(
    '/(\{)\{([^}]*)\}\}/',
    function($match) {
        // Extraire le contenu entre {{ et }}
        $content = $match[2];
        // Supprimer toutes les balises XML
        $cleanContent = preg_replace('/<[^>]+>/', '', $content);
        // Reconstruire la variable proprement
        return '{{' . trim($cleanContent) . '}}';
    },
    $xml
);

// Si la regex ci-dessus n'a rien trouvé, c'est que les variables sont très fragmentées
// Utiliser une approche différente : reconstruire toutes les variables depuis le texte brut
preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $xml, $textMatches);
$fullText = implode('', $textMatches[1]);

// Décoder les entités HTML
$fullText = html_entity_decode($fullText, ENT_XML1);

// Trouver toutes les variables dans le texte complet
preg_match_all('/\{\{([^}]+)\}\}/', $fullText, $varsFound);

echo "   Variables trouvées dans le texte : " . count(array_unique($varsFound[1])) . "\n";

// Pour chaque variable trouvée, la nettoyer dans le XML
foreach (array_unique($varsFound[0]) as $variable) {
    $varName = str_replace(['{{', '}}'], '', $variable);

    // Pattern très permissif pour trouver la variable fragmentée
    // Cherche {{ suivi de n'importe quoi contenant $varName suivi de }}
    $pattern = '/\{\{[^}]*?' . preg_quote($varName, '/') . '[^}]*?\}\}/s';

    // Remplacer par une version propre
    $cleanVar = '{{' . $varName . '}}';

    $xml = preg_replace($pattern, $cleanVar, $xml);
}

// Sauvegarder le XML nettoyé
$zip->deleteName('word/document.xml');
$zip->addFromString('word/document.xml', $xml);
$zip->close();

// Vérifier le résultat
$zipCheck = new ZipArchive();
$zipCheck->open($templatePath);
$xmlCheck = $zipCheck->getFromName('word/document.xml');
preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $xmlCheck, $textMatchesAfter);
$fullTextAfter = implode('', $textMatchesAfter[1]);
preg_match_all('/\{\{([^}]+)\}\}/', $fullTextAfter, $varsAfter);
$zipCheck->close();

echo "\n✅ Nettoyage terminé !\n";
echo "   Variables détectables APRÈS : " . count(array_unique($varsAfter[1])) . "\n";
echo "\n📋 Variables nettoyées :\n";
foreach (array_unique($varsAfter[1]) as $var) {
    echo "   - {$var}\n";
}

echo "\n💡 Le template a été nettoyé. Retestez la génération !\n";
echo "   Sauvegarde disponible : " . basename($backupPath) . "\n";
