<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Font;

class ExportController extends Controller
{
    /**
     * Exporter la fiche client en PDF
     */
    public function exportPdf($id)
    {
        $client = Client::with(['conjoint', 'enfants', 'entreprise', 'santeSouhait'])->findOrFail($id);

        $pdf = Pdf::loadView('exports.client-pdf', [
            'client' => $client
        ]);

        $filename = 'fiche_client_' . $client->nom . '_' . $client->prenom . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Exporter la fiche client en Word
     */
    public function exportWord($id)
    {
        $client = Client::with(['conjoint', 'enfants', 'entreprise', 'santeSouhait'])->findOrFail($id);

        $phpWord = new PhpWord();

        // Configuration du document
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(11);

        // Création de la section
        $section = $phpWord->addSection([
            'marginTop' => 1000,
            'marginBottom' => 1000,
            'marginLeft' => 1200,
            'marginRight' => 1200,
        ]);

        // Styles
        $titleStyle = ['bold' => true, 'size' => 16, 'color' => '4F46E5'];
        $heading1Style = ['bold' => true, 'size' => 14, 'color' => '312E81'];
        $heading2Style = ['bold' => true, 'size' => 12, 'color' => '6366F1'];
        $labelStyle = ['bold' => true, 'size' => 11, 'color' => '374151'];
        $textStyle = ['size' => 11];

        // Titre du document
        $section->addText(
            '🎧 WHISPER CRM - FICHE CLIENT',
            $titleStyle,
            ['alignment' => Jc::CENTER, 'spaceAfter' => 300]
        );

        // Identité
        $section->addText('IDENTITÉ', $heading1Style, ['spaceAfter' => 200, 'spaceBefore' => 400]);
        $this->addField($section, 'Civilité', $client->civilite, $labelStyle, $textStyle);
        $this->addField($section, 'Nom', $client->nom, $labelStyle, $textStyle);
        if ($client->nom_jeune_fille) {
            $this->addField($section, 'Nom de jeune fille', $client->nom_jeune_fille, $labelStyle, $textStyle);
        }
        $this->addField($section, 'Prénom', $client->prenom, $labelStyle, $textStyle);
        $this->addField($section, 'Date de naissance', $client->datedenaissance ? \Carbon\Carbon::parse($client->datedenaissance)->format('d/m/Y') : null, $labelStyle, $textStyle);
        $this->addField($section, 'Lieu de naissance', $client->lieudenaissance, $labelStyle, $textStyle);
        $this->addField($section, 'Nationalité', $client->nationalite, $labelStyle, $textStyle);

        // Coordonnées
        $section->addText('COORDONNÉES', $heading1Style, ['spaceAfter' => 200, 'spaceBefore' => 400]);
        $this->addField($section, 'Adresse', $client->adresse, $labelStyle, $textStyle);
        $this->addField($section, 'Code postal', $client->code_postal, $labelStyle, $textStyle);
        $this->addField($section, 'Ville', $client->ville, $labelStyle, $textStyle);
        $this->addField($section, 'Résidence fiscale', $client->residence_fiscale, $labelStyle, $textStyle);
        $this->addField($section, 'Téléphone', $client->telephone, $labelStyle, $textStyle);
        $this->addField($section, 'Email', $client->email, $labelStyle, $textStyle);

        // Situation
        $section->addText('SITUATION PERSONNELLE', $heading1Style, ['spaceAfter' => 200, 'spaceBefore' => 400]);
        $this->addField($section, 'Situation matrimoniale', $client->situationmatrimoniale, $labelStyle, $textStyle);
        $this->addField($section, 'Date situation matrimoniale', $client->date_situation_matrimoniale ? \Carbon\Carbon::parse($client->date_situation_matrimoniale)->format('d/m/Y') : null, $labelStyle, $textStyle);
        $this->addField($section, 'Situation actuelle', $client->situation_actuelle, $labelStyle, $textStyle);
        $this->addField($section, 'Nombre d\'enfants', $client->nombreenfants, $labelStyle, $textStyle);

        // Conjoint
        if ($client->conjoint) {
            $section->addText('CONJOINT', $heading2Style, ['spaceAfter' => 200, 'spaceBefore' => 300]);
            $this->addField($section, 'Nom', $client->conjoint->nom, $labelStyle, $textStyle);
            $this->addField($section, 'Prénom', $client->conjoint->prenom, $labelStyle, $textStyle);
            $this->addField($section, 'Date de naissance', $client->conjoint->datedenaissance ? \Carbon\Carbon::parse($client->conjoint->datedenaissance)->format('d/m/Y') : null, $labelStyle, $textStyle);
        }

        // Enfants
        if ($client->enfants && $client->enfants->count() > 0) {
            $section->addText('ENFANTS', $heading2Style, ['spaceAfter' => 200, 'spaceBefore' => 300]);
            foreach ($client->enfants as $index => $enfant) {
                $section->addText('Enfant ' . ($index + 1), ['bold' => true, 'size' => 11], ['spaceAfter' => 100]);
                $this->addField($section, 'Prénom', $enfant->prenom, $labelStyle, $textStyle);
                $this->addField($section, 'Date de naissance', $enfant->datedenaissance ? \Carbon\Carbon::parse($enfant->datedenaissance)->format('d/m/Y') : null, $labelStyle, $textStyle);
            }
        }

        // Professionnel
        $section->addText('SITUATION PROFESSIONNELLE', $heading1Style, ['spaceAfter' => 200, 'spaceBefore' => 400]);
        $this->addField($section, 'Profession', $client->profession, $labelStyle, $textStyle);
        $this->addField($section, 'Date événement professionnel', $client->date_evenement_professionnel ? \Carbon\Carbon::parse($client->date_evenement_professionnel)->format('d/m/Y') : null, $labelStyle, $textStyle);
        $this->addField($section, 'Revenus annuels', $client->revenusannuels ? number_format($client->revenusannuels, 0, ',', ' ') . ' €' : null, $labelStyle, $textStyle);
        $this->addField($section, 'Risques professionnels', $client->risques_professionnels ? 'Oui' : 'Non', $labelStyle, $textStyle);
        $this->addField($section, 'Détails risques', $client->details_risques_professionnels, $labelStyle, $textStyle);
        $this->addField($section, 'Charge clientèle', $client->charge_clientele, $labelStyle, $textStyle);

        // Entreprise
        if ($client->entreprise) {
            $section->addText('ENTREPRISE', $heading2Style, ['spaceAfter' => 200, 'spaceBefore' => 300]);
            $this->addField($section, 'Nom', $client->entreprise->nom, $labelStyle, $textStyle);
            $this->addField($section, 'Forme juridique', $client->entreprise->forme_juridique, $labelStyle, $textStyle);
            $this->addField($section, 'SIRET', $client->entreprise->siret, $labelStyle, $textStyle);
        }

        // Mode de vie
        $section->addText('MODE DE VIE', $heading1Style, ['spaceAfter' => 200, 'spaceBefore' => 400]);
        $this->addField($section, 'Fumeur', $client->fumeur ? 'Oui' : 'Non', $labelStyle, $textStyle);
        $this->addField($section, 'Activités sportives', $client->activites_sportives ? 'Oui' : 'Non', $labelStyle, $textStyle);
        $this->addField($section, 'Détails activités', $client->details_activites_sportives, $labelStyle, $textStyle);
        $this->addField($section, 'Niveau activités', $client->niveau_activites_sportives, $labelStyle, $textStyle);

        // Besoins
        if ($client->besoins && is_array($client->besoins) && count($client->besoins) > 0) {
            $section->addText('BESOINS IDENTIFIÉS', $heading1Style, ['spaceAfter' => 200, 'spaceBefore' => 400]);
            foreach ($client->besoins as $besoin) {
                $section->addListItem($besoin, 0, null, ['spaceAfter' => 100]);
            }
        }

        // Santé et souhaits
        if ($client->santeSouhait) {
            $section->addText('SANTÉ ET SOUHAITS', $heading1Style, ['spaceAfter' => 200, 'spaceBefore' => 400]);
            $sante = $client->santeSouhait;
            if ($sante->contrat_en_place) {
                $this->addField($section, 'Contrat en place', $sante->contrat_en_place, $labelStyle, $textStyle);
            }
            if ($sante->budget_mensuel_maximum) {
                $this->addField($section, 'Budget mensuel maximum', $sante->budget_mensuel_maximum . ' €', $labelStyle, $textStyle);
            }
        }

        // Notes
        if ($client->notes) {
            $section->addText('NOTES', $heading1Style, ['spaceAfter' => 200, 'spaceBefore' => 400]);
            $section->addText($client->notes, $textStyle);
        }

        // Pied de page
        $section->addText(
            '_______________________________________________',
            ['size' => 10, 'color' => 'CCCCCC'],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 100, 'spaceBefore' => 400]
        );
        $section->addText(
            'Document généré le ' . now()->format('d/m/Y à H:i') . ' par Whisper CRM',
            ['size' => 9, 'color' => '6B7280'],
            ['alignment' => Jc::CENTER]
        );

        // Génération du fichier
        $filename = 'fiche_client_' . $client->nom . '_' . $client->prenom . '.docx';
        $tempFile = storage_path('app/temp/' . $filename);

        // Créer le dossier temp s'il n'existe pas
        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($tempFile);

        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Ajouter un champ au document Word
     */
    private function addField($section, $label, $value, $labelStyle, $textStyle)
    {
        if ($value !== null && $value !== '') {
            $section->addText($label . ' : ' . $value, array_merge($textStyle, $labelStyle), ['spaceAfter' => 100]);
        }
    }
}
