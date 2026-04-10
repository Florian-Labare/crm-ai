<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Service de validation et correction de la catégorisation des actifs.
 *
 * Ce service agit comme un garde-fou pour s'assurer que les actifs
 * sont correctement catégorisés après l'extraction GPT.
 *
 * Catégories :
 * - ACTIFS FINANCIERS : Assurance-vie, PEA, PER, compte-titres, livrets, SCPI, actions, obligations
 * - BIENS IMMOBILIERS : Maisons, appartements, terrains, locaux commerciaux, SCI
 * - AUTRES ACTIFS : Crypto, or, art, bijoux, collections, métaux précieux
 */
class AssetCategorizationService {
    /**
     * Mots-clés pour identifier les ACTIFS FINANCIERS
     */
    private const ACTIFS_FINANCIERS_KEYWORDS = [
        // Produits d'épargne bancaire
        'assurance-vie', 'assurance vie', 'av', 'contrat vie',
        'pea', 'plan épargne actions', 'plan epargne actions',
        'per', 'plan épargne retraite', 'plan epargne retraite',
        'compte-titres', 'compte titres', 'cto',
        'livret a', 'livret-a', 'livreta',
        'ldds', 'ldd', 'livret développement durable',
        'lep', 'livret épargne populaire',
        'pel', 'plan épargne logement',
        'cel', 'compte épargne logement',
        'livret jeune',
        // Placements financiers
        'scpi', 'pierre papier',
        'opcvm', 'sicav', 'fcp',
        'actions', 'obligations', 'bourse',
        'fonds euro', 'fonds euros',
        'etf', 'tracker',
        'unités de compte', 'uc',
        // Assurances
        'capitalisation', 'contrat de capitalisation',
    ];

    /**
     * Mots-clés pour identifier les AUTRES ACTIFS (crypto, or, art...)
     */
    private const AUTRES_ACTIFS_KEYWORDS = [
        // Cryptomonnaies
        'crypto', 'cryptomonnaie', 'cryptomonnaies', 'cryptocurrency',
        'bitcoin', 'btc', 'ethereum', 'eth', 'solana', 'sol',
        'ripple', 'xrp', 'cardano', 'ada', 'binance', 'bnb',
        'dogecoin', 'doge', 'shiba', 'nft', 'token', 'altcoin',
        'wallet crypto', 'portefeuille crypto',
        // Métaux précieux
        'or', 'lingot', 'lingots', 'pièces d\'or', 'pieces or',
        'argent métal', 'argent metal', 'platine', 'palladium',
        'napoléon', 'napoleon', 'krugerrand', 'once or',
        // Art et collections
        'art', 'tableau', 'tableaux', 'œuvre', 'oeuvre', 'sculpture',
        'collection', 'timbres', 'philatélie', 'numismatique',
        'montres de luxe', 'montre luxe', 'rolex', 'patek',
        'vin', 'cave à vin', 'grands crus', 'whisky',
        'voiture collection', 'voiture ancienne', 'véhicule collection',
        // Bijoux
        'bijoux', 'bijou', 'diamant', 'diamants', 'pierres précieuses',
        'joaillerie', 'bague', 'collier précieux',
        // Autres
        'antiquités', 'antiquites', 'meubles anciens',
        'argent liquide', 'cash', 'espèces',
    ];

    /**
     * Mots-clés pour identifier les BIENS IMMOBILIERS
     */
    private const BIENS_IMMOBILIERS_KEYWORDS = [
        // Types de biens
        'maison', 'appartement', 'studio', 'loft', 'duplex', 'triplex',
        'villa', 'pavillon', 'chalet', 'mas',
        'immeuble', 'local commercial', 'bureau', 'entrepôt',
        'terrain', 'parcelle', 'foncier',
        'garage', 'parking', 'box', 'cave',
        // Usage
        'résidence principale', 'residence principale', 'rp',
        'résidence secondaire', 'residence secondaire', 'rs',
        'bien locatif', 'investissement locatif', 'location',
        // Structure juridique
        'sci', 'société civile immobilière',
        'indivision', 'démembrement', 'nue-propriété', 'usufruit',
        // Termes génériques immobilier
        'immobilier', 'immo', 'propriété', 'bien immobilier',
        'acquisition immobilière', 'achat immobilier',
    ];

    /**
     * Valide et corrige la catégorisation des actifs extraits.
     *
     * @param  array  $extractedData  Données extraites par GPT
     * @return array Données corrigées avec les actifs bien catégorisés
     */
    public function validateAndCorrect(array $extractedData): array {
        Log::info('[AssetCategorizationService] Début de la validation des catégorisations');

        $actifsFinanciers = $extractedData['client_actifs_financiers'] ?? [];
        $biensImmobiliers = $extractedData['client_biens_immobiliers'] ?? [];
        $autresActifs = $extractedData['client_autres_epargnes'] ?? [];

        Log::info('[AssetCategorizationService] État initial', [
            'actifs_financiers' => count($actifsFinanciers),
            'biens_immobiliers' => count($biensImmobiliers),
            'autres_actifs' => count($autresActifs),
        ]);

        $corrections = [
            'moved_to_financiers' => [],
            'moved_to_immo' => [],
            'moved_to_autres' => [],
            'excluded_residence_principale' => [],
        ];

        // 1. Vérifier les actifs financiers mal catégorisés
        $actifsFinanciers = $this->validateActifsFinanciers($actifsFinanciers, $biensImmobiliers, $autresActifs, $corrections);

        // 2. Vérifier les biens immobiliers mal catégorisés
        $biensImmobiliers = $this->validateBiensImmobiliers($biensImmobiliers, $actifsFinanciers, $autresActifs, $corrections);

        // 3. Vérifier les autres actifs mal catégorisés
        $autresActifs = $this->validateAutresActifs($autresActifs, $actifsFinanciers, $biensImmobiliers, $corrections);

        // 4. 🏠 RÈGLE MÉTIER : Exclure la résidence principale des actifs d'investissement
        // La RP n'est pas un actif d'investissement, elle ne doit pas apparaître dans le patrimoine
        $biensImmobiliers = $this->filterResidencePrincipale($biensImmobiliers, $corrections);

        // Log des corrections effectuées
        $hasCorrections = ! empty($corrections['moved_to_financiers'])
            || ! empty($corrections['moved_to_immo'])
            || ! empty($corrections['moved_to_autres'])
            || ! empty($corrections['excluded_residence_principale']);

        if ($hasCorrections) {
            Log::warning('[AssetCategorizationService] Corrections de catégorisation effectuées', $corrections);
        }

        // Reconstruire les données corrigées
        if (! empty($actifsFinanciers)) {
            $extractedData['client_actifs_financiers'] = $actifsFinanciers;
        }
        if (! empty($biensImmobiliers)) {
            $extractedData['client_biens_immobiliers'] = $biensImmobiliers;
        }
        if (! empty($autresActifs)) {
            $extractedData['client_autres_epargnes'] = $autresActifs;
        }

        Log::info('[AssetCategorizationService] Validation terminée', [
            'actifs_financiers_count' => count($actifsFinanciers),
            'biens_immobiliers_count' => count($biensImmobiliers),
            'autres_actifs_count' => count($autresActifs),
        ]);

        return $extractedData;
    }

    /**
     * Valide les actifs financiers et déplace les éléments mal catégorisés
     */
    private function validateActifsFinanciers(array $actifs, array &$biensImmo, array &$autresActifs, array &$corrections): array {
        $validActifs = [];

        foreach ($actifs as $actif) {
            $text = $this->getSearchableText($actif);
            $category = $this->detectCategory($text);

            if ($category === 'IMMO') {
                // C'est de l'immobilier → déplacer vers biens_immobiliers
                $biensImmo[] = $this->convertToImmobilier($actif);
                $corrections['moved_to_immo'][] = $actif['nature'] ?? 'inconnu';
                Log::info('[AssetCategorizationService] Actif financier reclassé en immobilier', ['actif' => $actif]);
            } elseif ($category === 'AUTRES') {
                // C'est un autre actif (crypto, or...) → déplacer vers autres_epargnes
                $autresActifs[] = $this->convertToAutreActif($actif);
                $corrections['moved_to_autres'][] = $actif['nature'] ?? 'inconnu';
                Log::info('[AssetCategorizationService] Actif financier reclassé en autre actif', ['actif' => $actif]);
            } else {
                // C'est bien un actif financier
                $validActifs[] = $actif;
            }
        }

        return $validActifs;
    }

    /**
     * Valide les biens immobiliers et déplace les éléments mal catégorisés
     */
    private function validateBiensImmobiliers(array $biens, array &$actifsFinanciers, array &$autresActifs, array &$corrections): array {
        $validBiens = [];

        foreach ($biens as $bien) {
            $text = $this->getSearchableText($bien);
            $category = $this->detectCategory($text);

            if ($category === 'FINANCIER') {
                // C'est un actif financier → déplacer
                $actifsFinanciers[] = $this->convertToActifFinancier($bien);
                $corrections['moved_to_financiers'][] = $bien['designation'] ?? 'inconnu';
                Log::info('[AssetCategorizationService] Bien immobilier reclassé en actif financier', ['bien' => $bien]);
            } elseif ($category === 'AUTRES') {
                // C'est un autre actif → déplacer
                $autresActifs[] = $this->convertToAutreActifFromImmo($bien);
                $corrections['moved_to_autres'][] = $bien['designation'] ?? 'inconnu';
                Log::info('[AssetCategorizationService] Bien immobilier reclassé en autre actif', ['bien' => $bien]);
            } else {
                // C'est bien de l'immobilier
                $validBiens[] = $bien;
            }
        }

        return $validBiens;
    }

    /**
     * Valide les autres actifs et déplace les éléments mal catégorisés
     */
    private function validateAutresActifs(array $autres, array &$actifsFinanciers, array &$biensImmo, array &$corrections): array {
        $validAutres = [];

        foreach ($autres as $autre) {
            $text = $this->getSearchableText($autre);
            $category = $this->detectCategory($text);

            if ($category === 'FINANCIER') {
                // C'est un actif financier → déplacer
                $actifsFinanciers[] = $this->convertToActifFinancierFromAutre($autre);
                $corrections['moved_to_financiers'][] = $autre['designation'] ?? 'inconnu';
                Log::info('[AssetCategorizationService] Autre actif reclassé en actif financier', ['autre' => $autre]);
            } elseif ($category === 'IMMO') {
                // C'est de l'immobilier → déplacer
                $biensImmo[] = $this->convertToImmobilierFromAutre($autre);
                $corrections['moved_to_immo'][] = $autre['designation'] ?? 'inconnu';
                Log::info('[AssetCategorizationService] Autre actif reclassé en immobilier', ['autre' => $autre]);
            } else {
                // C'est bien un autre actif
                $validAutres[] = $autre;
            }
        }

        return $validAutres;
    }

    /**
     * Détecte la catégorie d'un actif basé sur son texte
     *
     * ORDRE DE PRIORITÉ :
     * 1. IMMOBILIER (locatif, appartement, maison) - PRIORITÉ HAUTE car souvent mal classé
     * 2. AUTRES (crypto, or, art) - seulement si pas immobilier
     * 3. FINANCIER - par défaut
     */
    private function detectCategory(string $text): string {
        $text = mb_strtolower($text, 'UTF-8');

        // Normaliser les accents pour la comparaison
        $textNormalized = $this->removeAccents($text);

        // 🏠 PRIORITÉ 1 : Vérifier l'IMMOBILIER en premier (souvent mal classé)
        // Mots-clés très spécifiques à l'immobilier
        $immoKeywords = [
            'locatif', 'location', 'loue', 'louer',
            'appartement', 'appart',
            'maison', 'villa', 'pavillon',
            'studio', 'loft', 'duplex',
            'immeuble', 'terrain', 'parcelle',
            'residence secondaire', 'résidence secondaire',
            'bien immobilier', 'investissement immo',
            'sci', 'indivision',
        ];

        foreach ($immoKeywords as $keyword) {
            $keywordNormalized = $this->removeAccents($keyword);
            if (str_contains($textNormalized, $keywordNormalized)) {
                Log::debug("[AssetCategorizationService] Détecté comme IMMO: '$text' (keyword: $keyword)");

                return 'IMMO';
            }
        }

        // 💎 PRIORITÉ 2 : Vérifier les AUTRES ACTIFS (crypto, or, art)
        $autresKeywords = [
            'crypto', 'bitcoin', 'btc', 'ethereum', 'eth',
            'solana', 'ripple', 'xrp', 'cardano', 'dogecoin',
            'nft', 'token', 'altcoin', 'wallet',
            'or', 'lingot', 'pieces or', 'pièces or',
            'argent metal', 'platine', 'napoleon', 'napoléon',
            'art', 'tableau', 'sculpture', 'oeuvre', 'œuvre',
            'collection', 'timbres', 'numismatique',
            'montre luxe', 'rolex', 'patek',
            'vin', 'grands crus', 'whisky',
            'bijoux', 'diamant', 'joaillerie',
            'antiquite', 'antiquité',
            'liquide', 'cash', 'especes', 'espèces',
        ];

        foreach ($autresKeywords as $keyword) {
            $keywordNormalized = $this->removeAccents($keyword);
            if (str_contains($textNormalized, $keywordNormalized)) {
                Log::debug("[AssetCategorizationService] Détecté comme AUTRES: '$text' (keyword: $keyword)");

                return 'AUTRES';
            }
        }

        // 💰 Par défaut, actif financier
        return 'FINANCIER';
    }

    /**
     * Supprime les accents d'une chaîne pour faciliter la comparaison
     */
    private function removeAccents(string $text): string {
        $text = mb_strtolower($text, 'UTF-8');
        $accents = ['é', 'è', 'ê', 'ë', 'à', 'â', 'ä', 'ù', 'û', 'ü', 'ô', 'ö', 'î', 'ï', 'ç'];
        $noAccents = ['e', 'e', 'e', 'e', 'a', 'a', 'a', 'u', 'u', 'u', 'o', 'o', 'i', 'i', 'c'];

        return str_replace($accents, $noAccents, $text);
    }

    /**
     * 🏠 RÈGLE MÉTIER : Filtre la résidence principale des actifs d'investissement
     *
     * La résidence principale n'est PAS un actif d'investissement car :
     * - Le client y habite, il ne peut pas la vendre facilement
     * - Elle n'est pas liquide
     * - Elle ne génère pas de revenus
     *
     * Seuls les biens locatifs/investissement doivent apparaître dans le patrimoine.
     */
    private function filterResidencePrincipale(array $biensImmobiliers, array &$corrections): array {
        $filteredBiens = [];

        foreach ($biensImmobiliers as $bien) {
            $designation = mb_strtolower($bien['designation'] ?? '', 'UTF-8');
            $designationNormalized = $this->removeAccents($designation);

            // Détecter si c'est une résidence principale
            $isResidencePrincipale = (
                str_contains($designationNormalized, 'residence principale') ||
                str_contains($designationNormalized, 'rp') ||
                str_contains($designationNormalized, 'domicile') ||
                // Si c'est juste "maison" ou "appartement" sans mention de "locatif", c'est probablement la RP
                (
                    (str_contains($designationNormalized, 'maison') || str_contains($designationNormalized, 'appartement'))
                    && ! str_contains($designationNormalized, 'locatif')
                    && ! str_contains($designationNormalized, 'location')
                    && ! str_contains($designationNormalized, 'secondaire')
                    && ! str_contains($designationNormalized, 'investissement')
                )
            );

            if ($isResidencePrincipale) {
                $corrections['excluded_residence_principale'][] = $bien['designation'] ?? 'inconnu';
                Log::info('[AssetCategorizationService] 🏠 Résidence principale exclue des actifs', [
                    'designation' => $bien['designation'] ?? 'inconnu',
                    'valeur' => $bien['valeur_actuelle_estimee'] ?? 'non renseignée',
                ]);

                // On n'ajoute PAS ce bien à la liste filtrée
                continue;
            }

            // C'est un bien d'investissement, on le garde
            $filteredBiens[] = $bien;
        }

        return $filteredBiens;
    }

    /**
     * Extrait le texte recherchable d'un élément
     */
    private function getSearchableText(array $item): string {
        $parts = [];

        // Champs communs
        if (isset($item['nature'])) {
            $parts[] = $item['nature'];
        }
        if (isset($item['designation'])) {
            $parts[] = $item['designation'];
        }
        if (isset($item['etablissement'])) {
            $parts[] = $item['etablissement'];
        }
        if (isset($item['detenteur'])) {
            $parts[] = $item['detenteur'];
        }
        if (isset($item['forme_propriete'])) {
            $parts[] = $item['forme_propriete'];
        }

        return implode(' ', $parts);
    }

    /**
     * Convertisseurs entre formats
     */
    private function convertToImmobilier(array $actif): array {
        return [
            'designation' => $actif['nature'] ?? $actif['etablissement'] ?? 'Bien immobilier',
            'detenteur' => $actif['detenteur'] ?? null,
            'valeur_actuelle_estimee' => $actif['valeur_actuelle'] ?? null,
        ];
    }

    private function convertToAutreActif(array $actif): array {
        return [
            'designation' => $actif['nature'] ?? 'Autre actif',
            'detenteur' => $actif['detenteur'] ?? null,
            'valeur' => $actif['valeur_actuelle'] ?? null,
        ];
    }

    private function convertToActifFinancier(array $bien): array {
        return [
            'nature' => $bien['designation'] ?? 'actif financier',
            'detenteur' => $bien['detenteur'] ?? null,
            'valeur_actuelle' => $bien['valeur_actuelle_estimee'] ?? null,
        ];
    }

    private function convertToAutreActifFromImmo(array $bien): array {
        return [
            'designation' => $bien['designation'] ?? 'Autre actif',
            'detenteur' => $bien['detenteur'] ?? null,
            'valeur' => $bien['valeur_actuelle_estimee'] ?? null,
        ];
    }

    private function convertToActifFinancierFromAutre(array $autre): array {
        return [
            'nature' => $autre['designation'] ?? 'actif financier',
            'detenteur' => $autre['detenteur'] ?? null,
            'valeur_actuelle' => $autre['valeur'] ?? null,
        ];
    }

    private function convertToImmobilierFromAutre(array $autre): array {
        return [
            'designation' => $autre['designation'] ?? 'Bien immobilier',
            'detenteur' => $autre['detenteur'] ?? null,
            'valeur_actuelle_estimee' => $autre['valeur'] ?? null,
        ];
    }
}
