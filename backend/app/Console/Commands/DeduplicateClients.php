<?php

namespace App\Console\Commands;

use App\Models\AudioRecord;
use App\Models\Client;
use App\Models\Conjoint;
use App\Models\Enfant;
use App\Models\QuestionnaireRisque;
use App\Models\SanteSouhait;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DeduplicateClients extends Command
{
    protected $signature = 'clients:deduplicate {--merge : Fusionner automatiquement les doublons détectés}';

    protected $description = 'Détecte (et optionnellement fusionne) les doublons clients par utilisateur.';

    public function handle(): int
    {
        $clients = Client::with(['conjoint', 'enfants', 'santeSouhait'])->get();
        if ($clients->isEmpty()) {
            $this->info('Aucun client enregistré.');
            return self::SUCCESS;
        }

        $duplicates = $this->detectDuplicates($clients);

        if ($duplicates->isEmpty()) {
            $this->info('Aucun doublon détecté 🎉');
            return self::SUCCESS;
        }

        $this->warn(sprintf('%d groupe(s) de doublons détecté(s)', $duplicates->count()));

        $merge = $this->option('merge');
        foreach ($duplicates as $group) {
            $this->line('');
            $this->line(str_repeat('-', 80));
            $this->line($this->formatGroupSummary($group));

            if ($merge) {
                $this->mergeGroup($group);
            }
        }

        if (!$merge) {
            $this->line('');
            $this->comment('Ajoutez --merge pour fusionner automatiquement les doublons.');
        }

        return self::SUCCESS;
    }

    private function detectDuplicates(Collection $clients): Collection
    {
        $groups = collect();

        $clients->groupBy(function (Client $client) {
            $keyParts = [
                $client->user_id,
                $this->normalize($client->email),
            ];
            return implode('|', $keyParts);
        })->each(function (Collection $group) use ($groups) {
            if ($group->filter(fn (Client $c) => !empty($c->email))->count() > 1) {
                $groups->push($group->filter(fn (Client $c) => !empty($c->email)));
            }
        });

        $clients->groupBy(function (Client $client) {
            $keyParts = [
                $client->user_id,
                $this->normalizePhone($client->telephone),
            ];
            return implode('|', $keyParts);
        })->each(function (Collection $group) use ($groups) {
            if ($group->filter(fn (Client $c) => !empty($this->normalizePhone($c->telephone)))->count() > 1) {
                $groups->push($group->filter(fn (Client $c) => !empty($this->normalizePhone($c->telephone))));
            }
        });

        $clients->groupBy(function (Client $client) {
            return implode('|', [
                $client->user_id,
                $this->normalize($client->nom),
                $this->normalize($client->prenom),
                $client->date_naissance ?? '',
            ]);
        })->each(function (Collection $group) use ($groups) {
            if ($group->count() > 1 && $this->normalize($group->first()->nom) && $this->normalize($group->first()->prenom)) {
                $groups->push($group);
            }
        });

        return $groups->unique(function (Collection $group) {
            return $group->pluck('id')->sort()->implode('-');
        });
    }

    private function formatGroupSummary(Collection $group): string
    {
        $first = $group->first();
        $ids = $group->pluck('id')->implode(', ');
        return sprintf(
            "User #%d • %s %s • %d doublon(s) [IDs: %s]",
            $first->user_id,
            $first->prenom,
            $first->nom,
            $group->count(),
            $ids
        );
    }

    private function mergeGroup(Collection $group): void
    {
        $master = $group->sortBy('created_at')->first();
        $duplicates = $group->where('id', '!=', $master->id);

        foreach ($duplicates as $duplicate) {
            $this->mergeClient($master, $duplicate);
        }
    }

    private function mergeClient(Client $master, Client $duplicate): void
    {
        foreach ($master->getFillable() as $field) {
            if (blank($master->{$field}) && filled($duplicate->{$field})) {
                $master->{$field} = $duplicate->{$field};
            }
        }
        $master->save();

        Conjoint::where('client_id', $duplicate->id)->update(['client_id' => $master->id]);
        Enfant::where('client_id', $duplicate->id)->update(['client_id' => $master->id]);
        SanteSouhait::where('client_id', $duplicate->id)->update(['client_id' => $master->id]);
        QuestionnaireRisque::where('client_id', $duplicate->id)->update(['client_id' => $master->id]);
        AudioRecord::where('client_id', $duplicate->id)->update(['client_id' => $master->id]);

        $duplicate->delete();

        $this->info(sprintf('→ Fusion du client #%d dans #%d effectuée', $duplicate->id, $master->id));
    }

    private function normalize(?string $value): ?string
    {
        if (empty($value)) {
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
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '33') && strlen($digits) === 11) {
            $digits = '0' . substr($digits, 2);
        }

        return $digits;
    }
}
