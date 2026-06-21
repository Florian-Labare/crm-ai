<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $user = auth()->user();
        $team = $user->currentTeam();
        $teamId = $team?->id;

        $isAdmin = $team && $user->isTeamAdmin($team);

        $base = Client::query();
        if (! $isAdmin && ! $user->isSuperAdmin()) {
            $base->where('user_id', $user->id);
        }

        // ──────────────────────────────────────────────
        // PIPELINE
        // ──────────────────────────────────────────────
        $prospects = (clone $base)->where('is_client', false)->where('is_archived', false)->count();
        $clients = (clone $base)->where('is_client', true)->where('is_archived', false)->count();
        $total = $prospects + $clients;
        $tauxConversion = $total > 0 ? round($clients / $total * 100, 1) : 0;

        $nouveauxMois = (clone $base)
            ->where('is_archived', false)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $archivesMois = (clone $base)
            ->where('is_archived', true)
            ->whereMonth('updated_at', now()->month)
            ->whereYear('updated_at', now()->year)
            ->count();

        // ──────────────────────────────────────────────
        // IDs actifs (réutilisés partout)
        // ──────────────────────────────────────────────
        $actifIds = (clone $base)->where('is_archived', false)->pluck('id')->toArray();
        $allIds = (clone $base)->pluck('id')->toArray();

        // ──────────────────────────────────────────────
        // OPPORTUNITÉS
        // ──────────────────────────────────────────────
        $avecSante = DB::table('client_contrats')->whereIn('client_id', $actifIds)->where('type', 'sante')->distinct('client_id')->count('client_id');
        $avecPrevoyance = DB::table('client_contrats')->whereIn('client_id', $actifIds)->where('type', 'prevoyance')->distinct('client_id')->count('client_id');
        $avecRetraite = DB::table('client_contrats')->whereIn('client_id', $actifIds)->where('type', 'per')->distinct('client_id')->count('client_id');
        $avecEpargne = DB::table('client_contrats')->whereIn('client_id', $actifIds)->whereIn('type', ['assurance_vie', 'vie_entiere'])->distinct('client_id')->count('client_id');

        // ──────────────────────────────────────────────
        // PORTEFEUILLE — contrats & en-cours
        // ──────────────────────────────────────────────
        $nbContrats = DB::table('client_contrats')->whereIn('client_id', $actifIds)->count();
        $tauxEquipement = $total > 0 ? round($nbContrats / $total, 2) : 0;
        $enCoursTotal = (float) DB::table('client_contrats')->whereIn('client_id', $actifIds)->sum('en_cours');
        $mensualitesTotal = (float) DB::table('client_contrats')->whereIn('client_id', $actifIds)->sum('mensualite');

        // Contrats par type
        $typeLabels = [
            'sante' => 'Santé',
            'prevoyance' => 'Prévoyance',
            'per' => 'PER',
            'assurance_vie' => 'Ass. Vie',
            'emprunteur' => 'Emprunteur',
            'vie_entiere' => 'Vie Entière',
        ];
        $contratsParType = DB::table('client_contrats')
            ->whereIn('client_id', $actifIds)
            ->select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->get()
            ->map(fn ($r) => ['type' => $r->type, 'label' => $typeLabels[$r->type] ?? $r->type, 'count' => (int) $r->count])
            ->values()->toArray();

        // Top 5 assureurs par nb contrats
        $parAssureur = DB::table('client_contrats')
            ->join('assureurs', 'client_contrats.assureur_id', '=', 'assureurs.id')
            ->whereIn('client_contrats.client_id', $actifIds)
            ->select('assureurs.nom as label', DB::raw('count(*) as count'))
            ->groupBy('assureurs.id', 'assureurs.nom')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(fn ($r) => ['label' => $r->label, 'count' => (int) $r->count])
            ->values()->toArray();

        // Contrats "signés" = lettre de mission uploadée
        $lettreMissionTypes = ['lettre_mission_sante', 'lettre_mission_prevoyance', 'lettre_mission_retraite', 'lettre_mission_epargne', 'lettre_mission_emprunteur'];
        $nbContratsSignes = DB::table('client_compliance_documents')
            ->whereIn('client_id', $actifIds)
            ->whereIn('document_type', $lettreMissionTypes)
            ->distinct('client_id', 'document_type')
            ->count();

        // ──────────────────────────────────────────────
        // CONFORMITÉ
        // ──────────────────────────────────────────────
        $clientsAvecValide = DB::table('client_compliance_documents')
            ->whereIn('client_id', $actifIds)
            ->where('status', 'validated')
            ->distinct('client_id')
            ->count('client_id');

        // Clients avec docs mais aucun validé
        $clientsAvecValidePure = DB::table('client_compliance_documents')
            ->whereIn('client_id', $actifIds)
            ->where('status', 'validated')
            ->pluck('client_id')
            ->unique()
            ->toArray();

        $clientsAvecDocSeulement = DB::table('client_compliance_documents')
            ->whereIn('client_id', $actifIds)
            ->whereNotIn('client_id', $clientsAvecValidePure)
            ->distinct('client_id')
            ->count('client_id');

        $clientsIncomplets = max(0, count($actifIds) - $clientsAvecValide - $clientsAvecDocSeulement);

        $docsExpires = DB::table('client_compliance_documents')
            ->whereIn('client_id', $actifIds)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->where('status', 'validated')
            ->count();

        $docsExpirantBientot = DB::table('client_compliance_documents')
            ->whereIn('client_id', $actifIds)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays(30)])
            ->where('status', 'validated')
            ->count();

        $docsPending = DB::table('client_compliance_documents')
            ->whereIn('client_id', $actifIds)
            ->where('status', 'pending')
            ->count();

        // ──────────────────────────────────────────────
        // ACTIVITÉ IA
        // ──────────────────────────────────────────────
        $audioBase = DB::table('audio_records');
        if (! $isAdmin && ! $user->isSuperAdmin()) {
            $audioBase->where('user_id', $user->id);
        } elseif ($teamId) {
            $audioBase->where('team_id', $teamId);
        }

        $enregistrementsMois = (clone $audioBase)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $totalAudio = (clone $audioBase)->count();
        $diarisationSucces = (clone $audioBase)->where('diarization_success', true)->count();
        $tauxDiarisation = $totalAudio > 0 ? round($diarisationSucces / $totalAudio * 100) : 0;

        // Clients enrichis via IA ce mois (audio_record lié à un client, traité ce mois)
        $clientsEnrichisIA = (clone $audioBase)
            ->whereNotNull('client_id')
            ->where('status', 'done')
            ->whereMonth('processed_at', now()->month)
            ->whereYear('processed_at', now()->year)
            ->distinct('client_id')
            ->count('client_id');

        // Audio par mois sur 6 mois
        $frMonths = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
        $audio6Mois = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $cnt = (clone $audioBase)
                ->whereMonth('created_at', $date->month)
                ->whereYear('created_at', $date->year)
                ->count();
            $audio6Mois[] = ['mois' => $frMonths[$date->month - 1], 'count' => $cnt];
        }

        // ──────────────────────────────────────────────
        // COMMISSIONS (depuis les bordereaux)
        // ──────────────────────────────────────────────
        $prodBase = DB::table('productions')->where('team_id', $teamId);
        if (! $isAdmin && ! $user->isSuperAdmin()) {
            $prodBase->where('user_id', $user->id);
        }

        $commissionTotals = (clone $prodBase)->selectRaw('
            sum(commission_mia) as total_commission_mia,
            sum(encours_commission) as encours_portefeuille,
            count(*) as nb_lignes,
            count(distinct client_id) as nb_clients_bordereaux
        ')->first();

        $typeLabelsComm = [
            'sante' => 'Santé',
            'prevoyance' => 'Prévoyance',
            'per' => 'PER',
            'assurance_vie' => 'Ass. Vie',
            'emprunteur' => 'Emprunteur',
            'vie_entiere' => 'Vie Entière',
        ];
        $commParType = (clone $prodBase)
            ->whereNotNull('type_contrat')
            ->selectRaw('type_contrat, sum(commission_mia) as commission, count(*) as nb_lignes')
            ->groupBy('type_contrat')
            ->orderByDesc('commission')
            ->get()
            ->map(fn ($r) => [
                'type' => $r->type_contrat,
                'label' => $typeLabelsComm[$r->type_contrat] ?? $r->type_contrat,
                'commission' => (float) $r->commission,
                'nb_lignes' => (int) $r->nb_lignes,
            ])
            ->values()->toArray();

        // ──────────────────────────────────────────────
        // TENDANCES
        // ──────────────────────────────────────────────
        $nouveaux6Mois = [];
        $contrats6Mois = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $nouveaux6Mois[] = [
                'mois' => $frMonths[$date->month - 1],
                'count' => (clone $base)->where('is_archived', false)->whereMonth('created_at', $date->month)->whereYear('created_at', $date->year)->count(),
            ];
            $contrats6Mois[] = [
                'mois' => $frMonths[$date->month - 1],
                'count' => DB::table('client_compliance_documents')->whereIn('client_id', $actifIds)->whereIn('document_type', $lettreMissionTypes)->whereMonth('created_at', $date->month)->whereYear('created_at', $date->year)->count(),
            ];
        }

        // ──────────────────────────────────────────────
        // RÉPARTITION BESOINS
        // ──────────────────────────────────────────────
        $clientsAvecBesoins = (clone $base)->where('is_archived', false)->whereNotNull('besoins')->where('besoins', '!=', '[]')->get(['besoins']);
        $besoinsCounts = [];
        foreach ($clientsAvecBesoins as $c) {
            $besoins = is_array($c->besoins) ? $c->besoins : json_decode($c->besoins, true) ?? [];
            foreach ($besoins as $b) {
                $label = $this->normalizeBesoin($b);
                $besoinsCounts[$label] = ($besoinsCounts[$label] ?? 0) + 1;
            }
        }
        arsort($besoinsCounts);

        // ──────────────────────────────────────────────
        // ÉQUIPE (admin seulement)
        // ──────────────────────────────────────────────
        $equipe = [];
        if (($isAdmin || $user->isSuperAdmin()) && $teamId) {
            $membres = DB::table('team_user')
                ->join('users', 'team_user.user_id', '=', 'users.id')
                ->where('team_user.team_id', $teamId)
                ->select('users.id', 'users.name', 'users.firstname', 'team_user.role')
                ->get();

            foreach ($membres as $m) {
                $nbC = DB::table('clients')->where('user_id', $m->id)->where('team_id', $teamId)->where('is_archived', false)->count();
                $nbCl = DB::table('clients')->where('user_id', $m->id)->where('team_id', $teamId)->where('is_client', true)->where('is_archived', false)->count();
                $nbAu = DB::table('audio_records')->where('user_id', $m->id)->where('team_id', $teamId)->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count();

                $memClientIds = DB::table('clients')->where('user_id', $m->id)->where('team_id', $teamId)->where('is_archived', false)->pluck('id')->toArray();
                $docsVal = count($memClientIds) > 0
                    ? DB::table('client_compliance_documents')->whereIn('client_id', $memClientIds)->where('status', 'validated')->distinct('client_id')->count('client_id')
                    : 0;
                $tauxConf = $nbC > 0 ? round($docsVal / $nbC * 100) : 0;

                $equipe[] = [
                    'id' => $m->id,
                    'nom' => trim(($m->firstname ?? '').' '.$m->name),
                    'role' => $m->role,
                    'nb_contacts' => $nbC,
                    'nb_clients' => $nbCl,
                    'audio_mois' => $nbAu,
                    'taux_conformite' => $tauxConf,
                ];
            }
        }

        return response()->json([
            'pipeline' => [
                'prospects' => $prospects,
                'clients' => $clients,
                'taux_conversion' => $tauxConversion,
                'nouveaux_mois' => $nouveauxMois,
                'archives_mois' => $archivesMois,
            ],
            'portefeuille' => [
                'nb_contrats' => $nbContrats,
                'nb_contrats_signes' => $nbContratsSignes,
                'taux_equipement' => $tauxEquipement,
                'en_cours_total' => $enCoursTotal,
                'mensualites_total' => $mensualitesTotal,
                'par_assureur' => $parAssureur,
            ],
            'conformite' => [
                'complets' => $clientsAvecValide,
                'partiels' => $clientsAvecDocSeulement,
                'incomplets' => $clientsIncomplets,
                'docs_expires' => $docsExpires,
                'docs_expirant_30j' => $docsExpirantBientot,
                'docs_pending' => $docsPending,
            ],
            'activite_ia' => [
                'enregistrements_mois' => $enregistrementsMois,
                'taux_diarisation' => $tauxDiarisation,
                'clients_enrichis' => $clientsEnrichisIA,
                'audio_6mois' => $audio6Mois,
            ],
            'commissions' => [
                'total_commission_mia' => (float) ($commissionTotals->total_commission_mia ?? 0),
                'encours_portefeuille' => (float) ($commissionTotals->encours_portefeuille ?? 0),
                'nb_lignes' => (int) ($commissionTotals->nb_lignes ?? 0),
                'nb_clients_bordereaux' => (int) ($commissionTotals->nb_clients_bordereaux ?? 0),
                'par_type' => $commParType,
            ],
            'equipe' => $equipe,
            'contrats_par_type' => $contratsParType,
            'nouveaux_6mois' => $nouveaux6Mois,
            'contrats_6mois' => $contrats6Mois,
            'opportunites' => [
                'sans_sante' => max(0, $total - $avecSante),
                'sans_prevoyance' => max(0, $total - $avecPrevoyance),
                'sans_retraite' => max(0, $total - $avecRetraite),
                'sans_epargne' => max(0, $total - $avecEpargne),
            ],
            'besoins_repartition' => array_values(array_map(
                fn ($label, $count) => ['label' => $label, 'count' => $count],
                array_keys($besoinsCounts),
                array_values($besoinsCounts)
            )),
        ]);
    }

    private function normalizeBesoin(string $besoin): string
    {
        $lower = mb_strtolower($besoin);
        if (str_contains($lower, 'retraite') || preg_match('/\bper\b/', $lower)) {
            return 'Retraite';
        }
        if (str_contains($lower, 'prévoyance') || str_contains($lower, 'prevoyance') || str_contains($lower, 'décès')) {
            return 'Prévoyance';
        }
        if (str_contains($lower, 'santé') || str_contains($lower, 'sante') || str_contains($lower, 'mutuelle')) {
            return 'Santé';
        }
        if (str_contains($lower, 'emprunt') || str_contains($lower, 'crédit')) {
            return 'Emprunteur';
        }
        if (str_contains($lower, 'épargne') || str_contains($lower, 'epargne') || str_contains($lower, 'assurance vie') || str_contains($lower, 'placement')) {
            return 'Épargne';
        }

        return 'Autre';
    }
}
