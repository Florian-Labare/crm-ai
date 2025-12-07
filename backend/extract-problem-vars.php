<?php

$templates = [
    'rc-assurance-vie.docx',
    'rc-per.docx',
    'recueil-ade.docx',
];

foreach ($templates as $templateName) {
    echo "\n📄 Template: {$templateName}\n";
    echo str_repeat("=", 80) . "\n";

    $zip = new ZipArchive();
    $zip->open(__DIR__ . '/storage/app/templates/' . $templateName);
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $xml, $matches);
    $fullText = implode('', $matches[1]);
    $fullText = html_entity_decode($fullText, ENT_XML1);

    preg_match_all('/\{\{([^}]+)\}\}/', $fullText, $varMatches);
    $variables = array_unique($varMatches[1]);

    $problemVars = [
        'impot_paye_n_1', 'SOCOGEA', 'Date', 'fumeur', 'nbkm'
    ];

    foreach ($variables as $var) {
        $var = trim($var);
        foreach ($problemVars as $needle) {
            if (stripos($var, $needle) !== false) {
                echo "Variable: {{$var}}\n";
                echo "Hex: " . bin2hex($var) . "\n";
                echo "Length: " . strlen($var) . "\n";

                // Test regex
                if (preg_match('/^([a-z_]+)(?:\[(\d+)\])?\.([a-z0-9_]+)$/i', $var)) {
                    echo "✅ Matches table.column format\n";
                } else {
                    echo "❌ Does NOT match table.column format\n";
                }
                echo "\n";
                break;
            }
        }
    }
}
