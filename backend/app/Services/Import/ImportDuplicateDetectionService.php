<?php

namespace App\Services\Import;

use App\Models\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ImportDuplicateDetectionService
{
    private const CONFIDENCE_THRESHOLD_HIGH = 0.9;
    private const CONFIDENCE_THRESHOLD_MEDIUM = 0.7;
    private const CONFIDENCE_THRESHOLD_LOW = 0.5;

    private const WEIGHTS = [
        'email' => 0.35,
        'telephone' => 0.25,
        'nom_prenom_date' => 0.30,
        'nom_prenom' => 0.10,
    ];

    public function findDuplicates(array $normalizedData, int $teamId): array
    {
        $matches = [];
        $totalScore = 0;

        $emailMatches = $this->findByEmail($normalizedData, $teamId);
        if (!empty($emailMatches)) {
            $matches = array_merge($matches, $emailMatches);
            $totalScore += self::WEIGHTS['email'];
        }

        $phoneMatches = $this->findByPhone($normalizedData, $teamId);
        if (!empty($phoneMatches)) {
            foreach ($phoneMatches as $match) {
                if (!in_array($match['client_id'], array_column($matches, 'client_id'))) {
                    $matches[] = $match;
                }
            }
            $totalScore += self::WEIGHTS['telephone'];
        }

        $nameMatches = $this->findByNameAndBirthdate($normalizedData, $teamId);
        if (!empty($nameMatches)) {
            foreach ($nameMatches as $match) {
                $existingIndex = array_search($match['client_id'], array_column($matches, 'client_id'));
                if ($existingIndex !== false) {
                    $matches[$existingIndex]['score'] += $match['score'];
                    $matches[$existingIndex]['reasons'] = array_merge(
                        $matches[$existingIndex]['reasons'],
                        $match['reasons']
                    );
                } else {
                    $matches[] = $match;
                }
            }
        }

        usort($matches, fn($a, $b) => $b['score'] <=> $a['score']);

        $bestMatch = !empty($matches) ? $matches[0] : null;
        $confidence = $bestMatch ? min(1.0, $bestMatch['score']) : 0;

        return [
            'has_duplicates' => $confidence >= self::CONFIDENCE_THRESHOLD_LOW,
            'confidence' => $confidence,
            'confidence_level' => $this->getConfidenceLevel($confidence),
            'matches' => array_slice($matches, 0, 5),
            'best_match' => $bestMatch,
        ];
    }

    public function checkDuplicateInBatch(array $normalizedData, array $batchData, int $rowNumber): array
    {
        $duplicates = [];

        foreach ($batchData as $index => $otherRow) {
            if ($index >= $rowNumber) {
                continue;
            }

            $score = $this->compareRows($normalizedData, $otherRow);

            if ($score >= self::CONFIDENCE_THRESHOLD_MEDIUM) {
                $duplicates[] = [
                    'row_number' => $index,
                    'score' => $score,
                ];
            }
        }

        return $duplicates;
    }

    private function findByEmail(array $data, int $teamId): array
    {
        if (empty($data['email'])) {
            return [];
        }

        $clients = Client::where('team_id', $teamId)
            ->where('email', $data['email'])
            ->get();

        return $clients->map(fn($client) => [
            'client_id' => $client->id,
            'score' => self::WEIGHTS['email'],
            'reasons' => ['Email identique'],
            'client' => $this->formatClientInfo($client),
        ])->toArray();
    }

    private function findByPhone(array $data, int $teamId): array
    {
        if (empty($data['telephone'])) {
            return [];
        }

        $phone = preg_replace('/[^0-9]/', '', $data['telephone']);

        $clients = Client::where('team_id', $teamId)
            ->whereRaw("REPLACE(REPLACE(REPLACE(telephone, ' ', ''), '.', ''), '-', '') LIKE ?", ["%{$phone}%"])
            ->get();

        return $clients->map(fn($client) => [
            'client_id' => $client->id,
            'score' => self::WEIGHTS['telephone'],
            'reasons' => ['Téléphone identique'],
            'client' => $this->formatClientInfo($client),
        ])->toArray();
    }

    private function findByNameAndBirthdate(array $data, int $teamId): array
    {
        if (empty($data['nom']) || empty($data['prenom'])) {
            return [];
        }

        $nom = $this->normalizeName($data['nom']);
        $prenom = $this->normalizeName($data['prenom']);

        $query = Client::where('team_id', $teamId)
            ->where(function ($q) use ($nom, $prenom) {
                $q->whereRaw('LOWER(nom) LIKE ?', ["%{$nom}%"])
                    ->whereRaw('LOWER(prenom) LIKE ?', ["%{$prenom}%"]);
            })
            ->orWhere(function ($q) use ($nom, $prenom) {
                $q->whereRaw('LOWER(nom) LIKE ?', ["%{$prenom}%"])
                    ->whereRaw('LOWER(prenom) LIKE ?', ["%{$nom}%"]);
            });

        $clients = $query->get();

        $results = [];

        foreach ($clients as $client) {
            $nameScore = $this->calculateNameSimilarity(
                $data['nom'] ?? '',
                $data['prenom'] ?? '',
                $client->nom ?? '',
                $client->prenom ?? ''
            );

            $reasons = [];
            $score = 0;

            if ($nameScore >= 0.8) {
                $reasons[] = 'Nom et prénom similaires';
                $score += self::WEIGHTS['nom_prenom'] * $nameScore;
            }

            if (!empty($data['date_naissance']) && !empty($client->date_naissance)) {
                $importDate = $this->normalizeDate($data['date_naissance']);
                $clientDate = $this->normalizeDate($client->date_naissance);

                if ($importDate && $clientDate && $importDate === $clientDate) {
                    $reasons[] = 'Date de naissance identique';
                    $score += self::WEIGHTS['nom_prenom_date'];
                }
            }

            if (!empty($reasons)) {
                $results[] = [
                    'client_id' => $client->id,
                    'score' => $score,
                    'reasons' => $reasons,
                    'client' => $this->formatClientInfo($client),
                ];
            }
        }

        return $results;
    }

    private function compareRows(array $row1, array $row2): float
    {
        $score = 0;

        if (!empty($row1['email']) && !empty($row2['email'])) {
            if (strtolower($row1['email']) === strtolower($row2['email'])) {
                $score += self::WEIGHTS['email'];
            }
        }

        if (!empty($row1['telephone']) && !empty($row2['telephone'])) {
            $phone1 = preg_replace('/[^0-9]/', '', $row1['telephone']);
            $phone2 = preg_replace('/[^0-9]/', '', $row2['telephone']);
            if ($phone1 === $phone2) {
                $score += self::WEIGHTS['telephone'];
            }
        }

        $nameScore = $this->calculateNameSimilarity(
            $row1['nom'] ?? '',
            $row1['prenom'] ?? '',
            $row2['nom'] ?? '',
            $row2['prenom'] ?? ''
        );

        if ($nameScore >= 0.8) {
            $score += self::WEIGHTS['nom_prenom'] * $nameScore;

            if (!empty($row1['date_naissance']) && !empty($row2['date_naissance'])) {
                $date1 = $this->normalizeDate($row1['date_naissance']);
                $date2 = $this->normalizeDate($row2['date_naissance']);
                if ($date1 && $date2 && $date1 === $date2) {
                    $score += self::WEIGHTS['nom_prenom_date'];
                }
            }
        }

        return min(1.0, $score);
    }

    private function calculateNameSimilarity(string $nom1, string $prenom1, string $nom2, string $prenom2): float
    {
        $nom1 = $this->normalizeName($nom1);
        $nom2 = $this->normalizeName($nom2);
        $prenom1 = $this->normalizeName($prenom1);
        $prenom2 = $this->normalizeName($prenom2);

        $directMatch = $this->stringSimilarity($nom1, $nom2) * $this->stringSimilarity($prenom1, $prenom2);

        $inverseMatch = $this->stringSimilarity($nom1, $prenom2) * $this->stringSimilarity($prenom1, $nom2);

        return max($directMatch, $inverseMatch);
    }

    private function stringSimilarity(string $str1, string $str2): float
    {
        if (empty($str1) || empty($str2)) {
            return 0;
        }

        if ($str1 === $str2) {
            return 1.0;
        }

        $levenshtein = levenshtein($str1, $str2);
        $maxLen = max(strlen($str1), strlen($str2));

        return 1 - ($levenshtein / $maxLen);
    }

    private function normalizeName(string $name): string
    {
        $normalized = Str::lower($name);
        $normalized = Str::ascii($normalized);
        $normalized = preg_replace('/[^a-z]/', '', $normalized);

        return $normalized;
    }

    private function normalizeDate(?string $date): ?string
    {
        if (empty($date)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($date)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getConfidenceLevel(float $confidence): string
    {
        if ($confidence >= self::CONFIDENCE_THRESHOLD_HIGH) {
            return 'high';
        }
        if ($confidence >= self::CONFIDENCE_THRESHOLD_MEDIUM) {
            return 'medium';
        }
        if ($confidence >= self::CONFIDENCE_THRESHOLD_LOW) {
            return 'low';
        }

        return 'none';
    }

    private function formatClientInfo(Client $client): array
    {
        return [
            'id' => $client->id,
            'nom' => $client->nom,
            'prenom' => $client->prenom,
            'email' => $client->email,
            'telephone' => $client->telephone,
            'date_naissance' => $client->date_naissance,
        ];
    }
}
