<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Str;

class ClientSyncService
{
    /**
     * Recherche un client existant ou en crée un nouveau en fonction des données vocales.
     */
    public function findOrCreateFromAnalysis(array $data, int $userId): Client
    {
        $existing = $this->findExistingClient($data, $userId);

        $cleanData = collect($data)
            ->map(fn ($v) => $v === '' ? null : $v)
            ->reject(function ($v) {
                if (is_array($v)) {
                    return empty($v);
                }
                return is_null($v);
            })
            ->toArray();

        if ($existing) {
            $existing->fill($cleanData);
            if ($existing->isDirty()) {
                $existing->save();
            }
            return $existing;
        }

        $cleanData['user_id'] = $userId;
        return Client::create($cleanData);
    }

    private function findExistingClient(array $data, int $userId): ?Client
    {
        if (!empty($data['id'])) {
            return Client::where('user_id', $userId)->find($data['id']);
        }

        if (!empty($data['email'])) {
            $email = strtolower(trim($data['email']));
            if (!empty($email)) {
                $match = Client::where('user_id', $userId)
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->first();
                if ($match) {
                    return $match;
                }
            }
        }

        if (!empty($data['telephone'])) {
            $normalizedPhone = $this->normalizePhone($data['telephone']);
            if ($normalizedPhone) {
                $match = Client::where('user_id', $userId)
                    ->get()
                    ->first(function (Client $client) use ($normalizedPhone) {
                        return $this->normalizePhone($client->telephone) === $normalizedPhone;
                    });
                if ($match) {
                    return $match;
                }
            }
        }

        $normalizedNom = $this->normalizeString($data['nom'] ?? null);
        $normalizedPrenom = $this->normalizeString($data['prenom'] ?? null);
        $dateNaissance = !empty($data['date_naissance']) ? trim($data['date_naissance']) : null;

        if (!$normalizedNom || !$normalizedPrenom) {
            return null;
        }

        $clients = Client::where('user_id', $userId)->get();

        $matchWithDate = $clients->first(function (Client $client) use ($normalizedNom, $normalizedPrenom, $dateNaissance) {
            return $this->normalizeString($client->nom) === $normalizedNom
                && $this->normalizeString($client->prenom) === $normalizedPrenom
                && $dateNaissance
                && $client->date_naissance === $dateNaissance;
        });

        if ($matchWithDate) {
            return $matchWithDate;
        }

        return $clients->first(function (Client $client) use ($normalizedNom, $normalizedPrenom) {
            return $this->normalizeString($client->nom) === $normalizedNom
                && $this->normalizeString($client->prenom) === $normalizedPrenom;
        });
    }

    private function normalizeString(?string $value): ?string
    {
        if (is_null($value)) {
            return null;
        }

        $normalized = Str::ascii(Str::lower(trim($value)));
        return $normalized === '' ? null : $normalized;
    }

    private function normalizePhone(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);
        if (empty($digits)) {
            return null;
        }

        if (str_starts_with($digits, '33') && strlen($digits) === 11) {
            $digits = '0' . substr($digits, 2);
        }

        return $digits;
    }
}
