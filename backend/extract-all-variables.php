<?php

require __DIR__ . '/vendor/autoload.php';

$templatePath = __DIR__ . '/storage/app/templates/Template Mandat.docx';

$zip = new ZipArchive();
if ($zip->open($templatePath) === TRUE) {
    $content = $zip->getFromName('word/document.xml');

    // Supprimer tous les espaces et retours à la ligne dans les balises w:t
    // pour reconstruire les variables fragmentées
    $content = preg_replace_callback(
        '/<w:t[^>]*>.*?<\/w:t>/s',
        function($matches) {
            return str_replace(["\n", "\r", "  "], '', $matches[0]);
        },
        $content
    );

    // Extraire le texte brut sans les balises XML
    preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $content, $textMatches);
    $fullText = implode('', $textMatches[1]);

    // Extraire toutes les variables {{variable}}
    preg_match_all('/\{\{([^}]+)\}\}/', $fullText, $matches);

    $variables = array_map('trim', $matches[1]);
    $variables = array_unique($variables);
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
