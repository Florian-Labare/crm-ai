<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TranscriptionService
{
    public function transcribe(string $audioPath): ?string
    {
        // Utiliser Whisper local (plus rapide et gratuit)
        $transcription = $this->transcribeLocal($audioPath);

        // Fallback sur OpenAI API si échec local
        if (empty($transcription)) {
            Log::warning('⚠️ Whisper local a échoué, utilisation de l\'API OpenAI');

            return $this->transcribeOpenAI($audioPath);
        }

        return $transcription;
    }

    private function transcribeLocal(string $audioPath): ?string
    {
        try {
            if (! file_exists($audioPath)) {
                throw new \Exception("Fichier audio introuvable : {$audioPath}");
            }
            if (! is_file($audioPath)) {
                throw new \Exception("Chemin audio invalide (pas un fichier) : {$audioPath}");
            }

            // Chemin vers le script Python
            $scriptPath = base_path('scripts/whisper_transcribe.py');

            if (! file_exists($scriptPath)) {
                throw new \Exception("Script Whisper introuvable : {$scriptPath}");
            }

            // Modèle à utiliser (tiny, base, small, medium, large)
            // base = bon compromis vitesse/qualité pour un POC
            $model = env('WHISPER_MODEL', 'base');

            // Exécuter le script Python avec timeout de 5 minutes
            $command = sprintf(
                'python3 %s %s %s 2>&1',
                escapeshellarg($scriptPath),
                escapeshellarg($audioPath),
                escapeshellarg($model)
            );

            Log::info('🎤 Transcription Whisper locale', [
                'command' => $command,
                'model' => $model,
            ]);

            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \Exception('Erreur lors de l\'exécution du script Python : '.implode("\n", $output));
            }

            $result = json_decode(implode("\n", $output), true);

            if (isset($result['error'])) {
                throw new \Exception($result['error']);
            }

            $transcription = $result['text'] ?? null;

            // Log de la transcription
            Log::info('📝 Transcription Whisper locale', [
                'text' => $transcription,
                'language' => $result['language'] ?? 'unknown',
                'probability' => $result['language_probability'] ?? 0,
            ]);

            return $transcription;

        } catch (\Throwable $e) {
            Log::error('[Whisper Local] '.$e->getMessage());

            return null;
        }
    }

    private function transcribeOpenAI(string $audioPath): ?string
    {
        try {
            if (! file_exists($audioPath)) {
                throw new \Exception("Fichier audio introuvable : {$audioPath}");
            }
            if (! is_file($audioPath)) {
                throw new \Exception("Chemin audio invalide (pas un fichier) : {$audioPath}");
            }

            $apiKey = config('openai.api_key');
            if (! $apiKey) {
                throw new \Exception('Clé API OpenAI manquante.');
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
            ])
                ->asMultipart()
                ->attach('file', file_get_contents($audioPath), basename($audioPath))
                ->attach('model', 'whisper-1')
                ->attach('language', 'fr')
                ->post('https://api.openai.com/v1/audio/transcriptions');

            if (! $response->successful()) {
                Log::error('[Whisper API] Erreur '.$response->status().' : '.$response->body());

                return null;
            }

            $transcription = $response->json('text') ?? null;

            // 📝 Log de la transcription pour debug
            Log::info('📝 Transcription Whisper', ['text' => $transcription]);

            return $transcription;
        } catch (\Throwable $e) {
            Log::error('[Whisper API] '.$e->getMessage());

            return null;
        }
    }
}
