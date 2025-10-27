<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Arr;

class ClientSyncService
{
    /**
     * Recherche un client selon des critères clés (id, nom, prénom, date de naissance)
     * ou crée un nouveau client si aucun ne correspond.
     * Met à jour uniquement les champs explicitement évoqués.
     */
    public function findOrCreateFromAnalysis(array $data): Client
    {
        $criteria = collect($data)
            ->only(['id', 'nom', 'prenom', 'datedenaissance'])
            ->map(fn ($v) => trim(strtolower((string) $v)))
            ->toArray();

        $query = Client::query();

        if (!empty($criteria['id'])) {
            $query->where('id', $criteria['id']);
        }

        if (!empty($criteria['nom'])) {
            $query->whereRaw('LOWER(nom) = ?', [$criteria['nom']]);
        }

        if (!empty($criteria['prenom'])) {
            $query->whereRaw('LOWER(prenom) = ?', [$criteria['prenom']]);
        }

        if (!empty($criteria['datedenaissance'])) {
            $query->where('datedenaissance', $criteria['datedenaissance']);
        }

        $existing = $query->first();

        // 🔧 Nettoyage : transformer les champs vides en null
        $cleanData = collect($data)
            ->map(fn($v) => $v === '' ? null : $v)
            ->filter(fn($v) => !is_null($v)) // ⚠️ on garde uniquement les champs réellement renseignés
            ->toArray();

        if ($existing) {
            // 🔄 On ne met à jour que les champs évoqués dans $cleanData
            $existing->fill($cleanData);

            // On ne sauvegarde que si quelque chose a changé
            if ($existing->isDirty()) {
                $existing->save();
            }

            return $existing;
        }

        // 🆕 Création d’un nouveau client avec les données valides
        return Client::create($cleanData);
    }
}