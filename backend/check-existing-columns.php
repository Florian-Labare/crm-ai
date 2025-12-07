<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Schema;

$tables = [
    'clients',
    'conjoints',
    'bae_prevoyance',
    'bae_retraite',
    'bae_epargne',
    'sante_souhaits',
    'questionnaire_risques',
    'questionnaire_risque_financiers',
    'questionnaire_risque_connaissances',
];

echo "📋 COLONNES EXISTANTES PAR TABLE\n";
echo str_repeat("=", 80) . "\n\n";

foreach ($tables as $table) {
    $columns = Schema::getColumnListing($table);

    echo "📦 Table: {$table} (" . count($columns) . " colonnes)\n";
    echo str_repeat("-", 80) . "\n";

    foreach ($columns as $column) {
        echo "   - {$column}\n";
    }

    echo "\n";
}
