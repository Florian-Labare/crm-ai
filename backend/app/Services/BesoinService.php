<?php

namespace App\Services;

use App\Models\Client;

/**
 * Source unique de vérité pour la logique "besoins" assurance.
 *
 * Centralise :
 * - Le mapping alias → slug canonique
 * - L'inférence des besoins depuis les sections BAE existantes
 * - La synchronisation du champ client.besoins
 */
class BesoinService
{
    /** Slugs canoniques reconnus par le système */
    public const VALID_SLUGS = ['prevoyance', 'retraite', 'epargne', 'sante', 'emprunteur'];

    /** Labels affichables */
    public const LABELS = [
        'prevoyance' => 'Prévoyance',
        'retraite'   => 'Retraite',
        'epargne'    => 'Épargne',
        'sante'      => 'Santé',
        'emprunteur' => 'Emprunteur',
    ];

    /**
     * Mapping exhaustif : chaine libre → slug canonique.
     * Fusionne toutes les entrées des implémentations précédentes.
     */
    private const ALIAS_MAP = [
        // Prévoyance
        'prevoyance'               => 'prevoyance',
        'prévoyance'               => 'prevoyance',
        'décès'                    => 'prevoyance',
        'deces'                    => 'prevoyance',
        'invalidité'               => 'prevoyance',
        'invalidite'               => 'prevoyance',
        'incapacité'               => 'prevoyance',
        'incapacite'               => 'prevoyance',
        'arrêt de travail'         => 'prevoyance',
        'arret de travail'         => 'prevoyance',
        'obsèques'                 => 'prevoyance',
        'obseques'                 => 'prevoyance',
        'protection sociale'       => 'prevoyance',
        'garanties collectives'    => 'prevoyance',
        // Retraite
        'retraite'                 => 'retraite',
        'per'                      => 'retraite',
        'plan epargne retraite'    => 'retraite',
        'plan épargne retraite'    => 'retraite',
        'pension'                  => 'retraite',
        'retraite complémentaire'  => 'retraite',
        // Épargne
        'épargne'                  => 'epargne',
        'epargne'                  => 'epargne',
        'placement'                => 'epargne',
        'assurance vie'            => 'epargne',
        'assurance-vie'            => 'epargne',
        'capitalisation'           => 'epargne',
        'assurance vie capitalisation' => 'epargne',
        'pea'                      => 'epargne',
        'patrimoine'               => 'epargne',
        'investissement'           => 'epargne',
        // Santé
        'santé'                    => 'sante',
        'sante'                    => 'sante',
        'mutuelle'                 => 'sante',
        'complémentaire santé'     => 'sante',
        'complementaire sante'     => 'sante',
        'complémentaire'           => 'sante',
        'complementaire'           => 'sante',
        // Emprunteur
        'emprunteur'               => 'emprunteur',
        'ade'                      => 'emprunteur',
        'assurance emprunteur'     => 'emprunteur',
        'assurance de prêt'        => 'emprunteur',
        'assurance de pret'        => 'emprunteur',
        'prêt'                     => 'emprunteur',
        'pret'                     => 'emprunteur',
        'crédit'                   => 'emprunteur',
        'credit'                   => 'emprunteur',
        'emprunt'                  => 'emprunteur',
    ];

    /** Mapping slug → relation BAE sur le modèle Client */
    private const BAE_RELATIONS = [
        'prevoyance' => 'baePrevoyance',
        'retraite'   => 'baeRetraite',
        'epargne'    => 'baeEpargne',
        'sante'      => 'santeSouhait',
    ];

    /**
     * Normalise un tableau de chaînes libres vers des slugs canoniques uniques.
     */
    public function normalizeSlugs(array $besoins): array
    {
        $slugs = [];
        foreach ($besoins as $besoin) {
            $key = mb_strtolower(trim((string) $besoin));
            $slug = self::ALIAS_MAP[$key] ?? null;
            if ($slug && !in_array($slug, $slugs, true)) {
                $slugs[] = $slug;
            }
        }
        return $slugs;
    }

    /**
     * Retourne les besoins effectifs d'un client :
     * besoins déclarés (normalisés) + besoins inférés des sections BAE existantes.
     */
    public function getEffectiveBesoins(Client $client): array
    {
        $client->loadMissing(array_values(self::BAE_RELATIONS));

        $raw = is_array($client->besoins)
            ? $client->besoins
            : (json_decode($client->besoins ?? '[]', true) ?? []);

        $besoins = $this->normalizeSlugs($raw);

        foreach (self::BAE_RELATIONS as $slug => $relation) {
            if ($client->{$relation} && !in_array($slug, $besoins, true)) {
                $besoins[] = $slug;
            }
        }

        return $besoins;
    }

    /**
     * Synchronise client.besoins avec les sections BAE existantes.
     * Ajoute les slugs manquants, ne retire rien.
     */
    public function syncBesoinsFromBae(Client $client): void
    {
        $effective = $this->getEffectiveBesoins($client);
        $current   = is_array($client->besoins) ? $client->besoins : [];

        if (count($effective) !== count($current) || array_diff($effective, $current)) {
            $client->update(['besoins' => $effective]);
        }
    }

    /**
     * Crée automatiquement les sections BAE manquantes pour les besoins déclarés.
     */
    public function createBaeSectionsFromBesoins(Client $client, array $besoins): void
    {
        $slugs = $this->normalizeSlugs($besoins);

        foreach (self::BAE_RELATIONS as $slug => $relation) {
            if (in_array($slug, $slugs, true) && !$client->{$relation}) {
                $client->{$relation}()->create(['client_id' => $client->id]);
            }
        }
    }

    /**
     * Retourne le label lisible d'un slug.
     */
    public function label(string $slug): string
    {
        return self::LABELS[$slug] ?? ucfirst($slug);
    }
}
