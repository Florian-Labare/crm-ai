<?php

require __DIR__ . '/vendor/autoload.php';

$templatePath = __DIR__ . '/storage/app/templates/Template Mandat.docx';

$zip = new ZipArchive();
if ($zip->open($templatePath) === TRUE) {
    $content = $zip->getFromName('word/document.xml');

    // Extraire toutes les variables {{variable}}
    preg_match_all('/\{\{([^}]+)\}\}/', $content, $matches);

    // Nettoyer et dédupliquer
    $variables = array_map('trim', $matches[1]);
    $variables = array_unique($variables);

    // Filtrer les variables qui contiennent du XML
    $variables = array_filter($variables, function($var) {
        return !str_contains($var, '<') && !str_contains($var, '>');
    });

    sort($variables);

    echo "Variables trouvées dans le template (" . count($variables) . ") :\n";
    echo "=====================================\n\n";

    foreach ($variables as $var) {
        echo "- {$var}\n";
    }

    $zip->close();
} else {
    echo "Erreur : Impossible d'ouvrir le template\n";
}
