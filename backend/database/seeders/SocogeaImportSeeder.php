<?php

namespace Database\Seeders;

use App\Models\Assureur;
use App\Models\Client;
use App\Models\ClientContrat;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Import des clients SOCOGEA depuis les bordereaux de commissions Alptis (mars 2026).
 *
 * Source : Bordereau-Commissions-746115.pdf (Alptis Assurances, code partenaire P11074/14347)
 * Cabinet : STE SOCOGEA — 19 BD EUGÈNE SPULLER, 21000 DIJON
 *
 * Règle métier : un numéro de contrat présent = client (is_client = true), plus un prospect.
 */
class SocogeaImportSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🏢 Import SOCOGEA — Alptis bordereaux commissions');

        // 1. Assureur Alptis
        $alptis = Assureur::firstOrCreate(
            ['nom' => 'Alptis Assurances'],
            ['lien_espace_client' => 'https://ro.alptis.org']
        );
        $this->command->info("✓ Assureur : {$alptis->nom}");

        // 2. Team SOCOGEA (utilise la team existante ou en crée une)
        $team = Team::first();
        if (! $team) {
            $adminUser = User::firstOrCreate(
                ['email' => 'admin@socogea.fr'],
                [
                    'name' => 'SOCOGEA',
                    'firstname' => 'Admin',
                    'password' => Hash::make('changeme'),
                    'email_verified_at' => now(),
                ]
            );
            $team = Team::create([
                'user_id' => $adminUser->id,
                'name' => 'SOCOGEA',
                'personal_team' => true,
            ]);
        }
        $this->command->info("✓ Team : {$team->name} (id={$team->id})");

        // 3. Utilisateur MIA (chargé de clientèle SOCOGEA)
        $miaRole = Role::firstOrCreate(['name' => 'MIA'], ['guard_name' => 'web']);

        $mia = User::firstOrCreate(
            ['email' => 'mia@socogea.fr'],
            [
                'name' => 'SOCOGEA',
                'firstname' => 'Courtier',
                'password' => Hash::make('changeme'),
                'email_verified_at' => now(),
            ]
        );
        if (! $mia->hasRole('MIA')) {
            $mia->assignRole($miaRole);
        }
        if (! $mia->teams()->where('teams.id', $team->id)->exists()) {
            $mia->teams()->attach($team->id, ['role' => 'member']);
        }
        $this->command->info("✓ MIA : {$mia->firstname} {$mia->name} ({$mia->email})");

        // 4. Import des clients — données extraites du bordereau Alptis (ALP28756180)
        $clients = $this->getClientsAlptis();

        $created = 0;
        $skipped = 0;

        foreach ($clients as $data) {
            // Recherche par numéro de contrat Alptis existant
            $existingContrat = ClientContrat::where('numero_contrat', $data['numero_adherent'])
                ->where('assureur_id', $alptis->id)
                ->first();

            if ($existingContrat) {
                $skipped++;

                continue;
            }

            // Créer le client
            $client = Client::create([
                'team_id' => $team->id,
                'user_id' => $mia->id,
                'nom' => $data['nom'],
                'prenom' => $data['prenom'],
                'is_client' => true,  // numéro de contrat présent = client, pas prospect
                'is_archived' => false,
            ]);

            // Créer le(s) contrat(s)
            foreach ($data['contrats'] as $contrat) {
                ClientContrat::create([
                    'client_id' => $client->id,
                    'type' => $contrat['type'],
                    'assureur_id' => $alptis->id,
                    'numero_contrat' => $data['numero_adherent'],
                    'produit' => $contrat['produit'],
                ]);
            }

            $created++;
        }

        $this->command->info("✅ {$created} clients créés, {$skipped} déjà présents");
        $this->command->info('Import SOCOGEA/Alptis terminé.');
    }

    /**
     * Données extraites du bordereau Alptis ALP28756180 (commissions mars 2026).
     * Numéro adhérent = numéro de contrat Alptis (source de vérité).
     */
    private function getClientsAlptis(): array
    {
        return [
            // --- SANTÉ ---
            ['numero_adherent' => '743924',  'nom' => 'ROLLOT',               'prenom' => 'Jean-Marie',       'contrats' => [['type' => 'sante',      'produit' => 'Plurielle niveau 4']]],
            ['numero_adherent' => '752848',  'nom' => 'LUCAS',                'prenom' => 'Irma',             'contrats' => [['type' => 'sante',      'produit' => 'Angeva niveau 10']]],
            ['numero_adherent' => '785255',  'nom' => 'DENNY',                'prenom' => 'Marc',             'contrats' => [['type' => 'sante',      'produit' => 'Select Séniors FC Niveau 4']]],
            ['numero_adherent' => '806374',  'nom' => 'HUOT-MARCHAND',        'prenom' => 'Claire',           'contrats' => [['type' => 'sante',      'produit' => 'Medico 100/150 solid']]],
            ['numero_adherent' => '816375',  'nom' => 'DEVILLIERS',           'prenom' => 'Elisabeth',        'contrats' => [['type' => 'sante',      'produit' => 'Medico 100/150 solid']]],
            ['numero_adherent' => '836996',  'nom' => 'MULLER',               'prenom' => 'Thierry',          'contrats' => [['type' => 'sante',      'produit' => 'Angeva Plus niveau 4']]],
            ['numero_adherent' => '1706334', 'nom' => 'BLINEAU',              'prenom' => 'Irene',            'contrats' => [['type' => 'sante',      'produit' => 'Plurielle niveau 2']]],
            ['numero_adherent' => '1854434', 'nom' => 'BERGUICES',            'prenom' => 'Daniele',          'contrats' => [['type' => 'sante',      'produit' => 'Plurielle niveau 3']]],
            ['numero_adherent' => '1878653', 'nom' => 'LEFEUVRE',             'prenom' => 'Nicole',           'contrats' => [['type' => 'sante',      'produit' => 'Plurielle niveau 3']]],
            ['numero_adherent' => '1879329', 'nom' => 'LESAINT',              'prenom' => 'Michel-Joseph',    'contrats' => [['type' => 'sante',      'produit' => 'Plurielle niveau 1']]],
            ['numero_adherent' => '1884320', 'nom' => 'MORVAN',               'prenom' => 'Roger',            'contrats' => [['type' => 'sante',      'produit' => 'Plurielle niveau 3']]],
            ['numero_adherent' => '1989192', 'nom' => 'LEMIRE',               'prenom' => 'Sylvie',           'contrats' => [['type' => 'sante',      'produit' => 'Plurielle niveau 3']]],
            ['numero_adherent' => '2046717', 'nom' => 'GISSELAIRE',           'prenom' => 'Patricia',         'contrats' => [['type' => 'sante',      'produit' => 'Luminéis niveau 2']]],
            ['numero_adherent' => '2046927', 'nom' => 'MARTINEZ',             'prenom' => 'Christian',        'contrats' => [['type' => 'sante',      'produit' => 'Luminéis niveau 2']]],
            ['numero_adherent' => '2287870', 'nom' => 'CHARBONNIER',          'prenom' => 'Thomas',           'contrats' => [['type' => 'sante',      'produit' => 'Select Pro FC Modulaire']]],
            ['numero_adherent' => '2351347', 'nom' => 'GALOPIN',              'prenom' => 'Serge',            'contrats' => [['type' => 'sante',      'produit' => 'Select Séniors FC Modulaire']]],
            ['numero_adherent' => '2360056', 'nom' => 'SENS',                 'prenom' => 'Evelyne',          'contrats' => [['type' => 'sante',      'produit' => 'Luminéis niveau 4']]],
            ['numero_adherent' => '2363777', 'nom' => 'HEROZ',                'prenom' => 'Monique',          'contrats' => [['type' => 'sante',      'produit' => 'Select Séniors FC Modulaire']]],
            ['numero_adherent' => '2386570', 'nom' => 'FRANCESCON',           'prenom' => 'Elisabeth',        'contrats' => [['type' => 'sante',      'produit' => 'Select Séniors FC Modulaire']]],
            ['numero_adherent' => '2387056', 'nom' => 'JOUVENCEAU',           'prenom' => 'Dominique',        'contrats' => [['type' => 'sante',      'produit' => 'Select Séniors FC Modulaire']]],
            ['numero_adherent' => '2394169', 'nom' => 'NAGELEISEN',           'prenom' => 'Annie',            'contrats' => [['type' => 'sante',      'produit' => 'Select Séniors FC Niveau 5']]],
            ['numero_adherent' => '2409321', 'nom' => 'LABBE',                'prenom' => 'Aime',             'contrats' => [['type' => 'sante',      'produit' => 'Select Séniors F1-5']]],
            ['numero_adherent' => '2413743', 'nom' => 'DUMONTET',             'prenom' => 'Sylvie',           'contrats' => [['type' => 'sante',      'produit' => 'Select Séniors FC Niveau 5']]],
            ['numero_adherent' => '2415221', 'nom' => 'STANDAERT',            'prenom' => 'Cindy',            'contrats' => [['type' => 'sante',      'produit' => 'Santé Pro+ Niveau 2']]],
            ['numero_adherent' => '2418804', 'nom' => 'CHAUSSIN',             'prenom' => 'Marc',             'contrats' => [['type' => 'sante',      'produit' => 'Select Séniors FC Modulaire']]],
            ['numero_adherent' => '2419810', 'nom' => 'GUINOT',               'prenom' => 'Patricia',         'contrats' => [['type' => 'sante',      'produit' => 'Select Séniors FC Niveau 3']]],
            ['numero_adherent' => '2420044', 'nom' => 'FAUVET',               'prenom' => 'Josette',          'contrats' => [['type' => 'sante',      'produit' => 'Select Séniors FC Niveau 6']]],
            ['numero_adherent' => '2421137', 'nom' => 'SHELLEY',              'prenom' => 'Anne-Marie',       'contrats' => [['type' => 'sante',      'produit' => 'Select Séniors FC Modulaire']]],
            ['numero_adherent' => '2421153', 'nom' => 'FLACELIERE',           'prenom' => 'Emmanuel',         'contrats' => [['type' => 'sante',      'produit' => 'Select Séniors FC Niveau 3']]],
            ['numero_adherent' => '2422902', 'nom' => 'TAUZIER',              'prenom' => 'Camille',          'contrats' => [['type' => 'sante',      'produit' => 'Select Pro FC Modulaire']]],
            ['numero_adherent' => '2423029', 'nom' => 'MICHAUD',              'prenom' => 'Jean-Marc',        'contrats' => [['type' => 'sante',      'produit' => 'Select Séniors FC Niveau 3']]],
            ['numero_adherent' => '2424058', 'nom' => 'MILLERON',             'prenom' => 'Emerick',          'contrats' => [['type' => 'sante',      'produit' => 'Luminéis niveau 2']]],
            ['numero_adherent' => '2425430', 'nom' => 'MUNCH',                'prenom' => 'Stephanie',        'contrats' => [['type' => 'sante',      'produit' => 'Luminéis niveau 3']]],
            ['numero_adherent' => '2425645', 'nom' => 'GILLOT',               'prenom' => 'Catherine',        'contrats' => [['type' => 'sante',      'produit' => 'Select Pro FC Niveau 4']]],
            ['numero_adherent' => '2425927', 'nom' => 'COURSELLE',            'prenom' => 'Francis',          'contrats' => [['type' => 'sante',      'produit' => 'Select Séniors FC Niveau 4']]],
            ['numero_adherent' => '2431596', 'nom' => 'HADYNIAK',             'prenom' => 'Stephanie',        'contrats' => [['type' => 'sante',      'produit' => 'Select Séniors FC Niveau 2']]],
            ['numero_adherent' => '2432330', 'nom' => 'JANEAU',               'prenom' => 'Gerard',           'contrats' => [['type' => 'sante',      'produit' => 'Select Séniors FC Niveau 5']]],
            ['numero_adherent' => '2432331', 'nom' => 'NICOLAS',              'prenom' => 'Colette',          'contrats' => [['type' => 'sante',      'produit' => 'Select Séniors FC Niveau 4']]],
            ['numero_adherent' => '2432471', 'nom' => 'LESPINE BASSEREAU',    'prenom' => 'Isabelle',         'contrats' => [['type' => 'sante',      'produit' => 'Select Séniors FC Modulaire']]],
            ['numero_adherent' => '2432696', 'nom' => 'BESANCENOT',           'prenom' => 'Caroline',         'contrats' => [['type' => 'sante',      'produit' => 'Santé Frontaliers suisses']]],
            ['numero_adherent' => '2432855', 'nom' => 'GARCIA',               'prenom' => 'Maellys',          'contrats' => [['type' => 'sante',      'produit' => 'Select Pro FC Niveau 2']]],
            ['numero_adherent' => '2435478', 'nom' => 'BOURSOT',              'prenom' => 'Claire',           'contrats' => [['type' => 'sante',      'produit' => 'Select Pro FC Niveau 3']]],
            ['numero_adherent' => '2443412', 'nom' => 'BONTEMS',              'prenom' => 'Leo',              'contrats' => [['type' => 'sante',      'produit' => 'Luminéis niveau 1']]],

            // --- PRÉVOYANCE ---
            ['numero_adherent' => '802892',  'nom' => 'SCHWARB',              'prenom' => 'Pierre',           'contrats' => [['type' => 'prevoyance', 'produit' => 'Protection accident']]],
            ['numero_adherent' => '858858',  'nom' => 'PIETRI',               'prenom' => 'Veronique',        'contrats' => [['type' => 'prevoyance', 'produit' => 'NRP 9A']]],
            ['numero_adherent' => '862985',  'nom' => 'SAINT GILLES LANSARD', 'prenom' => 'Sandra',           'contrats' => [['type' => 'prevoyance', 'produit' => 'NRP 9A']]],
            ['numero_adherent' => '1055100', 'nom' => 'LANGLADE',             'prenom' => 'Pierre',           'contrats' => [['type' => 'prevoyance', 'produit' => 'Frais généraux']]],
            ['numero_adherent' => '1493028', 'nom' => 'PERROT',               'prenom' => 'Michael',          'contrats' => [['type' => 'prevoyance', 'produit' => 'SPI-V2']]],
            ['numero_adherent' => '1693218', 'nom' => 'BARTOLINI',            'prenom' => 'Myriam',           'contrats' => [['type' => 'prevoyance', 'produit' => 'Décès Plus']]],
            ['numero_adherent' => '1723067', 'nom' => 'SIMARD',               'prenom' => 'Martine',          'contrats' => [['type' => 'prevoyance', 'produit' => 'SPI-V3 M/P']]],
            ['numero_adherent' => '1748157', 'nom' => 'BEAUX',                'prenom' => 'Florian',          'contrats' => [['type' => 'prevoyance', 'produit' => 'SPI-V3 M/P']]],
            ['numero_adherent' => '1754277', 'nom' => 'PAVELOT',              'prenom' => 'Lise',             'contrats' => [['type' => 'prevoyance', 'produit' => 'SPI-V3']]],
            ['numero_adherent' => '1754725', 'nom' => 'PAVELOT',              'prenom' => 'Luc',              'contrats' => [['type' => 'prevoyance', 'produit' => 'SPI-V3']]],
            ['numero_adherent' => '1756496', 'nom' => 'MANAT',                'prenom' => 'Thibault-Louis',   'contrats' => [['type' => 'prevoyance', 'produit' => 'SPI-V3']]],
            ['numero_adherent' => '1759665', 'nom' => 'BURGUET',              'prenom' => 'Jean-Luc',         'contrats' => [['type' => 'prevoyance', 'produit' => 'SPI-V3']]],
            ['numero_adherent' => '1764665', 'nom' => 'GRAS',                 'prenom' => 'Pierre',           'contrats' => [['type' => 'prevoyance', 'produit' => 'SPI-V3 M/P']]],
            ['numero_adherent' => '1767128', 'nom' => 'LACAILLE',             'prenom' => 'Thibaut',          'contrats' => [['type' => 'prevoyance', 'produit' => 'SPI-V3']]],
            ['numero_adherent' => '1769537', 'nom' => 'MEYER',                'prenom' => 'Nicolas',          'contrats' => [['type' => 'prevoyance', 'produit' => 'SPI-V3 M/P']]],
            ['numero_adherent' => '1771081', 'nom' => 'PEREZ',                'prenom' => 'Jonathan',         'contrats' => [['type' => 'prevoyance', 'produit' => 'SPI-V3']]],
            ['numero_adherent' => '1777155', 'nom' => 'LEVEZ',                'prenom' => 'Diane',            'contrats' => [['type' => 'prevoyance', 'produit' => 'SPI-V3']]],
            ['numero_adherent' => '1806728', 'nom' => 'LECLERCQ',             'prenom' => 'Rodolphe',         'contrats' => [['type' => 'prevoyance', 'produit' => 'SPI-V3']]],
            ['numero_adherent' => '1811068', 'nom' => 'THIBAUT',              'prenom' => 'Eric',             'contrats' => [['type' => 'prevoyance', 'produit' => 'PREV-PRO']]],
            ['numero_adherent' => '1812088', 'nom' => 'GACHOT',               'prenom' => 'Damien',           'contrats' => [['type' => 'prevoyance', 'produit' => 'SPI-V3']]],
            ['numero_adherent' => '1827180', 'nom' => 'CHORVOZ',              'prenom' => 'David',            'contrats' => [['type' => 'prevoyance', 'produit' => 'SPI-V3']]],
            ['numero_adherent' => '1831819', 'nom' => 'GAILLAC',              'prenom' => 'Lacramioara-Elena', 'contrats' => [['type' => 'prevoyance', 'produit' => 'SPI-V3']]],
            ['numero_adherent' => '1840230', 'nom' => 'PELTRET',              'prenom' => 'Catherine',        'contrats' => [['type' => 'prevoyance', 'produit' => 'Pareo-V6']]],
            ['numero_adherent' => '1870009', 'nom' => 'LEGRAND',              'prenom' => 'Sebastien',        'contrats' => [['type' => 'prevoyance', 'produit' => 'SPI-V3']]],
            ['numero_adherent' => '1877041', 'nom' => 'TISSOT',               'prenom' => 'Catherine',        'contrats' => [['type' => 'prevoyance', 'produit' => 'SPI-V3']]],
            ['numero_adherent' => '1908879', 'nom' => 'FORNEROT',             'prenom' => 'Pascal',           'contrats' => [['type' => 'prevoyance', 'produit' => 'SPI-V3']]],
            ['numero_adherent' => '1993802', 'nom' => 'UZAN',                 'prenom' => 'Joseph',           'contrats' => [['type' => 'prevoyance', 'produit' => 'Décès Plus']]],
            ['numero_adherent' => '2063710', 'nom' => 'BLANCHET',             'prenom' => 'Patrice',          'contrats' => [['type' => 'prevoyance', 'produit' => 'Pareo-V6']]],
            ['numero_adherent' => '2192287', 'nom' => 'DUDA',                 'prenom' => 'Tulay',            'contrats' => [['type' => 'prevoyance', 'produit' => 'SPI-V3']]],
            ['numero_adherent' => '2192707', 'nom' => 'AFONSO',               'prenom' => 'Francois',         'contrats' => [['type' => 'prevoyance', 'produit' => 'SPI-V3']]],
            ['numero_adherent' => '2192828', 'nom' => 'TARON',                'prenom' => 'Rudolph',          'contrats' => [['type' => 'prevoyance', 'produit' => 'SPI-V3']]],
            ['numero_adherent' => '2211685', 'nom' => 'FRANCOIS',             'prenom' => 'Nicolas',          'contrats' => [['type' => 'prevoyance', 'produit' => 'SPI-V3']]],
            ['numero_adherent' => '2313599', 'nom' => 'HEMAIRIA',             'prenom' => 'Rahiba',           'contrats' => [['type' => 'prevoyance', 'produit' => 'SPI-V3']]],
            ['numero_adherent' => '2400819', 'nom' => 'RENARD',               'prenom' => 'Stephane',         'contrats' => [['type' => 'prevoyance', 'produit' => 'PREV-PRO']]],
            ['numero_adherent' => '2414989', 'nom' => 'BASILICO',             'prenom' => 'Stephanie',        'contrats' => [['type' => 'prevoyance', 'produit' => 'Frais généraux']]],
            ['numero_adherent' => '2419312', 'nom' => 'GALLET',               'prenom' => 'Vanessa',          'contrats' => [['type' => 'prevoyance', 'produit' => 'SPI-V3']]],
        ];
    }
}
