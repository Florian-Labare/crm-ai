<?php

namespace Tests\Unit;

use App\Services\TranscriptionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TranscriptionServiceTest extends TestCase
{
    private string $tempAudioFile;

    protected function setUp(): void
    {
        parent::setUp();

        // Créer un fichier audio temporaire pour les tests
        $this->tempAudioFile = sys_get_temp_dir().'/test_audio_'.uniqid().'.mp3';
        file_put_contents($this->tempAudioFile, 'fake audio content for testing');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempAudioFile)) {
            unlink($this->tempAudioFile);
        }

        parent::tearDown();
    }

    #[Test]
    public function voxtral_transcription_returns_text_on_success()
    {
        config(['mistral.features.use_for_transcription' => true]);
        config(['mistral.api_key' => 'test_key']);
        config(['mistral.stt.model' => 'voxtral-mini-latest']);
        config(['mistral.stt.endpoint' => 'https://api.mistral.ai/v1/audio/transcriptions']);

        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'text' => "Bonjour, je m'appelle Jean Dupont.",
            ], 200),
        ]);

        $service = new TranscriptionService;
        $result = $service->transcribe($this->tempAudioFile);

        $this->assertEquals("Bonjour, je m'appelle Jean Dupont.", $result);
    }

    #[Test]
    public function voxtral_fallback_to_whisper_local_on_failure()
    {
        config(['mistral.features.use_for_transcription' => true]);
        config(['mistral.api_key' => 'test_key']);
        config(['mistral.stt.endpoint' => 'https://api.mistral.ai/v1/audio/transcriptions']);

        Http::fake([
            'api.mistral.ai/*' => Http::response(['error' => 'Service unavailable'], 503),
            'api.openai.com/*' => Http::response([
                'text' => 'Fallback transcription from OpenAI.',
            ], 200),
        ]);

        // Simuler l'échec de Whisper local (le script n'existe pas)
        Log::shouldReceive('warning')
            ->once()
            ->with('⚠️ Voxtral a échoué, tentative Whisper local...');

        Log::shouldReceive('error')
            ->atLeast()->once();

        Log::shouldReceive('warning')
            ->once()
            ->with('⚠️ Whisper local a échoué, utilisation de l\'API OpenAI');

        Log::shouldReceive('info')
            ->atLeast()->once();

        config(['openai.api_key' => 'test_openai_key']);

        $service = new TranscriptionService;
        $result = $service->transcribe($this->tempAudioFile);

        $this->assertEquals('Fallback transcription from OpenAI.', $result);
    }

    #[Test]
    public function voxtral_is_skipped_when_disabled()
    {
        config(['mistral.features.use_for_transcription' => false]);
        config(['openai.api_key' => 'test_openai_key']);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'text' => 'Direct OpenAI transcription.',
            ], 200),
        ]);

        // Whisper local va échouer, donc fallback sur OpenAI
        $service = new TranscriptionService;
        $result = $service->transcribe($this->tempAudioFile);

        // Vérifie que Voxtral n'a pas été appelé
        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), 'mistral.ai');
        });
    }

    #[Test]
    public function voxtral_returns_null_when_api_key_missing()
    {
        config(['mistral.features.use_for_transcription' => true]);
        config(['mistral.api_key' => null]);
        config(['openai.api_key' => 'test_openai_key']);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'text' => 'OpenAI fallback.',
            ], 200),
        ]);

        $service = new TranscriptionService;
        $result = $service->transcribe($this->tempAudioFile);

        // Doit fallback sur OpenAI
        $this->assertEquals('OpenAI fallback.', $result);
    }

    #[Test]
    public function transcribe_returns_null_for_nonexistent_file()
    {
        config(['mistral.features.use_for_transcription' => true]);
        config(['mistral.api_key' => 'test_key']);

        $service = new TranscriptionService;
        $result = $service->transcribe('/nonexistent/file.mp3');

        $this->assertNull($result);
    }

    #[Test]
    public function openai_transcription_works_directly()
    {
        config(['mistral.features.use_for_transcription' => false]);
        config(['openai.api_key' => 'test_openai_key']);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'text' => 'OpenAI Whisper transcription.',
            ], 200),
        ]);

        // Simuler l'échec de Whisper local
        $service = new TranscriptionService;
        $result = $service->transcribe($this->tempAudioFile);

        $this->assertEquals('OpenAI Whisper transcription.', $result);
    }

    #[Test]
    public function openai_returns_null_on_api_error()
    {
        config(['mistral.features.use_for_transcription' => false]);
        config(['openai.api_key' => 'test_openai_key']);

        Http::fake([
            'api.openai.com/*' => Http::response(['error' => 'Rate limited'], 429),
        ]);

        $service = new TranscriptionService;
        $result = $service->transcribe($this->tempAudioFile);

        $this->assertNull($result);
    }

    #[Test]
    public function voxtral_sends_correct_model_in_request()
    {
        config(['mistral.features.use_for_transcription' => true]);
        config(['mistral.api_key' => 'test_key']);
        config(['mistral.stt.model' => 'voxtral-mini-latest']);
        config(['mistral.stt.endpoint' => 'https://api.mistral.ai/v1/audio/transcriptions']);

        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'text' => 'Test transcription.',
            ], 200),
        ]);

        $service = new TranscriptionService;
        $service->transcribe($this->tempAudioFile);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.mistral.ai')
                && $request->hasHeader('Authorization', 'Bearer test_key');
        });
    }

    #[Test]
    public function voxtral_handles_empty_response_gracefully()
    {
        config(['mistral.features.use_for_transcription' => true]);
        config(['mistral.api_key' => 'test_key']);
        config(['mistral.stt.endpoint' => 'https://api.mistral.ai/v1/audio/transcriptions']);
        config(['openai.api_key' => 'test_openai_key']);

        Http::fake([
            'api.mistral.ai/*' => Http::response(['text' => null], 200),
            'api.openai.com/*' => Http::response([
                'text' => 'OpenAI fallback transcription.',
            ], 200),
        ]);

        $service = new TranscriptionService;
        $result = $service->transcribe($this->tempAudioFile);

        // Voxtral retourne null, fallback sur Whisper local puis OpenAI
        $this->assertEquals('OpenAI fallback transcription.', $result);
    }

    #[Test]
    public function voxtral_handles_timeout()
    {
        config(['mistral.features.use_for_transcription' => true]);
        config(['mistral.api_key' => 'test_key']);
        config(['mistral.stt.endpoint' => 'https://api.mistral.ai/v1/audio/transcriptions']);
        config(['openai.api_key' => 'test_openai_key']);

        Http::fake([
            'api.mistral.ai/*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
            },
            'api.openai.com/*' => Http::response([
                'text' => 'OpenAI after timeout.',
            ], 200),
        ]);

        $service = new TranscriptionService;
        $result = $service->transcribe($this->tempAudioFile);

        // Doit fallback vers OpenAI
        $this->assertEquals('OpenAI after timeout.', $result);
    }

    #[Test]
    public function voxtral_french_transcription_quality()
    {
        config(['mistral.features.use_for_transcription' => true]);
        config(['mistral.api_key' => 'test_key']);
        config(['mistral.stt.endpoint' => 'https://api.mistral.ai/v1/audio/transcriptions']);

        // Simuler une transcription française typique
        $frenchTranscription = "Bonjour, je m'appelle Marie Dupont. Je suis née le 15 mai 1985 à Lyon. Ma femme s'appelle Sophie et elle est infirmière.";

        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'text' => $frenchTranscription,
            ], 200),
        ]);

        $service = new TranscriptionService;
        $result = $service->transcribe($this->tempAudioFile);

        $this->assertEquals($frenchTranscription, $result);
        $this->assertStringContainsString('Dupont', $result);
        $this->assertStringContainsString('infirmière', $result);
    }
}
