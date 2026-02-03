<?php

return [
    'api_key' => env('MISTRAL_API_KEY'),

    'stt' => [
        'model' => env('MISTRAL_STT_MODEL', 'voxtral-mini-latest'),
        'endpoint' => 'https://api.mistral.ai/v1/audio/transcriptions',
    ],

    'llm' => [
        'model' => env('MISTRAL_LLM_MODEL', 'mistral-small-latest'),
        'endpoint' => 'https://api.mistral.ai/v1/chat/completions',
    ],

    'features' => [
        'use_for_transcription' => env('MISTRAL_USE_FOR_STT', false),
        'use_for_llm' => env('MISTRAL_USE_FOR_LLM', false),
    ],

    'fallback_to_openai' => env('MISTRAL_FALLBACK', true),
];
