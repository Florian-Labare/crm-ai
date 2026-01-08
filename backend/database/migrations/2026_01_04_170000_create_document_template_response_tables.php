<?php

use App\Services\DirectTemplateMapper;
use App\Services\DocumentTemplateFieldService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $templates = [
        'templates/recueil-global-pp-2025.docx',
        'templates/Template Mandat.docx',
        'templates/rc-assurance-vie.docx',
        'templates/rc-emprunteur.docx',
        'templates/rc-per.docx',
        'templates/rc-prevoyance.docx',
        'templates/rc-sante.docx',
        'templates/recueil-ade.docx',
    ];

    public function up(): void
    {
        $mapper = new DirectTemplateMapper();
        $fieldService = new DocumentTemplateFieldService();

        foreach ($this->templates as $filePath) {
            $absolutePath = storage_path('app/' . $filePath);
            if (!file_exists($absolutePath)) {
                continue;
            }

            $variables = $mapper->extractTemplateVariables($absolutePath);
            $columnMap = $fieldService->mapVariablesToColumns($variables);
            $tableName = $fieldService->tableNameForPath($filePath);

            if (!Schema::hasTable($tableName)) {
                Schema::create($tableName, function (Blueprint $table) use ($columnMap) {
                    $table->id();
                    $table->foreignId('client_id')->constrained()->onDelete('cascade');
                    foreach (array_values($columnMap) as $column) {
                        $table->text($column)->nullable();
                    }
                    $table->timestamps();
                    $table->unique('client_id');
                });
                continue;
            }

            $missing = [];
            foreach (array_values($columnMap) as $column) {
                if (!Schema::hasColumn($tableName, $column)) {
                    $missing[] = $column;
                }
            }

            if (!empty($missing)) {
                Schema::table($tableName, function (Blueprint $table) use ($missing) {
                    foreach ($missing as $column) {
                        $table->text($column)->nullable();
                    }
                });
            }
        }
    }

    public function down(): void
    {
        $fieldService = new DocumentTemplateFieldService();

        foreach ($this->templates as $filePath) {
            $tableName = $fieldService->tableNameForPath($filePath);
            if (Schema::hasTable($tableName)) {
                Schema::drop($tableName);
            }
        }
    }
};
