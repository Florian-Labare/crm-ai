<?php

namespace App\Services;

use App\Models\Client;
use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentGeneratorService
{
    private DirectTemplateMapper $mapper;

    public function __construct(DirectTemplateMapper $mapper)
    {
        $this->mapper = $mapper;
    }

    /**
     * Génère un document à partir d'un template et des données client
     */
    public function generateDocument(
        Client $client,
        DocumentTemplate $template,
        int $userId,
        string $format = 'docx',
        array $overrides = []
    ): GeneratedDocument {
        // Charger le template depuis le storage
        // Utiliser storage_path directement car les templates sont dans storage/app/templates/
        $templatePath = storage_path('app/' . $template->file_path);

        if (!file_exists($templatePath)) {
            throw new \Exception("Template file not found: {$templatePath}");
        }

        // Créer le processeur de template PHPWord avec délimiteurs {{}}
        $templateProcessor = new CustomTemplateProcessor($templatePath);

        // Extraire les variables du template pour détecter le format utilisé
        $templateVariables = $this->mapper->extractTemplateVariables($templatePath);

        Log::info('Document generation - Template variables extracted', [
            'count' => count($templateVariables),
            'template' => $template->name,
        ]);

        // Mapper avec les DEUX formats (ancien et nouveau) pour compatibilité totale
        $variables = [];

        // 1. Nouveau format (clients.nom, clients.code_postal, etc.) - pour les nouveaux templates
        // On commence par le nouveau format
        $newVariables = $this->mapper->mapVariables($client, $templateVariables);
        $variables = array_merge($variables, $newVariables);

        // 2. Ancien format (nom, codepostal, etc.) - pour les anciens templates
        // On merge en dernier pour que les valeurs legacy écrasent les valeurs vides du nouveau format
        $legacyVariables = $this->mapClientDataToVariables($client);
        // Merger uniquement les clés non vides pour ne pas écraser les valeurs du nouveau format
        foreach ($legacyVariables as $key => $value) {
            // Garder les valeurs legacy si la nouvelle valeur est vide OU si la clé n'existe pas encore
            if (!isset($variables[$key]) || $variables[$key] === '') {
                $variables[$key] = $value;
            }
        }

        Log::info('Document generation - Variables mapped (hybrid)', [
            'legacy_count' => count($legacyVariables),
            'new_count' => count($newVariables),
            'total_count' => count($variables),
            'client_id' => $client->id,
        ]);

        // Appliquer les valeurs saisies par l'utilisateur si disponibles
        if (!empty($overrides)) {
            $variables = array_merge($variables, $overrides);
        }

        Log::info('Document generation - Overrides applied', [
            'template' => $template->name,
            'client_id' => $client->id,
            'overrides_count' => count($overrides),
            'override_keys' => array_keys($overrides),
        ]);

        // Remplacer toutes les variables dans le template
        foreach ($variables as $key => $value) {
            $templateProcessor->setValue($key, $this->normalizeTemplateValue($value));
        }

        // Générer un nom de fichier unique (toujours en .docx d'abord)
        $fileName = $this->generateFileName($client, $template, 'docx');

        // S'assurer que le dossier de sortie existe sur le disque par défaut
        // (ex: storage/app/private/documents si le disque "private" est celui par défaut)
        Storage::makeDirectory('documents');

        $outputPath = Storage::path("documents/{$fileName}");

        // Sauvegarder le document généré
        $templateProcessor->saveAs($outputPath);

        // Si le format demandé est PDF, convertir le DOCX en PDF
        if ($format === 'pdf') {
            $outputPath = $this->convertToPdf($outputPath);
            $fileName = str_replace('.docx', '.pdf', $fileName);
        }

        // Créer l'entrée en base de données
        $generatedDocument = GeneratedDocument::create([
            'client_id' => $client->id,
            'user_id' => $userId,
            'document_template_id' => $template->id,
            'file_path' => "documents/{$fileName}",
            'format' => $format,
        ]);

        return $generatedDocument;
    }

    /**
     * Mappe toutes les données du client aux variables du template
     * Note: Le template utilise des noms sans underscores (ex: datenaissance au lieu de date_naissance)
     */
    private function mapClientDataToVariables(Client $client): array
    {
        // Charger toutes les relations
        $client->load([
            'conjoint',
            'enfants',
            'santeSouhait',
            'baePrevoyance',
            'baeRetraite',
            'baeEpargne',
        ]);

        $variables = [];

        // === INFORMATIONS PRINCIPALES DU CLIENT ===
        $variables['nom'] = $client->nom;
        $variables['nomjeunefille'] = $client->nom_jeune_fille;
        $variables['prenom'] = $client->prenom;
        $variables['datenaissance'] = $this->safeFormatDate($client->date_naissance);
        $variables['lieunaissance'] = $client->lieu_naissance;
        $variables['nationalite'] = $client->nationalite;
        $variables['situationmatrimoniale'] = $client->situation_matrimoniale;
        $variables['Date'] = now()->format('d/m/Y');

        // === SITUATION PROFESSIONNELLE ===
        $variables['situationactuelle'] = $client->situation_actuelle;
        $variables['professionn'] = $client->profession; // Note: template utilise professionn
        $variables['chefentreprisee'] = $client->chef_entreprise ? 'Oui' : 'Non';

        // === COORDONNÉES ===
        $variables['adresse'] = $client->adresse;
        $variables['codepostal'] = $client->code_postal;
        $variables['ville'] = $client->ville;
        $variables['numerotel'] = $client->telephone;
        $variables['email'] = $client->email;

        // === ENFANTS ===
        // Ajouter les variables pour chaque enfant (template utilise datenaissanceenfant11, nomprenomenfant1, etc.)
        for ($i = 1; $i <= 3; $i++) {
            $enfant = $client->enfants->get($i - 1);

            if ($enfant) {
                $variables["nomprenomenfant{$i}"] = $enfant->prenom . ' ' . $enfant->nom;
                $variables['datenaissanceenfant' . ($i == 1 ? '11' : $i)] = $this->safeFormatDate($enfant->date_naissance);
                // Variable fiscalcharge pour le template (fiscalcharge1, fiscalcharge2, fiscalcharge3)
                $variables["fiscalcharge{$i}"] = $enfant->fiscalement_a_charge ? 'Oui' : 'Non';
            } else {
                $variables["nomprenomenfant{$i}"] = '';
                $variables['datenaissanceenfant' . ($i == 1 ? '11' : $i)] = '';
                $variables["fiscalcharge{$i}"] = '';
            }
        }

        // === CONJOINT ===
        $conjoint = $client->conjoint;
        if ($conjoint) {
            // Template utilise nomconjoint, prenomconjoint, etc. (sans underscore, champ en premier)
            $variables['nomconjoint'] = $conjoint->nom;
            $variables['nomjeunefilleconjoint'] = $conjoint->nom_jeune_fille;
            $variables['prenomconjoint'] = $conjoint->prenom;
            $variables['datenaissanceconjoint'] = $this->safeFormatDate($conjoint->date_naissance);
            $variables['lieunaissanceconjoint'] = $conjoint->lieu_naissance;
            $variables['nationaliteconjoint'] = $conjoint->nationalite;
            $variables['professionconjointnn'] = $conjoint->profession;
            $variables['chefentrepriseconjoint'] = $conjoint->chef_entreprise ? 'Oui' : 'Non';
            $variables['adresseconjoint'] = $conjoint->adresse;
            $variables['codepostalconjoint'] = $conjoint->code_postal;
            $variables['villeconjoint'] = $conjoint->ville;
            $variables['actuelleconjointsituation'] = $conjoint->situation_actuelle_statut;
        } else {
            $variables['nomconjoint'] = '';
            $variables['nomjeunefilleconjoint'] = '';
            $variables['prenomconjoint'] = '';
            $variables['datenaissanceconjoint'] = '';
            $variables['lieunaissanceconjoint'] = '';
            $variables['nationaliteconjoint'] = '';
            $variables['professionconjointnn'] = '';
            $variables['chefentrepriseconjoint'] = '';
            $variables['adresseconjoint'] = '';
            $variables['codepostalconjoint'] = '';
            $variables['villeconjoint'] = '';
            $variables['actuelleconjointsituation'] = '';
        }

        // === BAE RETRAITE (mapping simplifié pour les champs principaux du template) ===
        $retraite = $client->baeRetraite;
        if ($retraite) {
            $variables['ageretraitedepart'] = $retraite->age_depart_retraite;
            $variables['ageretraitedepartconjoint'] = $retraite->age_depart_retraite_conjoint;
            $variables['siretraiteconjoint'] = $retraite->age_depart_retraite_conjoint ? 'Oui' : 'Non';
            $variables['bilanretraitee'] = $retraite->bilan_retraite_disponible ? 'Oui' : 'Non';
            $variables['contratenplacereraite'] = $retraite->contrat_en_place;
            $variables['complementaireretrairte'] = $retraite->complementaire_retraite_mise_en_place ? 'Oui' : 'Non';
        }

        // === BAE ÉPARGNE (mapping simplifié) ===
        $epargne = $client->baeEpargne;
        if ($epargne) {
            $variables['capaciteepargeestimeee'] = $epargne->capacite_epargne_estimee;
            $variables['totalpatrimoinefinancier'] = $epargne->actifs_financiers_total;
            $variables['totalpatrimoineimmo'] = $epargne->actifs_immo_total;
            $variables['totalcharges'] = $epargne->charges_totales;
            $variables['donationdate'] = $epargne->donation_date;
            $variables['donationmontant'] = $epargne->donation_montant;
            $variables['donationbeneficiaire'] = $epargne->donation_beneficiaires;
            $variables['donationforme'] = $epargne->donation_forme;
        }

        // Variables fiscales
        if ($retraite) {
            $variables['revenuannuelfiscal'] = $retraite->revenus_annuels;
            $variables['impotrevenunbpart'] = $retraite->nombre_parts_fiscales;
            $variables['impotrevenunmoins1'] = $retraite->impot_paye_n_1;
            $variables['impotrevenutmi'] = $retraite->tmi;
        }

        // Ajouter les champs parent pour enfants
        $variables['parents'] = $client->prenom . ' ' . $client->nom;
        if ($conjoint) {
            $variables['parents'] .= ' et ' . $conjoint->prenom . ' ' . $conjoint->nom;
        }

        return $variables;
    }

    /**
     * Génère un nom de fichier unique pour le document
     */
    private function generateFileName(Client $client, DocumentTemplate $template, string $format): string
    {
        $timestamp = now()->format('Ymd_His');
        $clientName = Str::slug($client->nom . '_' . $client->prenom, '_');
        $templateSlug = Str::slug($template->name, '_');

        return "{$clientName}_{$templateSlug}_{$timestamp}.{$format}";
    }

    /**
     * Parse une date de façon sécurisée (gère les formats partiels comme "1961-XX-XX")
     */
    private function safeFormatDate(?string $date): string
    {
        if (empty($date)) {
            return '';
        }

        // Vérifier si la date contient des caractères invalides (XX, ??, etc.)
        if (preg_match('/[Xx?]+/', $date)) {
            // Retourner la date telle quelle ou une version nettoyée
            return $date;
        }

        try {
            return \Carbon\Carbon::parse($date)->format('d/m/Y');
        } catch (\Exception $e) {
            // Si la date ne peut pas être parsée, la retourner telle quelle
            return $date;
        }
    }

    /**
     * Normalise une valeur pour PhpWord TemplateProcessor::setValue (qui attend du texte).
     */
    private function normalizeTemplateValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Oui' : 'Non';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y');
        }

        if (is_array($value)) {
            $parts = [];

            $iterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($value));
            foreach ($iterator as $item) {
                $parts[] = $this->normalizeTemplateValue($item);
            }

            return implode("\n", array_filter($parts, static fn($p) => $p !== ''));
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE) ?: (string) $value;
    }

    /**
     * Convertit un fichier DOCX en PDF via Gotenberg
     *
     * @param string $docxPath Chemin complet vers le fichier DOCX
     * @return string Chemin complet vers le fichier PDF généré
     * @throws \Exception Si la conversion échoue
     */
    private function convertToPdf(string $docxPath): string
    {
        try {
            // URL de Gotenberg (service Docker)
            $gotenbergUrl = 'http://gotenberg:3000/forms/libreoffice/convert';

            // Vérifier que le fichier existe et a une taille non-nulle
            if (!file_exists($docxPath)) {
                throw new \Exception("DOCX file not found: {$docxPath}");
            }

            // Force PHP to refresh file stats
            clearstatcache(true, $docxPath);

            $fileSize = filesize($docxPath);
            if ($fileSize === 0) {
                throw new \Exception("DOCX file is empty: {$docxPath}");
            }

            Log::info('DOCX file ready for conversion', [
                'docx_path' => $docxPath,
                'file_size' => $fileSize,
            ]);

            // Créer un client HTTP Guzzle
            $httpClient = new HttpClient([
                'timeout' => 60, // Timeout de 60 secondes pour la conversion
            ]);

            // Lire le contenu du fichier en mémoire (plus fiable que fopen)
            $docxContents = file_get_contents($docxPath);

            // Préparer le fichier DOCX pour l'upload
            $multipart = [
                [
                    'name' => 'files',
                    'contents' => $docxContents,
                    'filename' => basename($docxPath),
                ],
            ];

            Log::info('Converting DOCX to PDF via Gotenberg', [
                'docx_path' => $docxPath,
                'docx_size' => strlen($docxContents),
                'gotenberg_url' => $gotenbergUrl,
            ]);

            // Envoyer la requête à Gotenberg
            $response = $httpClient->post($gotenbergUrl, [
                'multipart' => $multipart,
            ]);

            // Vérifier le statut de la réponse
            if ($response->getStatusCode() !== 200) {
                throw new \Exception('Gotenberg conversion failed with status: ' . $response->getStatusCode());
            }

            // Générer le chemin du fichier PDF (même nom mais extension .pdf)
            $pdfPath = str_replace('.docx', '.pdf', $docxPath);

            // Sauvegarder le PDF
            file_put_contents($pdfPath, $response->getBody()->getContents());

            Log::info('PDF conversion successful', [
                'pdf_path' => $pdfPath,
                'pdf_size' => filesize($pdfPath),
            ]);

            // Supprimer le fichier DOCX temporaire (optionnel)
            if (file_exists($docxPath)) {
                unlink($docxPath);
            }

            return $pdfPath;

        } catch (\Exception $e) {
            Log::error('PDF conversion failed', [
                'error' => $e->getMessage(),
                'docx_path' => $docxPath,
            ]);

            throw new \Exception('Failed to convert document to PDF: ' . $e->getMessage());
        }
    }
}
