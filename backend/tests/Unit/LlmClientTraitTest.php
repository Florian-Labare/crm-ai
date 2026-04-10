<?php

namespace Tests\Unit;

use App\Services\Ai\Traits\LlmClientTrait;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Classe de test utilisant le trait LlmClientTrait.
 */
class LlmTestService
{
    use LlmClientTrait;

    public function testCallLlm(string $system, string $user, float $temperature = 0.1, bool $json = true): ?array
    {
        return $this->callLlm($system, $user, $temperature, $json);
    }
}

class LlmClientTraitTest extends TestCase
{
    private LlmTestService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LlmTestService;
    }

    public function test_mistral_api_returns_valid_json(): void
    {
        config(['mistral.features.use_for_llm' => true]);
        config(['mistral.api_key' => 'test-mistral-key']);
        config(['mistral.llm.endpoint' => 'https://api.mistral.ai/v1/chat/completions']);
        config(['mistral.llm.model' => 'mistral-small-latest']);

        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => '{"message": "Bonjour!"}',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->service->testCallLlm(
            'Tu es un assistant. Réponds en JSON.',
            'Dis bonjour en JSON: {"message": "..."}',
            0.1,
            true
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('message', $result);
        $this->assertEquals('Bonjour!', $result['message']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'mistral.ai')
                && $request->hasHeader('Authorization', 'Bearer test-mistral-key')
                && $request['model'] === 'mistral-small-latest';
        });
    }

    public function test_openai_api_returns_valid_json(): void
    {
        config(['mistral.features.use_for_llm' => false]);
        config(['openai.api_key' => 'test-openai-key']);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => '{"greeting": "Hello!"}',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->service->testCallLlm(
            'Tu es un assistant.',
            'Dis bonjour',
            0.1,
            true
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('greeting', $result);
        $this->assertEquals('Hello!', $result['greeting']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'openai.com')
                && $request->hasHeader('Authorization', 'Bearer test-openai-key')
                && $request['model'] === 'gpt-4o-mini';
        });
    }

    public function test_fallback_to_openai_when_mistral_fails(): void
    {
        config(['mistral.features.use_for_llm' => true]);
        config(['mistral.api_key' => 'test-mistral-key']);
        config(['mistral.fallback_to_openai' => true]);
        config(['openai.api_key' => 'test-openai-key']);
        config(['mistral.llm.endpoint' => 'https://api.mistral.ai/v1/chat/completions']);

        Http::fake([
            'api.mistral.ai/*' => Http::response(['error' => 'Rate limit'], 429),
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => '{"fallback": true}',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->service->testCallLlm(
            'Tu es un assistant.',
            'Dis bonjour',
            0.1,
            true
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('fallback', $result);
        $this->assertTrue($result['fallback']);
    }

    public function test_throws_exception_when_no_fallback(): void
    {
        config(['mistral.features.use_for_llm' => true]);
        config(['mistral.api_key' => 'test-mistral-key']);
        config(['mistral.fallback_to_openai' => false]);
        config(['mistral.llm.endpoint' => 'https://api.mistral.ai/v1/chat/completions']);

        Http::fake([
            'api.mistral.ai/*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Mistral API error: 401');

        $this->service->testCallLlm('System', 'User', 0.1, true);
    }

    public function test_throws_exception_when_mistral_key_missing(): void
    {
        config(['mistral.features.use_for_llm' => true]);
        config(['mistral.api_key' => null]);
        config(['mistral.fallback_to_openai' => false]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Clé API Mistral manquante.');

        $this->service->testCallLlm('System', 'User', 0.1, true);
    }

    public function test_throws_exception_when_openai_key_missing(): void
    {
        config(['mistral.features.use_for_llm' => false]);
        config(['openai.api_key' => null]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Clé API OpenAI manquante.');

        $this->service->testCallLlm('System', 'User', 0.1, true);
    }

    public function test_returns_null_when_json_parsing_fails(): void
    {
        config(['mistral.features.use_for_llm' => true]);
        config(['mistral.api_key' => 'test-mistral-key']);
        config(['mistral.llm.endpoint' => 'https://api.mistral.ai/v1/chat/completions']);

        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'This is not valid JSON',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->service->testCallLlm(
            'Tu es un assistant.',
            'Réponds quelque chose',
            0.1,
            true
        );

        $this->assertNull($result);
    }

    public function test_json_format_is_sent_in_payload(): void
    {
        config(['mistral.features.use_for_llm' => true]);
        config(['mistral.api_key' => 'test-mistral-key']);
        config(['mistral.llm.endpoint' => 'https://api.mistral.ai/v1/chat/completions']);

        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [['message' => ['content' => '{}']]],
            ], 200),
        ]);

        $this->service->testCallLlm('System', 'User', 0.1, true);

        Http::assertSent(function ($request) {
            return isset($request['response_format'])
                && $request['response_format']['type'] === 'json_object';
        });
    }

    public function test_temperature_is_passed_correctly(): void
    {
        config(['mistral.features.use_for_llm' => true]);
        config(['mistral.api_key' => 'test-mistral-key']);
        config(['mistral.llm.endpoint' => 'https://api.mistral.ai/v1/chat/completions']);

        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [['message' => ['content' => '{}']]],
            ], 200),
        ]);

        $this->service->testCallLlm('System', 'User', 0.7, true);

        Http::assertSent(function ($request) {
            return $request['temperature'] === 0.7;
        });
    }
}
