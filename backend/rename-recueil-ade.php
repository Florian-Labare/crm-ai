<?php

/**
 * Script pour renommer les variables du Template RECUEIL ADE
 */

require __DIR__ . '/vendor/autoload.php';

$mapping = require __DIR__ . '/variable-mapping-complete.php';

$templatePath = __DIR__ . '/storage/app/templates/recueil-ade.docx';

echo "🔄 RENOMMAGE DES VARIABLES DANS LE TEMPLATE RECUEIL ADE\n";
echo str_repeat("=", 80) . "\n\n";

if (!file_exists($templatePath)) {
    echo "❌ Template non trouvé: recueil-ade.docx\n";
    exit(1);
}

echo "📄 Traitement: Template RECUEIL ADE\n";
echo str_repeat("-", 80) . "\n";

// 1. Créer une backup avant modification
$backupPath = $templatePath . '.backup_renaming_' . time();
copy($templatePath, $backupPath);
echo "   Backup créée: " . basename($backupPath) . "\n";

// 2. Ouvrir le template
$zip = new ZipArchive();
if ($zip->open($templatePath) !== TRUE) {
    echo "   ❌ Impossible d'ouvrir le fichier\n";
    exit(1);
}

$xml = $zip->getFromName('word/document.xml');

// 3. Extraire toutes les variables actuelles
preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $xml, $matches);
$fullText = implode('', $matches[1]);
$fullText = html_entity_decode($fullText, ENT_XML1);

preg_match_all('/\{\{([^}]+)\}\}/', $fullText, $varMatches);
$variables = array_unique($varMatches[1]);
$variables = array_map('trim', $variables);
$variables = array_filter($variables, fn($v) => !empty($v));

echo "   Variables trouvées: " . count($variables) . "\n";

$replacements = [];
$notMapped = [];

// 4. Pour chaque variable, créer le remplacement
foreach ($variables as $varName) {
    if (isset($mapping[$varName])) {
        $config = $mapping[$varName];

        if (isset($config['type'])) {
            // Variable computed ou fixed
            if ($config['type'] === 'computed') {
                $newVar = '{{' . $config['value'] . '}}';
            } else if ($config['type'] === 'fixed') {
                // Les variables fixes restent en l'état pour l'instant
                $newVar = '{{' . $varName . '}}';
            }
        } else {
            // Variable mappée à une colonne DB
            $table = $config['table'];
            $column = $config['column'];

            // Format: {{table.colonne}}
            if (isset($config['index'])) {
                // Pour les enfants avec index
                $newVar = '{{' . $table . '[' . $config['index'] . '].' . $column . '}}';
            } else {
                $newVar = '{{' . $table . '.' . $column . '}}';
            }
        }

        $oldVar = '{{' . $varName . '}}';
        $replacements[$oldVar] = $newVar;

        echo "   ✓ {$oldVar} → {$newVar}\n";
    } else {
        $notMapped[] = $varName;
    }
}

if (!empty($notMapped)) {
    echo "   ⚠️  Variables non mappées (" . count($notMapped) . "): " . implode(', ', $notMapped) . "\n";
}

// 5. Remplacer les variables dans le XML
foreach ($replacements as $oldVar => $newVar) {
    $xml = preg_replace_callback(
        '/<w:p\b[^>]*>(.*?)<\/w:p>/s',
        function($pMatch) use ($oldVar, $newVar) {
            $paragraph = $pMatch[0];

            // Extraire tout le texte du paragraphe
            preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $paragraph, $tMatches);
            $paragraphText = implode('', $tMatches[1]);
            $paragraphText = html_entity_decode($paragraphText, ENT_XML1);

            // Si l'ancienne variable est dans ce paragraphe
            if (strpos($paragraphText, $oldVar) !== false) {
                // Supprimer toute la fragmentation et remplacer par la nouvelle variable
                $marker = '___MARKER_' . md5($oldVar . uniqid()) . '___';

                // Chercher et marquer la variable fragmentée
                $pattern = '/\{\{[^}]*?' . preg_quote(trim($oldVar, '{}'), '/') . '[^}]*?\}\}/sU';
                $paragraph = preg_replace($pattern, $marker, $paragraph, 1);

                // Remplacer le marqueur par la nouvelle variable propre
                $cleanVar = '<w:r><w:t>' . htmlspecialchars($newVar, ENT_XML1) . '</w:t></w:r>';
                $paragraph = str_replace($marker, $cleanVar, $paragraph);
            }

            return $paragraph;
        },
        $xml
    );
}

// 6. Sauvegarder le XML modifié
$zip->deleteName('word/document.xml');
$zip->addFromString('word/document.xml', $xml);
$zip->close();

// 7. Vérifier les nouvelles variables
$zip = new ZipArchive();
$zip->open($templatePath);
$xmlAfter = $zip->getFromName('word/document.xml');
$zip->close();

preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $xmlAfter, $matchesAfter);
$fullTextAfter = implode('', $matchesAfter[1]);
$fullTextAfter = html_entity_decode($fullTextAfter, ENT_XML1);

preg_match_all('/\{\{([^}]+)\}\}/', $fullTextAfter, $varMatchesAfter);
$variablesAfter = array_unique($varMatchesAfter[1]);
$variablesAfterCount = count($variablesAfter);

echo "   ✅ Renommage terminé: {$variablesAfterCount} variables dans le template final\n";

echo "\n" . str_repeat("=", 80) . "\n";
echo "✅ Renommage terminé pour le template RECUEIL ADE !\n";
