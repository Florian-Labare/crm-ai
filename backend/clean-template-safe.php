<?php

/**
 * Script de nettoyage SAFE pour templates Word
 * Reconstruit les variables fragmentées sans en perdre
 */

require __DIR__ . '/vendor/autoload.php';

$templateName = $argv[1] ?? 'Template Mandat.docx';
$templatePath = __DIR__ . '/storage/app/templates/' . $templateName;

if (!file_exists($templatePath)) {
    die("❌ Template non trouvé\n");
}

echo "🔧 Nettoyage SAFE du template : {$templateName}\n\n";

$zip = new ZipArchive();
$zip->open($templatePath);
$xml = $zip->getFromName('word/document.xml');

// Étape 1: Extraire TOUT le texte pour détecter les variables
preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $xml, $matches);
$fullText = implode('', $matches[1]);
$fullText = html_entity_decode($fullText, ENT_XML1);

// Détecter toutes les variables dans le texte complet
preg_match_all('/\{\{([^}]+)\}\}/', $fullText, $varsFound);
$variables = array_unique($varsFound[1]);

echo "📋 Variables détectées (" . count($variables) . ") :\n";
foreach ($variables as $var) {
    echo "   - {$var}\n";
}
echo "\n";

// Étape 2: Pour chaque paragraphe, reconstruire les variables fragmentées
$xml = preg_replace_callback(
    '/<w:p\b[^>]*>(.*?)<\/w:p>/s',
    function($pMatch) use ($variables) {
        $paragraph = $pMatch[0];

        // Extraire tout le texte du paragraphe
        preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $paragraph, $tMatches);
        $paragraphText = implode('', $tMatches[1]);
        $paragraphText = html_entity_decode($paragraphText, ENT_XML1);

        // Pour chaque variable, la nettoyer dans ce paragraphe
        foreach ($variables as $varName) {
            $fullVar = '{{' . $varName . '}}';

            // Si cette variable est dans ce paragraphe
            if (strpos($paragraphText, $fullVar) !== false) {
                // Créer un marqueur temporaire unique
                $marker = '___VAR_' . md5($varName) . '___';

                // Remplacer tout le contenu entre le premier {{ et le dernier }} correspondant
                // par le marqueur
                $paragraph = preg_replace(
                    '/\{\{[^\}]*?' . preg_quote($varName, '/') . '[^\}]*?\}\}/sU',
                    $marker,
                    $paragraph,
                    1 // Une seule fois
                );

                // Remplacer le marqueur par la variable propre
                $cleanVar = '<w:r><w:t>' . $fullVar . '</w:t></w:r>';
                $paragraph = str_replace($marker, $cleanVar, $paragraph);
            }
        }

        return $paragraph;
    },
    $xml
);

// Sauvegarder
$zip->deleteName('word/document.xml');
$zip->addFromString('word/document.xml', $xml);
$zip->close();

// Vérification
$zipCheck = new ZipArchive();
$zipCheck->open($templatePath);
$xmlCheck = $zipCheck->getFromName('word/document.xml');
preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $xmlCheck, $matchesAfter);
$fullTextAfter = implode('', $matchesAfter[1]);
preg_match_all('/\{\{([^}]+)\}\}/', $fullTextAfter, $varsAfter);
$zipCheck->close();

$varsAfterClean = array_unique($varsAfter[1]);

echo "✅ Nettoyage terminé !\n";
echo "   Variables préservées : " . count($varsAfterClean) . "/" . count($variables) . "\n\n";

if (count($varsAfterClean) < count($variables)) {
    echo "⚠️  Variables perdues :\n";
    $lost = array_diff($variables, $varsAfterClean);
    foreach ($lost as $var) {
        echo "   - {$var}\n";
    }
}

echo "\n📋 Variables finales :\n";
foreach ($varsAfterClean as $var) {
    echo "   - {$var}\n";
}
