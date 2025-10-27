<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TranscriptionService
{
    public function transcribe(string $audioPath): ?string
    {
        
        return $this->transcribeOpenAI($audioPath);
    }

    private function transcribeLocal(string $audioPath): ?string
    {
        try {
            $response = Http::attach('file', file_get_contents($audioPath), basename($audioPath))
                ->post(env('WHISPER_LOCAL_URL', 'http://whisper_local:9000/transcribe'));

            return $response->successful() ? trim($response->body()) : null;
        } catch (\Throwable $e) {
            Log::error('[Whisper Local] '.$e->getMessage());
            return null;
        }
    }

    private function transcribeOpenAI(string $audioPath): ?string
    {
        try {
            $apiKey = config('openai.api_key');
            if (!$apiKey) {
                throw new \Exception('Clé API OpenAI manquante.');
            }

            $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                ])
                ->asMultipart()
                ->attach('file', file_get_contents($audioPath), basename($audioPath))
                ->attach('model', 'whisper-1')
                ->attach('language', 'fr')
                ->post('https://api.openai.com/v1/audio/transcriptions');

            if (!$response->successful()) {
                Log::error('[Whisper API] Erreur ' . $response->status() . ' : ' . $response->body());
                return null;
            }

            return $response->json('text') ?? null;
        } catch (\Throwable $e) {
            Log::error('[Whisper API] ' . $e->getMessage());
            return null;
        }
    }
}
