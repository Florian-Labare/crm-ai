<?php

namespace App\Services\Bordereau;

use App\Models\Assureur;
use App\Models\Client;
use App\Models\ClientContrat;
use App\Models\Production;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Parseur de bordereaux de commissions assureurs.
 *
 * Formats supportés :
 *   - alptis_cot : Alptis/Generali bordereaux COT CSV (ISO-8859-1, séparateur ;)
 *   - selencia    : SELENCIA Patrimoine (ISO-8859-1, séparateur ;)
 *
 * Résultat d'import :
 *   - Productions créées (commissions du bordereau)
 *   - Clients créés si absents
 *   - ClientContrats.mensualite mis à jour
 *   - Résiliations détectées (présents mois N-1, absents mois N)
 */
class BordereauParserService
{
    private const FORMATS = [
        'alptis_cot' => [
            'col_nom' => 'Nom de l assuré',
            'col_prenom' => 'Prénom de l assuré',
            'col_numero' => 'Numéro assuré',
            'col_produit' => 'Libellé du produit',
            'col_famille' => 'Libellé famille commerciale',
            'col_prime_ttc' => 'Montant de la cotisation TTC',
            'col_prime_ht' => 'Montant cotisation HT ou net investi en épargne',
            'col_assiette' => 'Assiette du cas de commission',
            'col_taux' => 'Taux de commission',
            'col_commission' => 'Montant de commission',
            'col_date_op' => 'Date de l opération',
            'col_date_debut' => 'Date de début de période',
            'col_compagnie' => 'Compagnie juridique',
            'col_nature_op' => 'Nature de l opération',
            'col_nature_com' => 'Nature de commission',
        ],
        'selencia' => [
            'col_nom_prenom' => 'Nom Prénom du client',
            'col_numero' => 'N° de contrat',
            'col_produit' => 'Produit',
            'col_commission' => 'Montant de commissions',
            'col_taux' => 'Votre taux de commissionnement global',
            'col_pm' => 'PM fin de période',
            'col_type_com' => 'Type de commissions',
            'col_assureur_src' => 'Société',
        ],
    ];

    /**
     * Détecte le format du bordereau depuis les en-têtes du CSV.
     */
    public function detectFormat(string $filePath): ?string
    {
        $handle = fopen($filePath, 'r');
        if (! $handle) {
            return null;
        }

        // Lire les premières lignes pour trouver la ligne d'en-tête
        $headerLine = null;
        for ($i = 0; $i < 5; $i++) {
            $line = fgets($handle);
            if ($line === false) {
                break;
            }
            $line = mb_convert_encoding($line, 'UTF-8', 'ISO-8859-1');
            if (str_contains($line, 'Numéro assuré') || str_contains($line, 'Nom de l assuré')) {
                $headerLine = $line;
                break;
            }
            if (str_contains($line, 'Nom Prénom du client') || str_contains($line, 'N° de contrat')) {
                $headerLine = $line;
                break;
            }
        }
        fclose($handle);

        if (! $headerLine) {
            return null;
        }

        if (str_contains($headerLine, 'Numéro assuré')) {
            return 'alptis_cot';
        }

        if (str_contains($headerLine, 'Nom Prénom du client')) {
            return 'selencia';
        }

        return null;
    }

    /**
     * Importe un bordereau CSV et retourne un résumé de l'opération.
     *
     * @return array{
     *   format: string,
     *   created_clients: int,
     *   updated_contrats: int,
     *   created_productions: int,
     *   resiliations: array<array{nom: string, prenom: string, numero_adherent: string}>,
     *   errors: string[]
     * }
     */
    public function import(string $filePath, Team $team, User $miaUser, string $bordereauMois): array
    {
        $format = $this->detectFormat($filePath);

        if (! $format) {
            return [
                'format' => 'unknown',
                'created_clients' => 0,
                'updated_contrats' => 0,
                'created_productions' => 0,
                'resiliations' => [],
                'errors' => ['Format de bordereau non reconnu. Formats supportés : Alptis COT, SELENCIA.'],
            ];
        }

        return match ($format) {
            'alptis_cot' => $this->importAlptisCot($filePath, $team, $miaUser, $bordereauMois),
            'selencia' => $this->importSelencia($filePath, $team, $miaUser, $bordereauMois),
            default => ['format' => $format, 'created_clients' => 0, 'updated_contrats' => 0, 'created_productions' => 0, 'resiliations' => [], 'errors' => ['Format non implémenté.']],
        };
    }

    // ─── Format Alptis COT ────────────────────────────────────────────────────

    private function importAlptisCot(string $filePath, Team $team, User $miaUser, string $bordereauMois): array
    {
        $cols = self::FORMATS['alptis_cot'];
        $result = ['format' => 'alptis_cot', 'created_clients' => 0, 'updated_contrats' => 0, 'created_productions' => 0, 'resiliations' => [], 'errors' => []];

        // Lire le fichier en ISO-8859-1
        $rows = $this->readCsv($filePath, ';');
        if (empty($rows)) {
            $result['errors'][] = 'Fichier vide ou illisible.';

            return $result;
        }

        $headers = $rows[0];
        $colIdx = $this->mapColumns($headers);

        // Collecter les numéros adhérent présents dans ce bordereau
        $adherentsPresents = [];

        DB::transaction(function () use ($rows, $colIdx, $cols, $team, $miaUser, $bordereauMois, &$result, &$adherentsPresents) {
            foreach (array_slice($rows, 1) as $row) {
                try {
                    $this->processAlptisRow($row, $colIdx, $cols, $team, $miaUser, $bordereauMois, $result, $adherentsPresents);
                } catch (\Throwable $e) {
                    $result['errors'][] = 'Ligne ignorée : '.$e->getMessage();
                    Log::warning('[BordereauParser] Alptis row error', ['error' => $e->getMessage()]);
                }
            }
        });

        // Détecter les résiliations : adhérents présents mois N-1, absents mois N
        $result['resiliations'] = $this->detectResiliations($team, $bordereauMois, $adherentsPresents, 'alptis_cot');

        return $result;
    }

    private function processAlptisRow(array $row, array $colIdx, array $cols, Team $team, User $miaUser, string $bordereauMois, array &$result, array &$adherentsPresents): void
    {
        $nom = $this->clean($row[$colIdx[$cols['col_nom']]] ?? '');
        $prenom = $this->clean($row[$colIdx[$cols['col_prenom']]] ?? '');
        $numeroAdherent = $this->clean($row[$colIdx[$cols['col_numero']]] ?? '');
        $produit = $this->clean($row[$colIdx[$cols['col_produit']]] ?? '');
        $famille = $this->clean($row[$colIdx[$cols['col_famille']]] ?? '');
        $primeTtc = $this->parseDecimal($row[$colIdx[$cols['col_prime_ttc']]] ?? '');
        $primeHt = $this->parseDecimal($row[$colIdx[$cols['col_prime_ht']]] ?? '');
        $assiette = $this->parseDecimal($row[$colIdx[$cols['col_assiette']]] ?? '');
        $taux = $this->parseDecimal($row[$colIdx[$cols['col_taux']]] ?? '');
        $commission = $this->parseDecimal($row[$colIdx[$cols['col_commission']]] ?? '');
        $dateOp = $this->parseDate($row[$colIdx[$cols['col_date_op']]] ?? '');
        $compagnie = $this->clean($row[$colIdx[$cols['col_compagnie']]] ?? '');
        $natureOp = $this->clean($row[$colIdx[$cols['col_nature_op']]] ?? '');
        $natureCom = $this->clean($row[$colIdx[$cols['col_nature_com']]] ?? '');

        if (empty($nom) || empty($numeroAdherent)) {
            return;
        }

        // Tracker les adhérents présents dans ce bordereau
        $adherentsPresents[$numeroAdherent] = true;

        // Trouver ou créer l'assureur
        $assureur = $this->findOrCreateAssureur($compagnie ?: 'Alptis Assurances');

        // Trouver ou créer le client
        $client = $this->findOrCreateClient($nom, $prenom, $team, $miaUser, $result);

        // Déterminer le type de contrat
        $typeContrat = $this->detectTypeContrat($famille, $produit);

        // Créer/MAJ le contrat client
        $this->syncClientContrat($client, $assureur, $typeContrat, $produit, $numeroAdherent, $primeTtc, $result);

        // Créer la ligne de production (commission)
        $moisDate = Carbon::createFromFormat('Y-m-d', $bordereauMois.'-01');
        Production::create([
            'team_id' => $team->id,
            'user_id' => $miaUser->id,
            'client_id' => $client->id,
            'nom_client' => $nom,
            'prenom_client' => $prenom,
            'assureur_id' => $assureur->id,
            'categorie' => $famille ?: $typeContrat,
            'type_contrat' => $typeContrat,
            'annee' => $moisDate->year,
            'date_commission' => $dateOp,
            'bordereau_mois' => $moisDate->format('Y-m-d'),
            'bordereau_format' => 'alptis_cot',
            'numero_adherent' => $numeroAdherent,
            'prime_ttc' => $primeTtc,
            'prime_ht' => $primeHt,
            'taux_commission' => $taux,
            'commission_mia' => $natureCom === 'Crédit' ? $commission : -$commission,
            'statut' => 'active',
        ]);
        $result['created_productions']++;
    }

    // ─── Format SELENCIA ──────────────────────────────────────────────────────

    private function importSelencia(string $filePath, Team $team, User $miaUser, string $bordereauMois): array
    {
        $cols = self::FORMATS['selencia'];
        $result = ['format' => 'selencia', 'created_clients' => 0, 'updated_contrats' => 0, 'created_productions' => 0, 'resiliations' => [], 'errors' => []];

        $rows = $this->readCsv($filePath, ';');
        if (empty($rows)) {
            $result['errors'][] = 'Fichier vide ou illisible.';

            return $result;
        }

        $headers = $rows[0];
        $colIdx = $this->mapColumns($headers);
        $adherentsPresents = [];

        DB::transaction(function () use ($rows, $colIdx, $cols, $team, $miaUser, $bordereauMois, &$result, &$adherentsPresents) {
            foreach (array_slice($rows, 1) as $row) {
                try {
                    $this->processSelenciaRow($row, $colIdx, $cols, $team, $miaUser, $bordereauMois, $result, $adherentsPresents);
                } catch (\Throwable $e) {
                    $result['errors'][] = 'Ligne ignorée : '.$e->getMessage();
                    Log::warning('[BordereauParser] Selencia row error', ['error' => $e->getMessage()]);
                }
            }
        });

        $result['resiliations'] = $this->detectResiliations($team, $bordereauMois, $adherentsPresents, 'selencia');

        return $result;
    }

    private function processSelenciaRow(array $row, array $colIdx, array $cols, Team $team, User $miaUser, string $bordereauMois, array &$result, array &$adherentsPresents): void
    {
        $nomPrenom = $this->clean($row[$colIdx[$cols['col_nom_prenom']]] ?? '');
        $numeroContrat = (string) ($row[$colIdx[$cols['col_numero']]] ?? '');
        $produit = $this->clean($row[$colIdx[$cols['col_produit']]] ?? '');
        $commission = $this->parseDecimal($row[$colIdx[$cols['col_commission']]] ?? '');
        $tauxColName = $cols['col_taux'] ?? null;
        $taux = ($tauxColName && isset($colIdx[$tauxColName])) ? $this->parseDecimal($row[$colIdx[$tauxColName]] ?? '') : null;
        $pmColName = $cols['col_pm'] ?? null;
        $pm = ($pmColName && isset($colIdx[$pmColName])) ? $this->parseDecimal($row[$colIdx[$pmColName]] ?? '') : null;
        $typeCom = $this->clean($row[$colIdx[$cols['col_type_com']]] ?? '');
        $assureurSrc = $this->clean($row[$colIdx[$cols['col_assureur_src']]] ?? 'SELENCIA Patrimoine');

        if (empty($nomPrenom) || empty($numeroContrat)) {
            return;
        }

        $adherentsPresents[$numeroContrat] = true;

        // Séparer nom prénom (format "NOM Prénom")
        $parts = explode(' ', $nomPrenom, 2);
        $nom = strtoupper($parts[0]);
        $prenom = isset($parts[1]) ? ucwords(strtolower($parts[1])) : '';

        $assureur = $this->findOrCreateAssureur($assureurSrc);
        $client = $this->findOrCreateClient($nom, $prenom, $team, $miaUser, $result);

        $typeContrat = 'assurance_vie'; // SELENCIA = patrimoine / assurance vie

        $this->syncClientContrat($client, $assureur, $typeContrat, $produit, (string) $numeroContrat, null, $result, $pm);

        $moisDate = Carbon::createFromFormat('Y-m-d', $bordereauMois.'-01');
        Production::create([
            'team_id' => $team->id,
            'user_id' => $miaUser->id,
            'client_id' => $client->id,
            'nom_client' => $nom,
            'prenom_client' => $prenom,
            'assureur_id' => $assureur->id,
            'categorie' => $typeCom,
            'type_contrat' => $typeContrat,
            'annee' => $moisDate->year,
            'bordereau_mois' => $moisDate->format('Y-m-d'),
            'bordereau_format' => 'selencia',
            'numero_adherent' => (string) $numeroContrat,
            'encours_commission' => $pm,
            'taux_commission' => $taux,
            'commission_mia' => $commission,
            'statut' => 'active',
        ]);
        $result['created_productions']++;
    }

    // ─── Helpers partagés ─────────────────────────────────────────────────────

    private function findOrCreateAssureur(string $nom): Assureur
    {
        return Assureur::firstOrCreate(['nom' => $nom]);
    }

    private function findOrCreateClient(string $nom, string $prenom, Team $team, User $miaUser, array &$result): Client
    {
        $existing = Client::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('nom', $nom)
            ->where('prenom', $prenom)
            ->first();

        if ($existing) {
            return $existing;
        }

        $result['created_clients']++;

        return Client::create([
            'team_id' => $team->id,
            'user_id' => $miaUser->id,
            'nom' => $nom,
            'prenom' => $prenom,
            'is_client' => true,
            'is_archived' => false,
        ]);
    }

    private const CONTRAT_TYPE_TO_BESOIN = [
        'sante' => 'sante',
        'prevoyance' => 'prevoyance',
        'per' => 'retraite',
        'assurance_vie' => 'epargne',
        'vie_entiere' => 'epargne',
        'emprunteur' => 'emprunteur',
    ];

    private function syncClientContrat(Client $client, Assureur $assureur, string $type, string $produit, string $numeroAdherent, ?float $primeTtc, array &$result, ?float $enCours = null): void
    {
        $existing = ClientContrat::where('client_id', $client->id)
            ->where('numero_contrat', $numeroAdherent)
            ->first();

        if ($existing) {
            $updates = [];
            if ($primeTtc !== null) {
                $updates['mensualite'] = $primeTtc;
            }
            if ($enCours !== null) {
                $updates['en_cours'] = $enCours;
            }
            if (! empty($produit) && empty($existing->produit)) {
                $updates['produit'] = $produit;
            }
            if (! empty($updates)) {
                $existing->update($updates);
                $result['updated_contrats']++;
            }
        } else {
            ClientContrat::create([
                'client_id' => $client->id,
                'type' => $type,
                'assureur_id' => $assureur->id,
                'numero_contrat' => $numeroAdherent,
                'produit' => $produit,
                'mensualite' => $primeTtc,
                'en_cours' => $enCours,
            ]);
            $result['updated_contrats']++;
        }

        $this->addBesoinToClient($client, $type);
    }

    private function addBesoinToClient(Client $client, string $typeContrat): void
    {
        $besoin = self::CONTRAT_TYPE_TO_BESOIN[$typeContrat] ?? null;
        if (! $besoin) {
            return;
        }

        $current = is_array($client->besoins) ? $client->besoins : [];
        if (! in_array($besoin, $current, true)) {
            $current[] = $besoin;
            $client->update(['besoins' => $current]);
        }
    }

    /**
     * Détecte les adhérents présents le mois précédent mais absents ce mois-ci.
     * Un absent = résiliation potentielle (à confirmer par le MIA).
     */
    private function detectResiliations(Team $team, string $bordereauMois, array $adherentsPresents, string $format): array
    {
        $moisPrecedent = Carbon::createFromFormat('Y-m-d', $bordereauMois.'-01')
            ->subMonth()
            ->format('Y-m-01');

        $productionsPrecedentes = Production::withoutGlobalScopes()
            ->where('team_id', $team->id)
            ->where('bordereau_mois', $moisPrecedent)
            ->where('bordereau_format', $format)
            ->whereNotNull('numero_adherent')
            ->where('statut', '!=', 'resilie')
            ->select('numero_adherent', 'nom_client', 'prenom_client')
            ->distinct()
            ->get();

        $resiliations = [];
        foreach ($productionsPrecedentes as $prod) {
            if (! isset($adherentsPresents[$prod->numero_adherent])) {
                $resiliations[] = [
                    'nom' => $prod->nom_client,
                    'prenom' => $prod->prenom_client,
                    'numero_adherent' => $prod->numero_adherent,
                ];
            }
        }

        return $resiliations;
    }

    private function detectTypeContrat(string $famille, string $produit): string
    {
        $lower = strtolower($famille.' '.$produit);
        if (str_contains($lower, 'prévoyance') || str_contains($lower, 'prevoyance')) {
            return 'prevoyance';
        }
        if (str_contains($lower, 'santé') || str_contains($lower, 'sante') || str_contains($lower, 'sani')) {
            return 'sante';
        }
        if (str_contains($lower, 'retraite') || str_contains($lower, 'per ') || str_contains($lower, 'pareo')) {
            return 'per';
        }
        if (str_contains($lower, 'vie') || str_contains($lower, 'épargne') || str_contains($lower, 'epargne')) {
            return 'assurance_vie';
        }

        return 'prevoyance'; // défaut pour Alptis
    }

    private function readCsv(string $filePath, string $delimiter = ';'): array
    {
        $rows = [];
        $handle = fopen($filePath, 'r');
        if (! $handle) {
            return $rows;
        }

        while (($line = fgets($handle)) !== false) {
            $line = mb_convert_encoding($line, 'UTF-8', 'ISO-8859-1');
            $line = rtrim($line, "\r\n");

            // Ignorer les lignes vides et les lignes de référence (="..")
            if (empty(trim($line))) {
                continue;
            }

            $columns = str_getcsv($line, $delimiter, '"');
            // Nettoyer les valeurs avec format Excel =".."
            $columns = array_map(function ($v) {
                $v = trim($v ?? '');
                if (preg_match('/^="(.*)"$/', $v, $m)) {
                    return trim($m[1]);
                }

                return $v;
            }, $columns);

            $rows[] = $columns;
        }
        fclose($handle);

        return $rows;
    }

    /**
     * Construit un index nom_colonne => index_numérique à partir de la ligne d'en-tête.
     */
    private function mapColumns(array $headers): array
    {
        $idx = [];
        foreach ($headers as $i => $h) {
            $idx[trim($h)] = $i;
        }

        return $idx;
    }

    private function clean(string $value): string
    {
        return trim($value);
    }

    private function parseDecimal(string $value): ?float
    {
        $value = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], trim($value));
        $value = preg_replace('/[^\d.\-]/', '', $value);

        return is_numeric($value) ? (float) $value : null;
    }

    private function parseDate(string $value): ?string
    {
        $value = trim($value);
        if (empty($value)) {
            return null;
        }

        try {
            // Format Alptis : DD.MM.YYYY
            if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $value)) {
                return Carbon::createFromFormat('d.m.Y', $value)->format('Y-m-d');
            }

            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
