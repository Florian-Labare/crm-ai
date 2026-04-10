<?php

/**
 * Script pour corriger les variables invalides dans les templates DOCX
 */
$templates = [
    'rc-assurance-vie.docx' => [
        '{SOCOGEAvousindique}' => '{current_date}',
        '{SOCOGEAvousindiqueque}' => '',
        '{Datedudocumentgénérer}' => '{current_date}',
        '{{Datedudocumentgénérer}' => '{{current_date}}',  // Malformed variable
    ],
    'rc-per.docx' => [
        '{SOCOGEAvousindique}' => '{current_date}',
        '{SOCOGEAvousindiqueque}' => '',
        '{Datedudocumentgénéré}' => '{current_date}',
        '{{Datedudocumentgénéré}' => '{{current_date}}',  // Malformed variable
    ],
    'recueil-ade.docx' => [
        '{fumeurconjoint}' => '{conjoints.fumeur}',
        '{nbkmparanconjoint}' => '{conjoints.km_parcourus_annuels}',
    ],
];

$templatesDir = __DIR__.'/storage/app/templates';

foreach ($templates as $templateName => $replacements) {
    $templatePath = $templatesDir.'/'.$templateName;

    echo "📄 Correction du template: {$templateName}\n";
    echo str_repeat('=', 80)."\n";

    if (! file_exists($templatePath)) {
        echo "   ❌ Fichier introuvable: {$templatePath}\n\n";

        continue;
    }

    // Créer une backup
    $backupPath = $templatesDir.'/'.str_replace('.docx', '_backup_'.date('YmdHis').'.docx', $templateName);
    if (! copy($templatePath, $backupPath)) {
        echo "   ❌ Impossible de créer une backup\n\n";

        continue;
    }
    echo '   ✅ Backup créée: '.basename($backupPath)."\n";

    // Ouvrir le DOCX
    $zip = new ZipArchive();
    if ($zip->open($templatePath) !== true) {
        echo "   ❌ Impossible d'ouvrir le fichier\n\n";

        continue;
    }

    // Lire le XML
    $xml = $zip->getFromName('word/document.xml');
    if (! $xml) {
        echo "   ❌ Impossible de lire document.xml\n";
        $zip->close();

        continue;
    }

    // Effectuer les remplacements
    $originalXml = $xml;
    $replacementCount = 0;

    foreach ($replacements as $search => $replace) {
        $count = 0;
        $xml = str_replace($search, $replace, $xml, $count);
        if ($count > 0) {
            echo "   ✅ Remplacé '{$search}' par '{$replace}' ({$count} occurrence(s))\n";
            $replacementCount += $count;
        } else {
            echo "   ⚠️  Variable '{$search}' non trouvée\n";
        }
    }

    if ($replacementCount > 0) {
        // Sauvegarder le XML modifié
        if ($zip->deleteName('word/document.xml') && $zip->addFromString('word/document.xml', $xml)) {
            echo "   ✅ Template mis à jour ({$replacementCount} remplacement(s))\n";
        } else {
            echo "   ❌ Erreur lors de la sauvegarde\n";
        }
    } else {
        echo "   ℹ️  Aucun remplacement effectué\n";
    }

    $zip->close();
    echo "\n";
}

echo str_repeat('=', 80)."\n";
echo "🏁 Correction terminée.\n";
