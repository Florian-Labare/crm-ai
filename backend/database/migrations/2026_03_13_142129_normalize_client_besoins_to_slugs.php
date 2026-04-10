<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    private function getMapping(): array
    {
        return [
            'prévoyance' => 'prevoyance',
            'prevoyance' => 'prevoyance',
            'décès' => 'prevoyance',
            'deces' => 'prevoyance',
            'invalidité' => 'prevoyance',
            'invalidite' => 'prevoyance',
            'incapacité' => 'prevoyance',
            'incapacite' => 'prevoyance',
            'arrêt de travail' => 'prevoyance',
            'arret de travail' => 'prevoyance',
            'protection sociale' => 'prevoyance',
            'garanties collectives' => 'prevoyance',
            'obsèques' => 'prevoyance',
            'obseques' => 'prevoyance',
            'retraite' => 'retraite',
            'per' => 'retraite',
            'plan epargne retraite' => 'retraite',
            'plan épargne retraite' => 'retraite',
            'pension' => 'retraite',
            'épargne' => 'epargne',
            'epargne' => 'epargne',
            'placement' => 'epargne',
            'assurance vie' => 'epargne',
            'assurance-vie' => 'epargne',
            'capitalisation' => 'epargne',
            'assurance vie capitalisation' => 'epargne',
            'pea' => 'epargne',
            'patrimoine' => 'epargne',
            'investissement' => 'epargne',
            'santé' => 'sante',
            'sante' => 'sante',
            'mutuelle' => 'sante',
            'complémentaire santé' => 'sante',
            'complementaire sante' => 'sante',
            'complémentaire' => 'sante',
            'complementaire' => 'sante',
            'emprunteur' => 'emprunteur',
            'ade' => 'emprunteur',
            'assurance emprunteur' => 'emprunteur',
            'assurance de prêt' => 'emprunteur',
            'assurance de pret' => 'emprunteur',
            'prêt' => 'emprunteur',
            'pret' => 'emprunteur',
            'crédit' => 'emprunteur',
            'credit' => 'emprunteur',
            'emprunt' => 'emprunteur',
        ];
    }

    public function up(): void
    {
        $mapping = $this->getMapping();

        $clients = DB::table('clients')
            ->whereNotNull('besoins')
            ->where('besoins', '!=', '[]')
            ->where('besoins', '!=', 'null')
            ->get(['id', 'besoins']);

        $updated = 0;
        $skipped = 0;

        foreach ($clients as $client) {
            $raw = is_string($client->besoins)
                ? json_decode($client->besoins, true)
                : (array) $client->besoins;

            if (empty($raw) || ! is_array($raw)) {
                continue;
            }

            $slugs = [];
            foreach ($raw as $besoin) {
                $key = mb_strtolower(trim((string) $besoin));
                $slug = $mapping[$key] ?? null;
                if ($slug && ! in_array($slug, $slugs)) {
                    $slugs[] = $slug;
                }
            }

            $newJson = json_encode($slugs);
            $origJson = json_encode(array_values($raw));

            if ($newJson === $origJson) {
                $skipped++;

                continue;
            }

            DB::table('clients')
                ->where('id', $client->id)
                ->update(['besoins' => $newJson]);

            $updated++;
        }

        Log::info('[MIGRATION] Besoins normalisés en slugs', [
            'updated' => $updated,
            'skipped' => $skipped,
        ]);
    }

    public function down(): void
    {
        // Irreversible : normalisation de données textuelles
    }
};
