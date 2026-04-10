<?php

namespace App\Http\Controllers;

use App\Models\Production;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ProductionController extends Controller
{
    /** Champs Production acceptés lors du mapping import */
    private const MAPPABLE_FIELDS = [
        'nom_client', 'prenom_client', 'compagnie_libre', 'categorie', 'type_contrat',
        'annee', 'date_signature', 'date_effet', 'prime_ttc', 'prime_ht',
        'fond_euro', 'uc', 'taux_commission', 'commission_compagnie',
        'commission_mia', 'commission_recurrente', 'encours_commission',
        'date_commission', 'date_resiliation', 'date_reprise', 'statut', 'notes',
        'regul_transmise', 'regul_signee',
    ];

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function currentTeam()
    {
        return Auth::user()->currentTeam();
    }

    private function requireAdmin(): ?JsonResponse
    {
        $team = $this->currentTeam();
        if (! $team || ! Auth::user()->isTeamAdmin($team)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return null;
    }

    // ─── index ────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        if ($err = $this->requireAdmin()) {
            return $err;
        }

        $query = Production::with(['user:id,name,firstname', 'client:id,nom,prenom', 'assureur:id,nom'])
            ->orderByDesc('annee')
            ->orderByDesc('created_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        // Filtres communs
        if ($request->filled('annee')) {
            $query->where('annee', $request->integer('annee'));
        }
        if ($request->filled('categorie')) {
            $query->where('categorie', $request->string('categorie'));
        }
        if ($request->filled('statut')) {
            $query->where('statut', $request->string('statut'));
        }
        if ($request->filled('assureur_id')) {
            $query->where('assureur_id', $request->integer('assureur_id'));
        }
        if ($request->filled('search')) {
            $search = $request->string('search')->trim();
            $query->where(function ($q) use ($search) {
                $q->where('nom_client', 'like', "%{$search}%")
                    ->orWhere('prenom_client', 'like', "%{$search}%")
                    ->orWhereHas('client', fn ($c) => $c->where('nom', 'like', "%{$search}%")
                        ->orWhere('prenom', 'like', "%{$search}%"));
            });
        }

        $perPage = min($request->integer('per_page', 50), 200);
        $productions = $query->paginate($perPage);

        return response()->json($productions);
    }

    // ─── store ────────────────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        if ($err = $this->requireAdmin()) {
            return $err;
        }

        $validated = $request->validate($this->validationRules());

        $team = $this->currentTeam();

        if (empty($validated['user_id'])) {
            $validated['user_id'] = Auth::id();
        }

        $validated['team_id'] = $team->id;

        $production = Production::create($validated);
        $production->load(['user:id,name,firstname', 'client:id,nom,prenom', 'assureur:id,nom']);

        return response()->json(['data' => $production], 201);
    }

    // ─── update ───────────────────────────────────────────────────────────────

    public function update(Request $request, Production $production): JsonResponse
    {
        if ($err = $this->requireAdmin()) {
            return $err;
        }

        $validated = $request->validate($this->validationRules(partial: true));

        $production->update($validated);
        $production->load(['user:id,name,firstname', 'client:id,nom,prenom', 'assureur:id,nom']);

        return response()->json(['data' => $production]);
    }

    // ─── destroy ──────────────────────────────────────────────────────────────

    public function destroy(Production $production): JsonResponse
    {
        if ($err = $this->requireAdmin()) {
            return $err;
        }

        $production->delete();

        return response()->json(['message' => 'Deleted'], 200);
    }

    // ─── stats ────────────────────────────────────────────────────────────────

    public function stats(Request $request): JsonResponse
    {
        if ($err = $this->requireAdmin()) {
            return $err;
        }

        $query = Production::query();

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('annee')) {
            $query->where('annee', $request->integer('annee'));
        }

        $base = clone $query;

        $totals = $base->selectRaw('
            SUM(commission_mia) as total_commission_mia,
            SUM(commission_recurrente) as total_commission_recurrente,
            SUM(encours_commission) as total_encours,
            COUNT(*) as nb_contrats
        ')->first();

        $byAnnee = (clone $query)
            ->selectRaw('annee, SUM(commission_mia) as commission_mia, COUNT(*) as nb_contrats')
            ->whereNotNull('annee')
            ->groupBy('annee')
            ->orderByDesc('annee')
            ->get();

        $byCategorie = (clone $query)
            ->selectRaw('categorie, SUM(commission_mia) as commission_mia, COUNT(*) as nb_contrats')
            ->whereNotNull('categorie')
            ->groupBy('categorie')
            ->orderByDesc('commission_mia')
            ->get();

        $byMia = (clone $query)
            ->selectRaw('user_id,
                SUM(commission_mia) as commission_mia,
                SUM(commission_recurrente) as commission_recurrente,
                SUM(encours_commission) as encours_commission,
                COUNT(*) as nb_contrats')
            ->groupBy('user_id')
            ->orderByDesc('commission_mia')
            ->with('user:id,name,firstname')
            ->get()
            ->map(fn ($row) => [
                'user_id' => $row->user_id,
                'user_name' => $row->user
                    ? trim(($row->user->firstname ?? '').' '.$row->user->name)
                    : 'Inconnu',
                'commission_mia' => (float) ($row->commission_mia ?? 0),
                'commission_recurrente' => (float) ($row->commission_recurrente ?? 0),
                'encours_commission' => (float) ($row->encours_commission ?? 0),
                'nb_contrats' => (int) $row->nb_contrats,
            ]);

        // Breakdown par client — uniquement quand on filtre sur un MIA précis
        $byClient = [];
        if ($request->filled('user_id')) {
            $byClient = (clone $query)
                ->selectRaw('client_id, nom_client, prenom_client,
                    SUM(commission_mia) as commission_mia,
                    SUM(prime_ttc) as prime_ttc,
                    COUNT(*) as nb_contrats')
                ->groupBy('client_id', 'nom_client', 'prenom_client')
                ->orderByDesc('commission_mia')
                ->with('client:id,nom,prenom')
                ->get()
                ->map(fn ($row) => [
                    'client_id' => $row->client_id,
                    'client_name' => $row->client
                        ? trim(($row->client->prenom ?? '').' '.$row->client->nom)
                        : (trim(($row->prenom_client ?? '').' '.($row->nom_client ?? '')) ?: 'Sans nom'),
                    'is_crm_client' => $row->client_id !== null,
                    'commission_mia' => (float) ($row->commission_mia ?? 0),
                    'prime_ttc' => (float) ($row->prime_ttc ?? 0),
                    'nb_contrats' => (int) $row->nb_contrats,
                ]);
        }

        return response()->json([
            'total_commission_mia' => (float) ($totals->total_commission_mia ?? 0),
            'total_commission_recurrente' => (float) ($totals->total_commission_recurrente ?? 0),
            'total_encours' => (float) ($totals->total_encours ?? 0),
            'nb_contrats' => (int) ($totals->nb_contrats ?? 0),
            'by_annee' => $byAnnee,
            'by_categorie' => $byCategorie,
            'by_mia' => $byMia,
            'by_client' => $byClient,
        ]);
    }

    // ─── import preview ───────────────────────────────────────────────────────

    public function importPreview(Request $request): JsonResponse
    {
        if ($err = $this->requireAdmin()) {
            return $err;
        }

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:20480',
        ]);

        $file = $request->file('file');
        $token = Str::uuid()->toString();
        $path = $file->storeAs('productions_imports', $token.'.'.$file->getClientOriginalExtension(), 'local');

        $spreadsheet = IOFactory::load(Storage::disk('local')->path($path));

        $sheets = [];
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $sheets[] = $sheet->getTitle();
        }

        // Lire les colonnes de la 1ère feuille par défaut (ou celle demandée)
        $sheetName = $request->input('sheet', $sheets[0] ?? null);
        $activeSheet = $sheetName ? $spreadsheet->getSheetByName($sheetName) : $spreadsheet->getActiveSheet();

        if (! $activeSheet) {
            return response()->json(['message' => 'Feuille introuvable'], 422);
        }

        $highestRow = $activeSheet->getHighestRow();
        $highestCol = $activeSheet->getHighestColumn();

        // En-têtes (ligne 1)
        $headers = [];
        foreach ($activeSheet->getRowIterator(1, 1) as $row) {
            foreach ($row->getCellIterator('A', $highestCol) as $cell) {
                $val = trim((string) $cell->getValue());
                if ($val !== '') {
                    $headers[] = $val;
                }
            }
        }

        // 3 lignes d'aperçu
        $preview = [];
        $maxPreviewRow = min($highestRow, 4);
        foreach ($activeSheet->getRowIterator(2, $maxPreviewRow) as $row) {
            $rowData = [];
            $colIdx = 0;
            foreach ($row->getCellIterator('A', $highestCol) as $cell) {
                if ($colIdx < count($headers)) {
                    $rowData[] = $cell->getFormattedValue();
                }
                $colIdx++;
            }
            if (array_filter($rowData, fn ($v) => $v !== '' && $v !== null)) {
                $preview[] = $rowData;
            }
        }

        return response()->json([
            'file_token' => $token,
            'file_extension' => $file->getClientOriginalExtension(),
            'sheets' => $sheets,
            'columns' => $headers,
            'preview' => $preview,
        ]);
    }

    // ─── import execute ───────────────────────────────────────────────────────

    public function importExecute(Request $request): JsonResponse
    {
        if ($err = $this->requireAdmin()) {
            return $err;
        }

        $request->validate([
            'file_token' => 'required|string',
            'file_extension' => 'required|string|in:xlsx,xls',
            'sheet_mapping' => 'required|array',
            'sheet_mapping.*' => 'nullable|integer|exists:users,id',
            'column_mapping' => 'required|array',
        ]);

        $token = $request->input('file_token');
        $ext = $request->input('file_extension');
        $sheetMapping = $request->input('sheet_mapping');   // sheet_name → user_id
        $columnMapping = $request->input('column_mapping');  // excel_col → production_field

        $filePath = Storage::disk('local')->path('productions_imports/'.$token.'.'.$ext);

        if (! file_exists($filePath)) {
            return response()->json(['message' => 'Fichier introuvable ou expiré'], 422);
        }

        $team = $this->currentTeam();
        $spreadsheet = IOFactory::load($filePath);

        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($sheetMapping as $sheetName => $userId) {
            $sheet = $spreadsheet->getSheetByName($sheetName);
            if (! $sheet) {
                $errors[] = "Feuille « {$sheetName} » introuvable dans le fichier";

                continue;
            }

            $highestRow = $sheet->getHighestRow();
            $highestCol = $sheet->getHighestColumn();

            // Récupérer les en-têtes
            $headers = [];
            foreach ($sheet->getRowIterator(1, 1) as $row) {
                foreach ($row->getCellIterator('A', $highestCol) as $cell) {
                    $headers[] = trim((string) $cell->getValue());
                }
            }

            // Parcourir les lignes de données
            foreach ($sheet->getRowIterator(2, $highestRow) as $row) {
                $rowValues = [];
                $colIdx = 0;
                foreach ($row->getCellIterator('A', $highestCol) as $cell) {
                    if (isset($headers[$colIdx])) {
                        $rowValues[$headers[$colIdx]] = $cell->getFormattedValue();
                    }
                    $colIdx++;
                }

                // Vérifier ligne non vide
                if (! array_filter($rowValues, fn ($v) => $v !== '' && $v !== null)) {
                    $skipped++;

                    continue;
                }

                // Mapper les colonnes vers les champs Production
                $productionData = [
                    'team_id' => $team->id,
                    'user_id' => $userId ?? Auth::id(),
                ];

                foreach ($columnMapping as $excelCol => $field) {
                    if (! in_array($field, self::MAPPABLE_FIELDS)) {
                        continue;
                    }
                    $rawValue = $rowValues[$excelCol] ?? null;
                    if ($rawValue === '' || $rawValue === null) {
                        continue;
                    }

                    $productionData[$field] = $this->castImportedValue($field, $rawValue, $errors, $imported + $skipped + 2);
                }

                try {
                    Production::create($productionData);
                    $imported++;
                } catch (\Throwable $e) {
                    $errors[] = 'Ligne '.($imported + $skipped + 2).' : '.$e->getMessage();
                    $skipped++;
                }
            }
        }

        // Nettoyer le fichier temporaire
        @unlink($filePath);

        return response()->json([
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);
    }

    // ─── Helpers privés ──────────────────────────────────────────────────────

    private function castImportedValue(string $field, mixed $value, array &$errors, int $lineNum): mixed
    {
        $dateFields = ['date_signature', 'date_effet', 'date_commission', 'date_resiliation', 'date_reprise'];
        $decimalFields = ['prime_ttc', 'prime_ht', 'fond_euro', 'uc', 'taux_commission',
            'commission_compagnie', 'commission_mia', 'commission_recurrente', 'encours_commission'];
        $boolFields = ['regul_transmise', 'regul_signee'];

        if (in_array($field, $decimalFields)) {
            // Nettoyer les espaces et virgules françaises
            $clean = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], (string) $value);
            $clean = preg_replace('/[^\d.\-]/', '', $clean);

            return is_numeric($clean) ? (float) $clean : null;
        }

        if ($field === 'annee') {
            $year = (int) $value;

            return ($year >= 1900 && $year <= 2100) ? $year : null;
        }

        if (in_array($field, $dateFields)) {
            // Gérer les timestamps Excel numériques
            if (is_numeric($value)) {
                try {
                    $date = ExcelDate::excelToDateTimeObject((float) $value);

                    return $date->format('Y-m-d');
                } catch (\Throwable) {
                    return null;
                }
            }
            // Essayer de parser comme date
            try {
                return Carbon::parse($value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        if (in_array($field, $boolFields)) {
            $lower = strtolower(trim((string) $value));

            return in_array($lower, ['1', 'oui', 'yes', 'true', 'x']);
        }

        if ($field === 'statut') {
            $map = ['active' => 'active', 'actif' => 'active', 'résilié' => 'resilie', 'resilie' => 'resilie',
                'résilie' => 'resilie', 'attente' => 'attente', 'frigo' => 'frigo'];

            return $map[strtolower(trim((string) $value))] ?? 'active';
        }

        return (string) $value;
    }

    private function validationRules(bool $partial = false): array
    {
        $sometimes = $partial ? 'sometimes|' : '';

        return [
            'user_id' => $sometimes.'nullable|integer|exists:users,id',
            'client_id' => $sometimes.'nullable|integer|exists:clients,id',
            'nom_client' => $sometimes.'nullable|string|max:255',
            'prenom_client' => $sometimes.'nullable|string|max:255',
            'assureur_id' => $sometimes.'nullable|integer|exists:assureurs,id',
            'compagnie_libre' => $sometimes.'nullable|string|max:255',
            'categorie' => $sometimes.'nullable|string|max:255',
            'type_contrat' => $sometimes.'nullable|string|max:255',
            'annee' => $sometimes.'nullable|integer|min:1900|max:2100',
            'date_signature' => $sometimes.'nullable|date',
            'date_effet' => $sometimes.'nullable|date',
            'prime_ttc' => $sometimes.'nullable|numeric',
            'prime_ht' => $sometimes.'nullable|numeric',
            'fond_euro' => $sometimes.'nullable|numeric',
            'uc' => $sometimes.'nullable|numeric',
            'taux_commission' => $sometimes.'nullable|numeric|min:0|max:1',
            'commission_compagnie' => $sometimes.'nullable|numeric',
            'commission_mia' => $sometimes.'nullable|numeric',
            'commission_recurrente' => $sometimes.'nullable|numeric',
            'encours_commission' => $sometimes.'nullable|numeric',
            'date_commission' => $sometimes.'nullable|date',
            'regul_transmise' => $sometimes.'nullable|boolean',
            'regul_signee' => $sometimes.'nullable|boolean',
            'date_resiliation' => $sometimes.'nullable|date',
            'date_reprise' => $sometimes.'nullable|date',
            'statut' => $sometimes.'nullable|in:active,resilie,attente,frigo',
            'notes' => $sometimes.'nullable|string',
        ];
    }
}
