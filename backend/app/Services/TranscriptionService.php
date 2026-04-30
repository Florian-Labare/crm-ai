<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TranscriptionService
{
    /**
     * Transcrit un fichier audio avec diarisation native Voxtral (2 locuteurs : Courtier + Client).
     *
     * Retourne un tableau avec :
     * - 'transcript'        : transcription complète avec labels [Courtier]: / [Client]:
     * - 'client_transcript' : uniquement les répliques du client (pour l'extraction IA)
     *
     * Si la diarisation échoue, retourne la transcription brute dans 'transcript'
     * et null dans 'client_transcript'.
     *
     * @return array{transcript: string, client_transcript: string|null}
     */
    public function transcribeWithDiarization(string $audioPath): array
    {
        if (config('mistral.features.use_for_transcription', false)) {
            $result = $this->transcribeVoxtralWithDiarization($audioPath);

            if ($result !== null) {
                return $result;
            }

            Log::warning('[TranscriptionService] Voxtral diarisation échouée, fallback transcription simple');
        }

        // Fallback : transcription simple sans diarisation
        $transcript = $this->transcribeOpenAI($audioPath);

        return [
            'transcript' => $transcript ?? '',
            'client_transcript' => null,
        ];
    }

    /**
     * Transcription simple sans diarisation (utilisée en fallback ou pour les courtes sessions).
     */
    public function transcribe(string $audioPath): ?string
    {
        if (config('mistral.features.use_for_transcription', false)) {
            $transcription = $this->transcribeVoxtral($audioPath);

            if (! empty($transcription)) {
                return $transcription;
            }

            Log::warning('[TranscriptionService] Voxtral échoué, fallback OpenAI');
        }

        return $this->transcribeOpenAI($audioPath);
    }

    /**
     * Appel Voxtral avec diarisation activée.
     * Identifie le courtier (premier locuteur) et le client (second locuteur).
     *
     * @return array{transcript: string, client_transcript: string}|null null en cas d'échec
     */
    private function transcribeVoxtralWithDiarization(string $audioPath): ?array
    {
        try {
            if (! file_exists($audioPath) || ! is_file($audioPath)) {
                throw new \Exception("Fichier audio introuvable : {$audioPath}");
            }

            $apiKey = config('mistral.api_key');
            if (! $apiKey) {
                throw new \Exception('Clé API Mistral manquante.');
            }

            Log::info('[TranscriptionService] Voxtral avec diarisation', [
                'file' => basename($audioPath),
                'model' => config('mistral.stt.model'),
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
            ])
                ->withOptions([
                    'connect_timeout' => 30,
                    'timeout' => 300,
                ])
                ->asMultipart()
                ->attach('file', file_get_contents($audioPath), basename($audioPath))
                ->attach('model', config('mistral.stt.model', 'voxtral-mini-latest'))
                ->attach('diarize', 'true')
                ->post(config('mistral.stt.endpoint'));

            if (! $response->successful()) {
                Log::error('[TranscriptionService] Voxtral erreur '.$response->status().' : '.$response->body());

                return null;
            }

            $json = $response->json();
            $segments = $json['segments'] ?? [];

            if (empty($segments)) {
                // Pas de segments : fallback sur le texte brut
                $text = $json['text'] ?? null;
                if (empty($text)) {
                    return null;
                }

                Log::warning('[TranscriptionService] Voxtral: pas de segments diarisés, retour texte brut');

                return [
                    'transcript' => $text,
                    'client_transcript' => null,
                ];
            }

            return $this->buildLabeledTranscript($segments);

        } catch (\Throwable $e) {
            Log::error('[TranscriptionService] Voxtral diarisation: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Construit la transcription labelisée à partir des segments diarisés.
     *
     * Stratégie d'identification :
     * - Le premier locuteur à parler = Courtier (il ouvre le rendez-vous)
     * - Le second locuteur à parler = Client
     *
     * Format de sortie :
     * [Courtier]: Bonjour, pouvez-vous me donner votre nom ?
     * [Client]: Jean Dupont.
     *
     * @param  array  $segments  Segments retournés par Voxtral avec speaker IDs
     * @return array{transcript: string, client_transcript: string}
     */
    private function buildLabeledTranscript(array $segments): array
    {
        // Identifier le rôle de chaque speaker_id
        // Premier speaker_id rencontré = courtier
        $speakerRoles = [];
        foreach ($segments as $segment) {
            $speakerId = $segment['speaker'] ?? null;
            if ($speakerId === null) {
                continue;
            }
            if (! isset($speakerRoles[$speakerId])) {
                $speakerRoles[$speakerId] = count($speakerRoles) === 0 ? 'Courtier' : 'Client';
            }
        }

        // Si un seul speaker détecté : on ne peut pas distinguer les rôles
        if (count($speakerRoles) < 2) {
            Log::warning('[TranscriptionService] Un seul locuteur détecté par Voxtral — pas de diarisation possible');
            $fullText = implode(' ', array_column($segments, 'text'));

            return [
                'transcript' => trim($fullText),
                'client_transcript' => null,
            ];
        }

        // Construire la transcription labelisée et la transcription client seule
        $labeledLines = [];
        $clientLines = [];

        foreach ($segments as $segment) {
            $speakerId = $segment['speaker'] ?? null;
            $text = trim($segment['text'] ?? '');

            if (empty($text) || $speakerId === null) {
                continue;
            }

            $role = $speakerRoles[$speakerId] ?? 'Client';
            $labeledLines[] = "[{$role}]: {$text}";

            if ($role === 'Client') {
                $clientLines[] = $text;
            }
        }

        $transcript = implode("\n", $labeledLines);
        $clientTranscript = implode(' ', $clientLines);

        Log::info('[TranscriptionService] Diarisation réussie', [
            'total_segments' => count($segments),
            'speakers' => $speakerRoles,
            'transcript_length' => strlen($transcript),
            'client_transcript_length' => strlen($clientTranscript),
        ]);

        return [
            'transcript' => $transcript,
            'client_transcript' => $clientTranscript ?: null,
        ];
    }

    /**
     * Transcription Voxtral simple (sans diarisation).
     */
    private function transcribeVoxtral(string $audioPath): ?string
    {
        try {
            if (! file_exists($audioPath) || ! is_file($audioPath)) {
                throw new \Exception("Fichier audio introuvable : {$audioPath}");
            }

            $apiKey = config('mistral.api_key');
            if (! $apiKey) {
                throw new \Exception('Clé API Mistral manquante.');
            }

            Log::info('[TranscriptionService] Voxtral simple', [
                'file' => basename($audioPath),
                'model' => config('mistral.stt.model'),
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
            ])
                ->withOptions([
                    'connect_timeout' => 30,
                    'timeout' => 180,
                ])
                ->asMultipart()
                ->attach('file', file_get_contents($audioPath), basename($audioPath))
                ->attach('model', config('mistral.stt.model', 'voxtral-mini-latest'))
                ->post(config('mistral.stt.endpoint'));

            if (! $response->successful()) {
                Log::error('[TranscriptionService] Voxtral erreur '.$response->status().' : '.$response->body());

                return null;
            }

            return $response->json('text') ?? null;

        } catch (\Throwable $e) {
            Log::error('[TranscriptionService] Voxtral: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Transcription OpenAI Whisper (fallback final).
     */
    private function transcribeOpenAI(string $audioPath): ?string
    {
        try {
            if (! file_exists($audioPath) || ! is_file($audioPath)) {
                throw new \Exception("Fichier audio introuvable : {$audioPath}");
            }

            $apiKey = config('openai.api_key');
            if (! $apiKey) {
                throw new \Exception('Clé API OpenAI manquante.');
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
            ])
                ->withOptions(['timeout' => 180])
                ->asMultipart()
                ->attach('file', file_get_contents($audioPath), basename($audioPath))
                ->attach('model', 'whisper-1')
                ->attach('language', 'fr')
                ->post('https://api.openai.com/v1/audio/transcriptions');

            if (! $response->successful()) {
                Log::error('[TranscriptionService] OpenAI Whisper erreur '.$response->status().' : '.$response->body());

                return null;
            }

            $transcription = $response->json('text') ?? null;
            Log::info('[TranscriptionService] OpenAI Whisper', ['text_length' => strlen($transcription ?? '')]);

            return $transcription;

        } catch (\Throwable $e) {
            Log::error('[TranscriptionService] OpenAI Whisper: '.$e->getMessage());

            return null;
        }
    }
}
