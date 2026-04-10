<?php

namespace App\Services\Ai\Traits;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Trait centralisant les appels LLM avec switch Mistral/OpenAI.
 *
 * Permet de basculer entre Mistral et OpenAI via les feature flags
 * avec fallback automatique si Mistral échoue.
 */
trait LlmClientTrait
{
    /**
     * Appelle le LLM (Mistral ou OpenAI selon la configuration).
     *
     * @param  string  $system  Prompt système
     * @param  string  $user  Prompt utilisateur
     * @param  float  $temperature  Température (0.0 à 1.0)
     * @param  bool  $json  Forcer le format JSON
     * @return array|null Données décodées ou null si échec
     */
    protected function callLlm(string $system, string $user, float $temperature = 0.1, bool $json = true): ?array
    {
        $useMistral = config('mistral.features.use_for_llm', false);

        try {
            return $useMistral
                ? $this->callMistral($system, $user, $temperature, $json)
                : $this->callOpenAI($system, $user, $temperature, $json);
        } catch (\Throwable $e) {
            if ($useMistral && config('mistral.fallback_to_openai', true)) {
                Log::warning('[LLM] Mistral failed, fallback to OpenAI', [
                    'error' => $e->getMessage(),
                    'service' => static::class,
                ]);

                return $this->callOpenAI($system, $user, $temperature, $json);
            }

            Log::error('[LLM] Request failed', [
                'error' => $e->getMessage(),
                'service' => static::class,
                'provider' => $useMistral ? 'Mistral' : 'OpenAI',
            ]);

            throw $e;
        }
    }

    /**
     * Appelle Mistral LLM.
     */
    private function callMistral(string $system, string $user, float $temperature, bool $json): ?array
    {
        $apiKey = config('mistral.api_key');
        if (! $apiKey) {
            throw new \Exception('Clé API Mistral manquante.');
        }

        $payload = [
            'model' => config('mistral.llm.model', 'mistral-small-latest'),
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            'temperature' => $temperature,
        ];

        if ($json) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(60)->post(config('mistral.llm.endpoint'), $payload);

        if (! $response->successful()) {
            throw new \Exception('Mistral API error: '.$response->status().' - '.$response->body());
        }

        $raw = $response->json('choices.0.message.content', '');

        Log::info('[LLM] Mistral response', [
            'service' => static::class,
            'raw' => $raw,
        ]);

        $data = json_decode($raw, true);

        if ($json && ! is_array($data)) {
            Log::warning('[LLM] Failed to parse Mistral JSON response', [
                'service' => static::class,
                'content' => $raw,
            ]);

            return null;
        }

        return $data;
    }

    /**
     * Appelle OpenAI LLM.
     */
    private function callOpenAI(string $system, string $user, float $temperature, bool $json): ?array
    {
        $apiKey = config('openai.api_key');
        if (! $apiKey) {
            throw new \Exception('Clé API OpenAI manquante.');
        }

        $payload = [
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            'temperature' => $temperature,
        ];

        if ($json) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$apiKey,
            'OpenAI-Organization' => env('OPENAI_ORG_ID'),
        ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', $payload);

        if (! $response->successful()) {
            throw new \Exception('OpenAI API error: '.$response->status().' - '.$response->body());
        }

        $raw = $response->json('choices.0.message.content', '');

        Log::info('[LLM] OpenAI response', [
            'service' => static::class,
            'raw' => $raw,
        ]);

        $data = json_decode($raw, true);

        if ($json && ! is_array($data)) {
            Log::warning('[LLM] Failed to parse OpenAI JSON response', [
                'service' => static::class,
                'content' => $raw,
            ]);

            return null;
        }

        return $data;
    }
}
