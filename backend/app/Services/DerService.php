<?php

namespace App\Services;

use App\Models\Client;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;

/**
 * DER Service
 *
 * Gère la génération et l'envoi du Document d'Entrée en Relation
 */
class DerService
{
    /**
     * Générer le DER à partir du template et des données du prospect
     */
    public function generateDer(Client $client, User $chargeClientele): string
    {
        Log::info("📄 Génération du DER pour le client #{$client->id}");

        // 1. Copier le template vers un fichier temporaire
        $templatePath = storage_path('app/templates/template-der.docx');

        if (! file_exists($templatePath)) {
            throw new \Exception("Template DER introuvable : {$templatePath}");
        }

        // Créer un nom de fichier unique pour le document généré
        $filename = 'DER_'.$client->nom.'_'.$client->prenom.'_'.time().'.docx';
        $tempPath = storage_path('app/temp/'.$filename);

        // Créer le dossier temp s'il n'existe pas
        if (! file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        // 2. Charger le template avec PhpWord
        $templateProcessor = new TemplateProcessor($templatePath);

        // 3. Remplacer les variables dynamiques
        $this->replaceVariables($templateProcessor, $client, $chargeClientele);

        // 4. Sauvegarder le document généré
        $templateProcessor->saveAs($tempPath);

        Log::info("✅ DER généré avec succès : {$tempPath}");

        return $tempPath;
    }

    /**
     * Remplacer toutes les variables dans le template
     */
    private function replaceVariables(
        TemplateProcessor $templateProcessor,
        Client $client,
        User $chargeClientele
    ): void {
        // Variables client - avec différentes conventions de nommage
        $templateProcessor->setValue('genre', $client->civilite ?? '');
        $templateProcessor->setValue('civilite', $client->civilite ?? '');
        $templateProcessor->setValue('nom', strtoupper($client->nom ?? ''));
        $templateProcessor->setValue('prenom', ucfirst($client->prenom ?? ''));
        $templateProcessor->setValue('email', $client->email ?? '');

        // Variables rendez-vous
        $templateProcessor->setValue('lieu_rdv', $client->der_lieu_rdv ?? '');
        $templateProcessor->setValue('date_rdv', $this->formatDate($client->der_date_rdv));
        $templateProcessor->setValue('heure_rdv', $this->formatHeure($client->der_heure_rdv));

        // Variables chargé de clientèle (MIA)
        $templateProcessor->setValue('charge_clientele', $chargeClientele->name ?? '');
        $templateProcessor->setValue('charge_clientele_nom', strtoupper($chargeClientele->name ?? ''));
        $templateProcessor->setValue('charge_clientele_email', $chargeClientele->email ?? '');

        // Date du jour
        $templateProcessor->setValue('date_aujourd_hui', Carbon::now()->format('d/m/Y'));

        // Logo du cabinet
        $team = $chargeClientele->currentTeam();
        if ($team && $team->logo_path) {
            try {
                $logoTempPath = $this->downloadLogoToTemp($team->logo_path);
                $templateProcessor->setImageValue('logo_cabinet', [
                    'path' => $logoTempPath,
                    'width' => 150,
                    'height' => 60,
                    'ratio' => true,
                ]);
                @unlink($logoTempPath);
            } catch (\Throwable $e) {
                Log::warning("⚠️ Impossible d'injecter le logo dans le DER : ".$e->getMessage());
                $templateProcessor->setValue('logo_cabinet', '');
            }
        } else {
            $templateProcessor->setValue('logo_cabinet', '');
        }

        Log::info('🔄 Variables remplacées dans le template DER');
    }

    /**
     * Télécharge le logo depuis S3 vers un fichier temporaire local
     */
    private function downloadLogoToTemp(string $s3Path): string
    {
        $content = Storage::disk('s3')->get($s3Path);
        $tempPath = storage_path('app/temp/logo_'.uniqid().'.png');

        if (! file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        file_put_contents($tempPath, $content);

        return $tempPath;
    }

    /**
     * Formater une date pour l'affichage
     */
    private function formatDate(?string $date): string
    {
        if (! $date) {
            return '';
        }

        return Carbon::parse($date)->locale('fr')->isoFormat('dddd D MMMM YYYY');
    }

    /**
     * Formater une heure pour l'affichage
     */
    private function formatHeure(?string $heure): string
    {
        if (! $heure) {
            return '';
        }

        return Carbon::parse($heure)->format('H\hi');
    }

    /**
     * Supprimer le fichier temporaire du DER
     */
    public function cleanupTempFile(string $tempPath): void
    {
        if (file_exists($tempPath)) {
            unlink($tempPath);
            Log::info("🗑️ Fichier temporaire supprimé : {$tempPath}");
        }
    }
}
