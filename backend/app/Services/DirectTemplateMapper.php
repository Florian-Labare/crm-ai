<?php

namespace App\Services;

use App\Models\Client;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service de mapping direct des variables de templates vers les colonnes de la base de données
 *
 * Format des variables: {{table.colonne}} ou {{table[index].colonne}}
 * Exemples:
 * - {{clients.nom}}
 * - {{clients.date_naissance}}
 * - {{bae_retraite.revenus_annuels}}
 * - {{enfants[0].prenom}}
 * - {{current_date}}
 */
class DirectTemplateMapper
{
    /**
     * Mapping des noms de tables vers les relations Eloquent
     */
    private const TABLE_RELATIONS = [
        'clients' => null, // Table principale, pas de relation
        'conjoints' => 'conjoint',
        'enfants' => 'enfants',
        'bae_epargne' => 'baeEpargne',
        'bae_prevoyance' => 'baePrevoyance',
        'bae_retraite' => 'baeRetraite',
        'sante_souhaits' => 'santeSouhait',
        'questionnaire_risques' => 'questionnaireRisque',
        'questionnaire_risque_financiers' => 'questionnaireRisque.financier',
        'questionnaire_risque_connaissances' => 'questionnaireRisque.connaissances',
        'users' => 'user',
    ];

    /**
     * Colonnes qui doivent être formatées comme des dates
     */
    private const DATE_COLUMNS = [
        'date_naissance', 'date_situation_matrimoniale', 'date_evenement_professionnel',
        'date_evenement_retraite', 'date_effet', 'donation_date', 'der_date_rdv',
        'date_ouverture_souscription', 'created_at', 'updated_at',
    ];

    /**
     * Colonnes qui doivent être formatées comme des montants
     */
    private const CURRENCY_COLUMNS = [
        'revenus_annuels', 'revenus_annuels_foyer', 'impot_revenu', 'impot_paye_n_1',
        'cotisations_annuelles', 'montant_epargne_disponible', 'donation_montant',
        'capacite_epargne_estimee', 'actifs_financiers_total', 'actifs_immo_total',
        'actifs_autres_total', 'passifs_total_emprunts', 'charges_totales',
        'cotisations', 'revenu_a_garantir', 'montant_annuel_charges_professionnelles',
        'montant_charges_professionnelles_a_garantir', 'capital_deces_souhaite',
        'montant_garanti', 'garanties_obseques', 'rente_enfants', 'rente_conjoint',
        'budget_mensuel_maximum', 'chargespro', 'valeur_actuelle_estimee',
        'valeur_acquisition', 'valeur_actuelle',
    ];

    /**
     * Colonnes qui doivent être formatées comme des booléens
     */
    private const BOOLEAN_COLUMNS = [
        'fumeur', 'activites_sportives', 'risques_professionnels', 'chef_entreprise',
        'mandataire_social', 'travailleur_independant', 'consentement_audio',
        'fiscalement_a_charge', 'garde_alternee', 'epargne_disponible',
        'donation_realisee', 'souhaite_couverture_invalidite', 'souhaite_couvrir_charges_professionnelles',
        'souhaite_garantie_outillage', 'garantir_totalite_charges_professionnelles',
        'bilan_retraite_disponible', 'complementaire_retraite_mise_en_place',
        'souhaite_medecine_douce', 'souhaite_cures_thermales', 'souhaite_autres_protheses',
        'souhaite_protection_juridique', 'souhaite_protection_juridique_conjoint',
        'placements_inquietude', 'epargne_precaution', 'situation_chomage',
    ];

    /**
     * Mappe toutes les variables d'un template avec les données du client
     */
    public function mapVariables(Client $client, array $templateVariables): array
    {
        // Charger toutes les relations nécessaires
        $this->loadRelations($client, $templateVariables);

        $mappedVariables = [];

        foreach ($templateVariables as $variable) {
            $mappedVariables[$variable] = $this->resolveVariable($client, $variable);
        }

        Log::info('DirectTemplateMapper: Variables mapped', [
            'total' => count($mappedVariables),
            'client_id' => $client->id,
        ]);

        return $mappedVariables;
    }

    /**
     * Charge dynamiquement les relations nécessaires basées sur les variables du template
     */
    private function loadRelations(Client $client, array $variables): void
    {
        $relationsToLoad = [];

        foreach ($variables as $variable) {
            // Parser le nom de table depuis la variable
            if (preg_match('/^([a-z_]+)(?:\[\d+\])?\./i', $variable, $matches)) {
                $tableName = $matches[1];

                if (isset(self::TABLE_RELATIONS[$tableName]) && self::TABLE_RELATIONS[$tableName] !== null) {
                    $relation = self::TABLE_RELATIONS[$tableName];
                    if (!in_array($relation, $relationsToLoad)) {
                        $relationsToLoad[] = $relation;
                    }
                }
            }
        }

        if (!empty($relationsToLoad)) {
            $client->load($relationsToLoad);
        }
    }

    /**
     * Résout la valeur d'une variable
     */
    private function resolveVariable(Client $client, string $variable): string
    {
        if (in_array($variable, ['bae_epargne.actifs_financiers_total', 'actifs_financiers_total'], true)) {
            $computedTotal = $this->computeActifsFinanciersTotal($client);
            if ($computedTotal !== null) {
                return $this->formatValue($computedTotal, 'actifs_financiers_total');
            }
        }

        if (in_array($variable, ['bae_epargne.actifs_immo_total', 'actifs_immo_total'], true)) {
            $computedTotal = $this->computeBiensImmobiliersTotal($client);
            if ($computedTotal !== null) {
                return $this->formatValue($computedTotal, 'actifs_immo_total');
            }
        }

        $legacyImmo = $this->resolveLegacyImmoVariable($client, $variable);
        if ($legacyImmo !== null) {
            return $legacyImmo;
        }

        $legacyFinancier = $this->resolveLegacyFinancierVariable($client, $variable);
        if ($legacyFinancier !== null) {
            return $legacyFinancier;
        }

        // Cas spéciaux - dates
        if ($variable === 'current_date' || $variable === 'Date' || $variable === 'Datedocgener') {
            return Carbon::now()->format('d/m/Y');
        }

        // Cas spéciaux - fiscalcharge1, fiscalcharge2, fiscalcharge3 (ancien format)
        if (preg_match('/^fiscalcharge(\d+)$/i', $variable, $matches)) {
            $index = (int)$matches[1] - 1; // fiscalcharge1 = index 0
            $enfant = $client->enfants->get($index);
            if ($enfant) {
                return $enfant->fiscalement_a_charge ? 'Oui' : 'Non';
            }
            return '';
        }

        // Parser la variable au format: table.colonne ou table[index].colonne
        if (preg_match('/^([a-z_]+)(?:\[(\d+)\])?\.(.+)$/i', $variable, $matches)) {
            $tableName = $matches[1];
            $index = isset($matches[2]) ? (int)$matches[2] : null;
            $columnName = $matches[3];

            return $this->getValueFromTable($client, $tableName, $columnName, $index);
        }

        // Variable non reconnue
        Log::warning('DirectTemplateMapper: Variable format not recognized', ['variable' => $variable]);
        return '';
    }

    /**
     * Récupère la valeur depuis une table/relation
     */
    private function getValueFromTable(Client $client, string $tableName, string $columnName, ?int $index = null): string
    {
        // Cas spécial: count des enfants
        if ($tableName === 'enfants' && $columnName === 'count') {
            return (string) $client->enfants->count();
        }

        // Cas spécial: full_name des enfants
        if ($tableName === 'enfants' && $columnName === 'full_name') {
            $enfant = $index !== null ? $client->enfants->get($index) : null;
            if ($enfant) {
                return trim($enfant->prenom . ' ' . $enfant->nom);
            }
            return '';
        }

        // Table principale (clients)
        if ($tableName === 'clients') {
            $value = $client->{$columnName} ?? null;
            return $this->formatValue($value, $columnName);
        }

        // Relations
        $relationName = self::TABLE_RELATIONS[$tableName] ?? null;

        if ($relationName === null) {
            Log::warning('DirectTemplateMapper: Unknown table', ['table' => $tableName]);
            return '';
        }

        // Naviguer dans la relation (peut contenir des points pour les relations imbriquées)
        $relationParts = explode('.', $relationName);
        $relatedModel = $client;

        foreach ($relationParts as $part) {
            $relatedModel = $relatedModel->{$part} ?? null;
            if ($relatedModel === null) {
                return '';
            }
        }

        // Si c'est une collection (ex: enfants), récupérer l'élément à l'index
        if ($index !== null && method_exists($relatedModel, 'get')) {
            $relatedModel = $relatedModel->get($index);
            if ($relatedModel === null) {
                return '';
            }
        }

        // Récupérer la valeur de la colonne
        $value = $relatedModel->{$columnName} ?? null;

        return $this->formatValue($value, $columnName);
    }

    private function resolveLegacyImmoVariable(Client $client, string $variable): ?string
    {
        $lower = strtolower($variable);
        $number = null;
        $field = null;

        if (preg_match('/immo(\\d+)|(\\d+)immo/i', $variable, $numberMatches)) {
            $number = (int) ($numberMatches[1] ?: $numberMatches[2]);
        } elseif (preg_match('/(valeuractuelleestimee|valeurestimee)(\\d+)/i', $variable, $numberMatches)) {
            $number = (int) $numberMatches[2];
            $field = 'valeur_actuelle_estimee';
        } else {
            return null;
        }

        $index = $number >= 4 ? $number - 4 : $number - 1;
        if ($index < 0) {
            $index = 0;
        }

        $client->loadMissing('biensImmobiliers');
        $bien = $client->biensImmobiliers->get($index);
        if (!$bien) {
            return '';
        }

        if (!$field && str_starts_with($lower, 'designation')) {
            $field = 'designation';
        } elseif (!$field && str_starts_with($lower, 'detenteur')) {
            $field = 'detenteur';
        } elseif (!$field && (str_contains($lower, 'formedeproprio') || str_contains($lower, 'formeproprio'))) {
            $field = 'forme_propriete';
        } elseif (!$field && (str_contains($lower, 'valeuractuelleestimee') || str_contains($lower, 'valeurestimee'))) {
            $field = 'valeur_actuelle_estimee';
        } elseif (!$field && (str_contains($lower, 'anneeacquisition') || str_contains($lower, 'anneeacqusiition'))) {
            $field = 'annee_acquisition';
        } elseif (!$field && str_contains($lower, 'valeuracquisition')) {
            $field = 'valeur_acquisition';
        }

        if (!$field) {
            return null;
        }

        return $this->formatValue($bien->{$field} ?? null, $field);
    }

    private function resolveLegacyFinancierVariable(Client $client, string $variable): ?string
    {
        $lower = strtolower($variable);
        $number = null;

        if (preg_match('/financier(\\d+)/i', $variable, $numberMatches)) {
            $number = (int) $numberMatches[1];
        } elseif (preg_match('/(\\d+)financier/i', $variable, $numberMatches)) {
            $number = (int) $numberMatches[1];
        } elseif (preg_match('/valeurfinancier(\\d+)/i', $variable, $numberMatches)) {
            $number = (int) $numberMatches[1];
        } else {
            return null;
        }

        $index = $number - 1;
        if ($index < 0) {
            $index = 0;
        }

        $financierItem = $this->getLegacyFinancierItem($client, $index);
        if (!$financierItem) {
            return '';
        }

        $field = null;

        if (str_starts_with($lower, 'nature')) {
            $field = 'nature';
        } elseif (str_starts_with($lower, 'etablissement')) {
            $field = 'etablissement';
        } elseif (str_starts_with($lower, 'detenteur')) {
            $field = 'detenteur';
        } elseif (str_contains($lower, 'ouvertsouscrit')) {
            $field = 'date_ouverture_souscription';
        } elseif (str_starts_with($lower, 'valeur') || str_contains($lower, 'valeurfinancier')) {
            $field = 'valeur_actuelle';
        }

        if (!$field) {
            return null;
        }

        if ($financierItem['type'] === 'crypto') {
            return $this->formatCryptoFinancierValue($financierItem['model'], $field);
        }

        if ($financierItem['type'] === 'other') {
            return $this->formatOtherFinancierValue($financierItem['model'], $field);
        }

        return $this->formatValue($financierItem['model']->{$field} ?? null, $field);
    }

    private function getLegacyFinancierItem(Client $client, int $index): ?array
    {
        $client->loadMissing(['actifsFinanciers', 'autresEpargnes', 'baeEpargne']);
        $items = [];

        foreach ($client->actifsFinanciers as $actif) {
            $items[] = ['type' => 'actif', 'model' => $actif];
        }

        $cryptoEntries = $this->getBaeEpargneOtherEntries($client, true);
        if (!empty($cryptoEntries)) {
            foreach ($cryptoEntries as $entry) {
                $items[] = ['type' => 'other', 'model' => $entry];
            }
        } else {
            $cryptoEpargnes = $this->filterCryptoEpargnes($client);
            foreach ($cryptoEpargnes as $crypto) {
                $items[] = ['type' => 'crypto', 'model' => $crypto];
            }
        }

        if (isset($items[$index])) {
            return $items[$index];
        }

        return null;
    }

    private function filterCryptoEpargnes(Client $client): \Illuminate\Support\Collection
    {
        return $client->autresEpargnes
            ->filter(function ($epargne) {
                return $this->isCryptoDesignation($epargne->designation ?? '');
            })
            ->values();
    }

    private function isCryptoDesignation(string $designation): bool
    {
        $value = Str::lower($designation);
        $keywords = [
            'crypto', 'cryptomonnaie', 'cryptocurrency', 'bitcoin', 'btc', 'ethereum', 'eth',
            'usdt', 'usdc', 'bnb', 'solana', 'doge', 'xrp', 'cardano', 'ada', 'litecoin', 'ltc',
        ];

        foreach ($keywords as $keyword) {
            if (Str::contains($value, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function formatCryptoFinancierValue($epargne, string $field): string
    {
        if ($field === 'nature') {
            return (string) ($epargne->designation ?? '');
        }

        if ($field === 'detenteur') {
            return (string) ($epargne->detenteur ?? '');
        }

        if ($field === 'valeur_actuelle') {
            return $this->formatValue($epargne->valeur ?? null, 'valeur_actuelle');
        }

        return '';
    }

    private function formatOtherFinancierValue(array $entry, string $field): string
    {
        if ($field === 'nature') {
            return (string) ($entry['designation'] ?? '');
        }

        if ($field === 'valeur_actuelle') {
            return $this->formatValue($entry['valeur'] ?? null, 'valeur_actuelle');
        }

        if ($field === 'detenteur') {
            return (string) ($entry['detenteur'] ?? '');
        }

        return '';
    }

    private function getBaeEpargneOtherEntries(Client $client, bool $onlyCrypto = false): array
    {
        $details = $client->baeEpargne?->actifs_autres_details;
        if (is_string($details)) {
            $decoded = json_decode($details, true);
            if (is_array($decoded)) {
                $details = $decoded;
            } else {
                $details = array_filter(array_map('trim', preg_split('/[\\n,]+/', $details)));
            }
        }

        if (!is_array($details) || empty($details)) {
            return [];
        }

        $entries = [];
        foreach ($details as $detail) {
            if (is_array($detail)) {
                $designation = $detail['designation'] ?? ($detail['label'] ?? '');
                if ($onlyCrypto && !$this->isCryptoDesignation($designation)) {
                    continue;
                }
                $entries[] = [
                    'designation' => $designation,
                    'valeur' => $detail['valeur'] ?? $this->parseNumericValue($detail['montant'] ?? null),
                    'detenteur' => $detail['detenteur'] ?? null,
                ];
                continue;
            }

            if (!is_string($detail)) {
                continue;
            }

            $parts = array_map('trim', explode(':', $detail, 2));
            $designation = $parts[0] ?? '';
            if ($onlyCrypto && !$this->isCryptoDesignation($designation)) {
                continue;
            }
            $valeur = isset($parts[1]) ? $this->parseNumericValue($parts[1]) : null;

            $entries[] = [
                'designation' => $designation,
                'valeur' => $valeur,
                'detenteur' => null,
            ];
        }

        return $entries;
    }

    private function parseNumericValue($value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $raw = (string) $value;
        $clean = preg_replace('/[^0-9,.-]/', '', $raw);
        if ($clean === '' || $clean === null) {
            return null;
        }

        if (str_contains($clean, ',') && str_contains($clean, '.')) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        } elseif (str_contains($clean, ',')) {
            $clean = str_replace(',', '.', $clean);
        }

        if (!is_numeric($clean)) {
            return null;
        }

        return (float) $clean;
    }

    private function computeActifsFinanciersTotal(Client $client): ?float
    {
        $client->loadMissing(['actifsFinanciers', 'autresEpargnes', 'baeEpargne']);
        if ($client->actifsFinanciers->isEmpty()
            && $client->autresEpargnes->isEmpty()
            && empty($client->baeEpargne?->actifs_autres_details)
            && is_null($client->baeEpargne?->actifs_autres_total)
        ) {
            return null;
        }

        $total = 0.0;

        foreach ($client->actifsFinanciers as $actif) {
            if (!is_null($actif->valeur_actuelle)) {
                $total += (float) $actif->valeur_actuelle;
            }
        }

        $cryptoEntries = $this->getBaeEpargneOtherEntries($client, true);
        if (!empty($cryptoEntries)) {
            $cryptoSum = 0.0;
            foreach ($cryptoEntries as $entry) {
                if (!is_null($entry['valeur'] ?? null)) {
                    $cryptoSum += (float) $entry['valeur'];
                }
            }
            if ($cryptoSum > 0) {
                $total += $cryptoSum;
            } elseif (!is_null($client->baeEpargne?->actifs_autres_total)) {
                $total += (float) $client->baeEpargne->actifs_autres_total;
            }
        } else {
            $cryptoEpargnes = $this->filterCryptoEpargnes($client);
            foreach ($cryptoEpargnes as $crypto) {
                if (!is_null($crypto->valeur)) {
                    $total += (float) $crypto->valeur;
                }
            }
        }

        return $total > 0 ? $total : null;
    }

    private function computeBiensImmobiliersTotal(Client $client): ?float
    {
        $client->loadMissing('biensImmobiliers');
        if ($client->biensImmobiliers->isEmpty()) {
            return null;
        }

        $total = 0.0;
        foreach ($client->biensImmobiliers as $bien) {
            if (!is_null($bien->valeur_actuelle_estimee)) {
                $total += (float) $bien->valeur_actuelle_estimee;
                continue;
            }

            if (!is_null($bien->valeur_acquisition)) {
                $total += (float) $bien->valeur_acquisition;
            }
        }

        return $total > 0 ? $total : null;
    }

    /**
     * Formate une valeur selon son type
     */
    private function formatValue($value, string $columnName): string
    {
        // Si la valeur est null ou vide
        if ($value === null || $value === '') {
            return '';
        }

        // Certaines colonnes peuvent contenir des tableaux (JSON casté côté Eloquent)
        if (is_array($value)) {
            $parts = [];
            $iterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($value));
            foreach ($iterator as $item) {
                if ($item === null || $item === '') {
                    continue;
                }
                $parts[] = (string) $item;
            }

            return implode("\n", $parts);
        }

        // Formatage des dates
        if (in_array($columnName, self::DATE_COLUMNS)) {
            // Vérifier si la date contient des caractères invalides (XX, ??, etc.)
            if (is_string($value) && preg_match('/[Xx?]+/', $value)) {
                return $value; // Retourner la date telle quelle
            }
            try {
                return Carbon::parse($value)->format('d/m/Y');
            } catch (\Exception $e) {
                return (string) $value; // Retourner la valeur originale si parsing échoue
            }
        }

        // Formatage des montants
        if (in_array($columnName, self::CURRENCY_COLUMNS)) {
            if ($value == 0) {
                return '';
            }
            return number_format((float) $value, 2, ',', ' ') . ' €';
        }

        // Formatage des booléens
        if (in_array($columnName, self::BOOLEAN_COLUMNS)) {
            return $value ? 'Oui' : 'Non';
        }

        // Par défaut, retourner la valeur comme chaîne
        return (string) $value;
    }

    /**
     * Extrait toutes les variables d'un template Word
     */
    public function extractTemplateVariables(string $templatePath): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($templatePath) !== TRUE) {
            throw new \Exception("Cannot open template file: {$templatePath}");
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        // Extraire tout le texte
        preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $xml, $matches);
        $fullText = implode('', $matches[1]);
        $fullText = html_entity_decode($fullText, ENT_XML1);

        // Extraire les variables {{...}}
        preg_match_all('/\{\{([^}]+)\}\}/', $fullText, $varMatches);
        $variables = array_unique($varMatches[1]);
        $variables = array_map('trim', $variables);
        $variables = array_filter($variables, fn($v) => !empty($v));

        return array_values($variables);
    }

    /**
     * Retourne les statistiques de mapping
     */
    public function getMappingStats(Client $client, array $variables): array
    {
        $total = count($variables);
        $mapped = 0;
        $empty = 0;

        foreach ($variables as $variable) {
            $value = $this->resolveVariable($client, $variable);
            if ($value !== '') {
                $mapped++;
            } else {
                $empty++;
            }
        }

        return [
            'total' => $total,
            'mapped' => $mapped,
            'empty' => $empty,
            'coverage' => $total > 0 ? round(($mapped / $total) * 100, 2) : 0,
        ];
    }
}
