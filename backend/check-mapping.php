<?php

require __DIR__ . '/vendor/autoload.php';

// Variables du template (32 variables)
$templateVars = [
    'Datedenaissance', 'Datesituationmatri', 'Lieudenaissance', 'Mail', 'Mandatairesocial',
    'Payeur', 'Profession', 'Siouiprévoyance', 'Situationactuelle', 'Situationmatrimoniale',
    'Statut', 'activitéssportives', 'ageretraitedepart', 'arrettravail', 'budgetsantemax',
    'casdecesproche', 'chargesprocouvert', 'chargesprofessionnelles', 'contratsanteindiv',
    'datedocument', 'dategaranties', 'enfantacharge', 'fumeur', 'horizonibjectif',
    'invaliditecouvert', 'nom', 'objectifrapport', 'prenom', 'profilrisqueclient',
    'prévoyanceindividuelle', 'risquesparticuliers', 'tel'
];

// Charger le fichier de mapping
$mapping = include __DIR__ . '/config/document_mapping.php';

echo "Vérification du mapping des variables\n";
echo "======================================\n\n";

$missing = [];
$present = [];

foreach ($templateVars as $var) {
    if (isset($mapping[$var])) {
        $present[] = $var;
        $config = $mapping[$var];

        // Afficher le mapping
        if (isset($config['source'])) {
            $source = $config['source'];
            $field = $config['field'] ?? 'computed';
            $format = $config['format'] ?? 'text';
            echo "✅ {$var} → {$source}.{$field} (format: {$format})\n";
        } elseif (isset($config['default'])) {
            echo "⚠️  {$var} → default: '{$config['default']}'\n";
        } else {
            echo "❓ {$var} → configuration inconnue\n";
        }
    } else {
        $missing[] = $var;
        echo "❌ {$var} → MANQUANT DANS LE MAPPING\n";
    }
}

echo "\n";
echo "Résumé:\n";
echo "-------\n";
echo "Variables mappées: " . count($present) . "/" . count($templateVars) . "\n";
echo "Variables manquantes: " . count($missing) . "\n";

if (!empty($missing)) {
    echo "\nVariables à ajouter:\n";
    foreach ($missing as $var) {
        echo "  - {$var}\n";
    }
}
