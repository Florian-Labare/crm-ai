<?php

namespace App\Services;

use App\Models\Client;
use Carbon\Carbon;

/**
 * Service de résolution des champs calculés pour le mapping de documents
 *
 * Chaque méthode publique correspond à un champ calculé défini dans config/document_mapping.php
 */
class ComputedFieldResolver
{
    /**
     * Résout un champ calculé par son nom de méthode
     */
    public function resolve(string $methodName, Client $client): string
    {
        if (!method_exists($this, $methodName)) {
            return '';
        }

        return (string) $this->{$methodName}($client);
    }

    // ===============================
    // ACTIVITÉS SPORTIVES ET RISQUES
    // ===============================

    public function activitesSportives(Client $client): string
    {
        if (!$client->activites_sportives) {
            return 'Non';
        }
        $details = $client->details_activites_sportives ? ' (' . $client->details_activites_sportives . ')' : '';
        return 'Oui' . $details;
    }

    public function risquesParticuliers(Client $client): string
    {
        if (!$client->risques_professionnels) {
            return 'Non';
        }
        $details = $client->details_risques_professionnels ? ' (' . $client->details_risques_professionnels . ')' : '';
        return 'Oui' . $details;
    }

    // ===============================
    // ENFANTS
    // ===============================

    public function enfantACharge(Client $client): string
    {
        if (!$client->enfants || $client->enfants->isEmpty()) {
            return '0';
        }
        $aCharge = $client->enfants->where('fiscalement_a_charge', true)->count();
        return (string) $aCharge;
    }

    public function nomPrenomEnfant1(Client $client): string
    {
        $enfant = $client->enfants->get(0);
        return $enfant ? $enfant->prenom . ' ' . $enfant->nom : '';
    }

    public function nomPrenomEnfant2(Client $client): string
    {
        $enfant = $client->enfants->get(1);
        return $enfant ? $enfant->prenom . ' ' . $enfant->nom : '';
    }

    public function nomPrenomEnfant3(Client $client): string
    {
        $enfant = $client->enfants->get(2);
        return $enfant ? $enfant->prenom . ' ' . $enfant->nom : '';
    }

    public function dateNaissanceEnfant1(Client $client): string
    {
        $enfant = $client->enfants->get(0);
        if ($enfant && $enfant->date_naissance) {
            return $this->safeFormatDate($enfant->date_naissance);
        }
        return '';
    }

    public function dateNaissanceEnfant2(Client $client): string
    {
        $enfant = $client->enfants->get(1);
        if ($enfant && $enfant->date_naissance) {
            return $this->safeFormatDate($enfant->date_naissance);
        }
        return '';
    }

    public function dateNaissanceEnfant3(Client $client): string
    {
        $enfant = $client->enfants->get(2);
        if ($enfant && $enfant->date_naissance) {
            return $this->safeFormatDate($enfant->date_naissance);
        }
        return '';
    }

    public function gardeAlterneeCas(Client $client): string
    {
        if ($client->enfants->count() === 0) {
            return '';
        }
        return $client->enfants->where('garde_alternee', true)->count() > 0 ? 'Oui' : 'Non';
    }

    public function parents(Client $client): string
    {
        if ($client->enfants->count() === 0) {
            return '';
        }
        $nomClient = trim(($client->prenom ?? '') . ' ' . ($client->nom ?? ''));
        $nomConjoint = $client->conjoint
            ? ' et ' . trim(($client->conjoint->prenom ?? '') . ' ' . ($client->conjoint->nom ?? ''))
            : '';
        return trim($nomClient . $nomConjoint);
    }

    // ===============================
    // BAE RETRAITE
    // ===============================

    public function siRetraiteConjoint(Client $client): string
    {
        return $client->baeRetraite && $client->baeRetraite->age_depart_retraite_conjoint ? 'Oui' : 'Non';
    }

    // ===============================
    // ACTIFS FINANCIERS (depuis bae_epargne)
    // ===============================

    public function natureFinancier1(Client $client): string
    {
        return $this->getActifFinancierDetail($client, 0);
    }

    public function natureFinancier2(Client $client): string
    {
        return $this->getActifFinancierDetail($client, 1);
    }

    public function natureFinancier3(Client $client): string
    {
        return $this->getActifFinancierDetail($client, 2);
    }

    private function getActifFinancierDetail(Client $client, int $index): string
    {
        if ($client->baeEpargne && is_array($client->baeEpargne->actifs_financiers_details)) {
            return $client->baeEpargne->actifs_financiers_details[$index] ?? '';
        }
        return '';
    }

    // ===============================
    // ACTIFS IMMOBILIERS (depuis bae_epargne)
    // ===============================

    public function designationImmo1(Client $client): string
    {
        return $this->getActifImmoDetail($client, 0);
    }

    public function designationImmo2(Client $client): string
    {
        return $this->getActifImmoDetail($client, 1);
    }

    public function designationImmo3(Client $client): string
    {
        return $this->getActifImmoDetail($client, 2);
    }

    private function getActifImmoDetail(Client $client, int $index): string
    {
        if ($client->baeEpargne && is_array($client->baeEpargne->actifs_immo_details)) {
            return $client->baeEpargne->actifs_immo_details[$index] ?? '';
        }
        return '';
    }

    // ===============================
    // PASSIFS (depuis bae_epargne)
    // ===============================

    public function preteur1(Client $client): string
    {
        return $this->getPassifDetail($client, 0);
    }

    public function preteur2(Client $client): string
    {
        return $this->getPassifDetail($client, 1);
    }

    public function preteur3(Client $client): string
    {
        return $this->getPassifDetail($client, 2);
    }

    private function getPassifDetail(Client $client, int $index): string
    {
        if ($client->baeEpargne && is_array($client->baeEpargne->passifs_details)) {
            return $client->baeEpargne->passifs_details[$index] ?? '';
        }
        return '';
    }

    // ===============================
    // CHARGES (depuis bae_epargne)
    // ===============================

    public function fiscalCharge1(Client $client): string
    {
        return $this->getChargeDetail($client, 0);
    }

    public function fiscalCharge2(Client $client): string
    {
        return $this->getChargeDetail($client, 1);
    }

    public function fiscalCharge3(Client $client): string
    {
        return $this->getChargeDetail($client, 2);
    }

    private function getChargeDetail(Client $client, int $index): string
    {
        if ($client->baeEpargne && is_array($client->baeEpargne->charges_details)) {
            return $client->baeEpargne->charges_details[$index] ?? '';
        }
        return '';
    }

    // ===============================
    // QUESTIONNAIRE RISQUE
    // ===============================

    public function profilRisqueClient(Client $client): string
    {
        return $client->questionnaireRisque?->profil_calcule ?? 'Non défini';
    }

    // ===============================
    // BESOINS
    // ===============================

    public function siOuiPrevoyance(Client $client): string
    {
        $besoins = is_array($client->besoins) ? $client->besoins : [];
        return in_array('prévoyance', $besoins) ? 'Oui' : 'Non';
    }

    // ===============================
    // DATES ET METADATA
    // ===============================

    public function dateActuelle(Client $client): string
    {
        return now()->format('d/m/Y');
    }

    public function dateDocument(Client $client): string
    {
        return now()->format('d/m/Y');
    }

    public function dateGaranties(Client $client): string
    {
        return now()->addDays(30)->format('d/m/Y');
    }

    // ===============================
    // SANTÉ
    // ===============================

    public function analyseImagerie(Client $client): string
    {
        return $client->santeSouhait && $client->santeSouhait->niveau_analyses_imagerie ? 'Oui' : 'Non';
    }

    public function auxiliairesMedicaux(Client $client): string
    {
        return $client->santeSouhait && $client->santeSouhait->niveau_auxiliaires_medicaux ? 'Oui' : 'Non';
    }

    public function dentaire(Client $client): string
    {
        return $client->santeSouhait && $client->santeSouhait->niveau_dentaire ? 'Oui' : 'Non';
    }

    public function hospitalisation(Client $client): string
    {
        return $client->santeSouhait && $client->santeSouhait->niveau_hospitalisation ? 'Oui' : 'Non';
    }

    public function medecinGeneraliste(Client $client): string
    {
        return $client->santeSouhait && $client->santeSouhait->niveau_medecin_generaliste ? 'Oui' : 'Non';
    }

    public function optiqueLentilles(Client $client): string
    {
        return $client->santeSouhait && $client->santeSouhait->niveau_optique ? 'Oui' : 'Non';
    }

    public function protheseAuditive(Client $client): string
    {
        return $client->santeSouhait && $client->santeSouhait->niveau_protheses_auditives ? 'Oui' : 'Non';
    }

    // ===============================
    // ÉPARGNE DISPONIBLE
    // ===============================

    public function clientDisposeEpargneDisponible(Client $client): string
    {
        if (!$client->baeEpargne || !$client->baeEpargne->montant_epargne_disponible) {
            return 'Non';
        }
        return $client->baeEpargne->montant_epargne_disponible > 0 ? 'Oui' : 'Non';
    }

    // ===============================
    // PROFIL DE RISQUE
    // ===============================

    public function toleranceRisqueClient(Client $client): string
    {
        return $client->questionnaireRisque?->questionnaireFinancier?->tolerance_risque ?? 'Non défini';
    }

    public function pourcentageMaxPerte(Client $client): string
    {
        return $client->questionnaireRisque?->questionnaireFinancier?->pourcentage_perte_max ?? '';
    }

    // ===============================
    // TEXTES STATIQUES
    // ===============================

    public function socogeaVousIndique(Client $client): string
    {
        return 'SOCOGEA vous indique';
    }

    public function socogeaVousIndiqueQue(Client $client): string
    {
        return 'SOCOGEA vous indique que';
    }

    public function presentRapportRepond(Client $client): string
    {
        return 'Le présent rapport répond';
    }

    // ===============================
    // HELPERS PRIVÉS
    // ===============================

    private function safeFormatDate(?string $date): string
    {
        if (empty($date)) {
            return '';
        }
        // Si la date contient des caractères invalides (XX, ??, etc.)
        if (preg_match('/[Xx?]+/', $date)) {
            return $date;
        }
        try {
            return Carbon::parse($date)->format('d/m/Y');
        } catch (\Exception $e) {
            return $date;
        }
    }
}
