<?php

namespace App\Services;

use App\Models\Client;
use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
        string $format = 'docx'
    ): GeneratedDocument {
        // Charger le template depuis le storage
        // Utiliser storage_path directement car les templates sont dans storage/app/templates/
        $templatePath = storage_path('app/' . $template->file_path);

        if (! file_exists($templatePath)) {
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

        // 1. Ancien format (nom, codepostal, etc.) - pour les anciens templates
        $legacyVariables = $this->mapClientDataToVariables($client);
        $variables = array_merge($variables, $legacyVariables);

        // 2. Nouveau format (clients.nom, clients.code_postal, etc.) - pour les nouveaux templates
        $newVariables = $this->mapper->mapVariables($client, $templateVariables);
        $variables = array_merge($variables, $newVariables);

        Log::info('Document generation - Variables mapped (hybrid)', [
            'legacy_count' => count($legacyVariables),
            'new_count' => count($newVariables),
            'total_count' => count($variables),
            'client_id' => $client->id,
        ]);

        // Remplacer toutes les variables dans le template
        foreach ($variables as $key => $value) {
            $templateProcessor->setValue($key, $value);
        }

        // Générer un nom de fichier unique (toujours en .docx d'abord)
        $fileName = $this->generateFileName($client, $template, 'docx');
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
        $variables['datenaissance'] = $client->date_naissance ? \Carbon\Carbon::parse($client->date_naissance)->format('d/m/Y') : '';
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
                $variables["nomprenomenfant{$i}"] = $enfant->prenom.' '.$enfant->nom;
                $variables['datenaissanceenfant'.($i == 1 ? '11' : $i)] = $enfant->date_naissance ? \Carbon\Carbon::parse($enfant->date_naissance)->format('d/m/Y') : '';
            } else {
                $variables["nomprenomenfant{$i}"] = '';
                $variables['datenaissanceenfant'.($i == 1 ? '11' : $i)] = '';
            }
        }

        // === CONJOINT ===
        $conjoint = $client->conjoint;
        if ($conjoint) {
            // Template utilise nomconjoint, prenomconjoint, etc. (sans underscore, champ en premier)
            $variables['nomconjoint'] = $conjoint->nom;
            $variables['nomjeunefilleconjoint'] = $conjoint->nom_jeune_fille;
            $variables['prenomconjoint'] = $conjoint->prenom;
            $variables['datenaissanceconjoint'] = $conjoint->date_naissance ? \Carbon\Carbon::parse($conjoint->date_naissance)->format('d/m/Y') : '';
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
        $variables['parents'] = $client->prenom.' '.$client->nom;
        if ($conjoint) {
            $variables['parents'] .= ' et '.$conjoint->prenom.' '.$conjoint->nom;
        }

        return $variables;
    }

    /**
     * Génère un nom de fichier unique pour le document
     */
    private function generateFileName(Client $client, DocumentTemplate $template, string $format): string
    {
        $timestamp = now()->format('Ymd_His');
        $clientName = str_replace(' ', '_', $client->nom.'_'.$client->prenom);
        $templateSlug = str_replace(' ', '_', strtolower($template->name));

        return "{$clientName}_{$templateSlug}_{$timestamp}.{$format}";
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
                throw new \Exception('Gotenberg conversion failed with status: '.$response->getStatusCode());
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

            throw new \Exception('Failed to convert document to PDF: '.$e->getMessage());
        }
    }
}
