<?php

/**
 * Script robuste pour corriger les variables dans les templates DOCX
 * Gère les variables fragmentées entre plusieurs balises XML
 */

$templates = [
    'rc-assurance-vie.docx' => [
        '/\{\{Datedudocumentgénérer\}\}/u' => '{{current_date}}',
    ],
    'rc-per.docx' => [
        '/\{\{Datedudocumentgénéré\}\}/u' => '{{current_date}}',
    ],
];

$templatesDir = __DIR__ . '/storage/app/templates';

foreach ($templates as $templateName => $replacements) {
    $templatePath = $templatesDir . '/' . $templateName;

    echo "📄 Correction du template: {$templateName}\n";
    echo str_repeat("=", 80) . "\n";

    if (!file_exists($templatePath)) {
        echo "   ❌ Fichier introuvable\n\n";
        continue;
    }

    // Créer une backup
    $backupPath = $templatesDir . '/' . str_replace('.docx', '_backup_' . date('YmdHis') . '.docx', $templateName);
    if (!copy($templatePath, $backupPath)) {
        echo "   ❌ Impossible de créer une backup\n\n";
        continue;
    }
    echo "   ✅ Backup créée: " . basename($backupPath) . "\n";

    // Ouvrir le DOCX
    $zip = new ZipArchive();
    if ($zip->open($templatePath) !== TRUE) {
        echo "   ❌ Impossible d'ouvrir le fichier\n\n";
        continue;
    }

    // Lire le XML
    $xml = $zip->getFromName('word/document.xml');
    if (!$xml) {
        echo "   ❌ Impossible de lire document.xml\n";
        $zip->close();
        continue;
    }

    // Extraire le texte complet en préservant la structure
    preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $xml, $matches, PREG_OFFSET_CAPTURE);

    $allText = '';
    foreach ($matches[1] as $match) {
        $allText .= html_entity_decode($match[0], ENT_XML1);
    }

    echo "   📝 Texte extrait: " . strlen($allText) . " caractères\n";

    // Effectuer les remplacements dans le XML brut
    // On doit gérer les variables qui peuvent être fragmentées
    $replacementCount = 0;

    foreach ($replacements as $pattern => $replace) {
        // D'abord essayer un remplacement simple dans le texte concaténé
        if (preg_match($pattern, $allText)) {
            echo "   ✅ Variable trouvée dans le texte\n";

            // Pour gérer les fragmentations, on va remplacer dans le XML en permettant
            // des balises entre les caractères
            $escapedPattern = $pattern;
            // Convertir le pattern pour permettre des tags XML entre chaque caractère
            $chars = [];
            // Extraire les caractères du pattern (sans les delimiters)
            $cleanPattern = trim($pattern, '/u');
            $cleanPattern = stripslashes($cleanPattern); // Remove escapes

            // Pattern flexible qui permet des balises XML entre les caractères
            $flexiblePattern = str_replace(
                ['{{', '}}', 'é', 'è', 'ê', 'à', 'ù'],
                ['(<[^>]+>)?{(<[^>]+>)?{', '}(<[^>]+>)?}', '(é|e)', '(è|e)', '(ê|e)', '(à|a)', '(ù|u)'],
                $cleanPattern
            );

            // Insérer (<[^>]+>)? entre chaque caractère
            $flexPattern = '';
            for ($i = 0; $i < strlen($cleanPattern); $i++) {
                $char = $cleanPattern[$i];
                if ($i > 0) {
                    $flexPattern .= '(<[^>]+>)*';
                }
                if ($char === '{' || $char === '}' || $char === '\\') {
                    $flexPattern .= '\\' . $char;
                } else {
                    $flexPattern .= $char;
                }
            }

            // Remplacement simple sur le XML entier
            $originalXml = $xml;
            $xml = preg_replace($pattern, $replace, $xml, -1, $count);

            if ($count > 0) {
                echo "   ✅ Remplacé {$count} occurrence(s)\n";
                $replacementCount += $count;
            } else {
                // Si le remplacement simple n'a pas marché, supprimer manuellement
                // la séquence problématique en cherchant les fragments
                if ($templateName === 'rc-assurance-vie.docx') {
                    $xml = preg_replace('/<w:t[^>]*>Fait le \{\{Datedudocumentgénér<\/w:t>.*?<w:t[^>]*>er\}\}<\/w:t>/s',
                                       '<w:t>Fait le {{current_date}}</w:t>', $xml, -1, $count2);
                    if ($count2 > 0) {
                        echo "   ✅ Corrigé manuellement {$count2} occurrence(s)\n";
                        $replacementCount += $count2;
                    }
                } elseif ($templateName === 'rc-per.docx') {
                    $xml = preg_replace('/<w:t[^>]*>Fait le \{\{Datedudocumentgénér<\/w:t>.*?<w:t[^>]*>é\}\}<\/w:t>/s',
                                       '<w:t>Fait le {{current_date}}</w:t>', $xml, -1, $count2);
                    if ($count2 > 0) {
                        echo "   ✅ Corrigé manuellement {$count2} occurrence(s)\n";
                        $replacementCount += $count2;
                    }
                }
            }
        } else {
            echo "   ⚠️  Variable non trouvée dans le texte\n";
        }
    }

    if ($replacementCount > 0) {
        // Sauvegarder le XML modifié
        if ($zip->deleteName('word/document.xml') && $zip->addFromString('word/document.xml', $xml)) {
            echo "   ✅ Template mis à jour ({$replacementCount} correction(s))\n";
        } else {
            echo "   ❌ Erreur lors de la sauvegarde\n";
        }
    } else {
        echo "   ℹ️  Aucune correction effectuée\n";
    }

    $zip->close();
    echo "\n";
}

echo str_repeat("=", 80) . "\n";
echo "🏁 Correction terminée.\n";
