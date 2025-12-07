<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Schema;

echo "🔍 GÉNÉRATION DE LA MIGRATION BASÉE SUR LES CHAMPS EXISTANTS\n";
echo str_repeat("=", 80) . "\n\n";

// Définir les champs à créer par table
$fieldsToCreate = [
    'clients' => [
        'situation_professionnelle' => ['type' => 'text', 'after' => 'profession'],
        'km_parcourus_annuels' => ['type' => 'integer', 'after' => 'niveau_activites_sportives'],
        'genre' => ['type' => 'string:1', 'after' => 'prenom'],
    ],
    'conjoints' => [
        'travailleur_independant' => ['type' => 'boolean', 'after' => 'chef_entreprise'],
        'situation_professionnelle' => ['type' => 'text', 'after' => 'profession'],
        'statut' => ['type' => 'string', 'after' => 'situation_professionnelle'],
        'niveau_activite_sportive' => ['type' => 'text', 'after' => 'situation_actuelle_statut'],
        'details_activites_sportives' => ['type' => 'text', 'after' => 'niveau_activite_sportive'],
        'situation_chomage' => ['type' => 'boolean', 'after' => 'situation_professionnelle'],
        'code_postal' => ['type' => 'string:10', 'after' => 'adresse'],
        'ville' => ['type' => 'string', 'after' => 'code_postal'],
    ],
    'bae_prevoyance' => [
        'deplacements_professionnels' => ['type' => 'text', 'after' => 'payeur'],
        'deplacements_professionnels_conjoint' => ['type' => 'text', 'after' => 'deplacements_professionnels'],
        'duree_indemnisation_frais_pro' => ['type' => 'string', 'after' => 'duree_indemnisation_souhaitee'],
        'denomination_contrat' => ['type' => 'string', 'after' => 'contrat_en_place'],
        'montant_garanti' => ['type' => 'decimal:12,2', 'after' => 'capital_deces_souhaite'],
        'souhaite_garantie_outillage' => ['type' => 'boolean', 'after' => 'souhaite_couvrir_charges_professionnelles'],
    ],
    'bae_retraite' => [
        'date_evenement_retraite' => ['type' => 'date', 'after' => 'age_depart_retraite'],
    ],
    'sante_souhaits' => [
        'souhaite_medecine_douce' => ['type' => 'boolean', 'after' => 'niveau_protheses_auditives'],
        'souhaite_cures_thermales' => ['type' => 'boolean', 'after' => 'souhaite_medecine_douce'],
        'souhaite_autres_protheses' => ['type' => 'boolean', 'after' => 'souhaite_cures_thermales'],
        'souhaite_protection_juridique' => ['type' => 'boolean', 'after' => 'souhaite_autres_protheses'],
        'souhaite_protection_juridique_conjoint' => ['type' => 'boolean', 'after' => 'souhaite_protection_juridique'],
    ],
];

// Vérifier quels champs existent déjà
$totalToCreate = 0;
$totalExist = 0;

foreach ($fieldsToCreate as $table => $fields) {
    $existingColumns = Schema::getColumnListing($table);

    echo "📦 Table: {$table}\n";
    echo str_repeat("-", 80) . "\n";

    foreach ($fields as $field => $config) {
        if (in_array($field, $existingColumns)) {
            echo "   ✅ {$field} (existe déjà)\n";
            $totalExist++;
        } else {
            echo "   🆕 {$field} (à créer)\n";
            $totalToCreate++;
        }
    }

    echo "\n";
}

echo str_repeat("=", 80) . "\n";
echo "📊 TOTAL:\n";
echo "   - Champs à créer: {$totalToCreate}\n";
echo "   - Champs existants: {$totalExist}\n\n";

// Générer le code de migration
echo "📝 CODE DE MIGRATION GÉNÉRÉ:\n";
echo str_repeat("=", 80) . "\n\n";

foreach ($fieldsToCreate as $table => $fields) {
    $existingColumns = Schema::getColumnListing($table);
    $hasFieldsToCreate = false;

    // Vérifier si on a au moins un champ à créer
    foreach ($fields as $field => $config) {
        if (!in_array($field, $existingColumns)) {
            $hasFieldsToCreate = true;
            break;
        }
    }

    if (!$hasFieldsToCreate) {
        continue;
    }

    echo "        // Table: {$table}\n";
    echo "        Schema::table('{$table}', function (Blueprint \$table) {\n";

    foreach ($fields as $field => $config) {
        if (in_array($field, $existingColumns)) {
            continue; // Skip existing fields
        }

        $type = $config['type'];
        $after = $config['after'];

        // Generate migration line
        if (str_contains($type, ':')) {
            [$typeBase, $typeParams] = explode(':', $type);
            if ($typeBase === 'string') {
                $line = "            \$table->string('{$field}', {$typeParams})->nullable()->after('{$after}');";
            } elseif ($typeBase === 'decimal') {
                $line = "            \$table->decimal('{$field}', " . str_replace(',', ', ', $typeParams) . ")->nullable()->after('{$after}');";
            }
        } else {
            $line = "            \$table->{$type}('{$field}')->nullable()->after('{$after}');";
        }

        echo $line . "\n";
    }

    echo "        });\n\n";
}

echo "\n✅ Migration générée !\n";
