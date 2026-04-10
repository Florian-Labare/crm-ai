<?php

namespace App\Console\Commands;

use App\Models\Client;
use Illuminate\Console\Command;

class CleanBesoinEncoding extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clients:clean-besoins';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Nettoie le double encodage JSON des besoins dans la base de données';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧹 Nettoyage des besoins mal encodés...');

        $clients = Client::whereNotNull('besoins')->get();
        $fixed = 0;

        foreach ($clients as $client) {
            $besoins = $client->besoins;

            if (! is_array($besoins)) {
                continue;
            }

            $cleaned = [];
            $needsUpdate = false;

            foreach ($besoins as $besoin) {
                if (is_string($besoin)) {
                    // Vérifier si c'est une chaîne JSON encodée
                    $decoded = json_decode($besoin, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        // C'était du JSON double-encodé, on prend le contenu décodé
                        $cleaned = array_merge($cleaned, $decoded);
                        $needsUpdate = true;
                    } else {
                        // C'est une chaîne normale, on la garde
                        $cleaned[] = $besoin;
                    }
                } else {
                    $cleaned[] = $besoin;
                }
            }

            if ($needsUpdate && ! empty($cleaned)) {
                $client->besoins = $cleaned;
                $client->save();
                $fixed++;
                $this->line("✅ Client #{$client->id} ({$client->prenom} {$client->nom}): ".implode(', ', $cleaned));
            }
        }

        $this->info("✨ Nettoyage terminé: {$fixed} client(s) corrigé(s) sur {$clients->count()}");

        return Command::SUCCESS;
    }
}
