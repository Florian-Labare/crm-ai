<?php

/**
 * Nettoie tous les 5 templates RC pour éviter la fragmentation XML de Word
 */

require __DIR__ . '/vendor/autoload.php';

$templates = [
    'rc-assurance-vie.docx' => 'RC ASSURANCE VIE',
    'rc-emprunteur.docx' => 'RC EMPRUNTEUR',
    'rc-per.docx' => 'RC PER',
    'rc-prevoyance.docx' => 'RC PRÉVOYANCE',
    'rc-sante.docx' => 'RC SANTÉ',
];

$templatesDir = __DIR__ . '/storage/app/templates/';

echo "🧹 NETTOYAGE DE TOUS LES TEMPLATES\n";
echo str_repeat("=", 80) . "\n\n";

foreach ($templates as $filename => $displayName) {
    $templatePath = $templatesDir . $filename;

    if (!file_exists($templatePath)) {
        echo "❌ {$displayName} - Fichier non trouvé: {$filename}\n\n";
        continue;
    }

    echo "📄 Traitement: {$displayName}\n";
    echo str_repeat("-", 80) . "\n";

    // 1. Extraire les variables avant nettoyage
    $zip = new ZipArchive();
    if ($zip->open($templatePath) !== TRUE) {
        echo "   ❌ Impossible d'ouvrir le fichier\n\n";
        continue;
    }

    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    // Extraire toutes les variables
    preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $xml, $matches);
    $fullText = implode('', $matches[1]);
    $fullText = html_entity_decode($fullText, ENT_XML1);

    preg_match_all('/\{\{([^}]+)\}\}/', $fullText, $varMatches);
    $variables = array_unique($varMatches[1]);
    $variables = array_map('trim', $variables);
    $variables = array_filter($variables, fn($v) => !empty($v));

    $variablesBefore = count($variables);
    echo "   Variables détectées: {$variablesBefore}\n";

    // 2. Créer une backup
    $backupPath = $templatePath . '.backup_' . time();
    copy($templatePath, $backupPath);
    echo "   Backup créée: " . basename($backupPath) . "\n";

    // 3. Nettoyer le template
    $zip = new ZipArchive();
    if ($zip->open($templatePath) !== TRUE) {
        echo "   ❌ Impossible d'ouvrir pour nettoyage\n\n";
        continue;
    }

    $xml = $zip->getFromName('word/document.xml');

    // Nettoyer les variables fragmentées
    $xml = preg_replace_callback(
        '/<w:p\b[^>]*>(.*?)<\/w:p>/s',
        function($pMatch) use ($variables) {
            $paragraph = $pMatch[0];

            // Extraire tout le texte du paragraphe
            preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $paragraph, $tMatches);
            $paragraphText = implode('', $tMatches[1]);
            $paragraphText = html_entity_decode($paragraphText, ENT_XML1);

            // Pour chaque variable trouvée dans ce paragraphe
            foreach ($variables as $varName) {
                $fullVar = '{{' . $varName . '}}';

                if (strpos($paragraphText, $fullVar) !== false) {
                    // Remplacer par un marqueur temporaire unique
                    $marker = '___VAR_' . md5($varName . uniqid()) . '___';

                    // Supprimer toute la fragmentation autour de cette variable
                    $paragraph = preg_replace(
                        '/\{\{[^\}]*?' . preg_quote($varName, '/') . '[^\}]*?\}\}/sU',
                        $marker,
                        $paragraph,
                        1
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

    // Sauvegarder le XML nettoyé
    $zip->deleteName('word/document.xml');
    $zip->addFromString('word/document.xml', $xml);
    $zip->close();

    // 4. Vérifier que toutes les variables sont préservées
    $zip = new ZipArchive();
    $zip->open($templatePath);
    $xmlAfter = $zip->getFromName('word/document.xml');
    $zip->close();

    preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $xmlAfter, $matchesAfter);
    $fullTextAfter = implode('', $matchesAfter[1]);
    $fullTextAfter = html_entity_decode($fullTextAfter, ENT_XML1);

    preg_match_all('/\{\{([^}]+)\}\}/', $fullTextAfter, $varMatchesAfter);
    $variablesAfter = array_unique($varMatchesAfter[1]);
    $variablesAfter = array_map('trim', $variablesAfter);
    $variablesAfter = array_filter($variablesAfter, fn($v) => !empty($v));

    $variablesAfterCount = count($variablesAfter);

    if ($variablesAfterCount === $variablesBefore) {
        echo "   ✅ Nettoyage réussi: {$variablesAfterCount}/{$variablesBefore} variables préservées\n";
    } else {
        echo "   ⚠️  Attention: {$variablesAfterCount}/{$variablesBefore} variables préservées\n";

        // Afficher les variables manquantes
        $missing = array_diff($variables, $variablesAfter);
        if (!empty($missing)) {
            echo "   Variables manquantes: " . implode(', ', $missing) . "\n";
        }
    }

    echo "\n";
}

echo str_repeat("=", 80) . "\n";
echo "✅ Nettoyage terminé pour tous les templates !\n";
