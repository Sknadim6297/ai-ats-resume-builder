<?php

namespace Tests\Unit;

use App\Services\AI\DTOs\AiRequest;
use App\Services\AI\Exceptions\AiProviderException;
use App\Services\AI\Providers\GeminiAiProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiAiProviderTest extends TestCase
{
    public function test_missing_api_key_is_rejected(): void
    {
        $provider = new GeminiAiProvider([
            'api_key' => '',
            'model' => 'gemini-2.5-flash',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
        ]);

        $this->expectException(AiProviderException::class);
        $provider->complete(new AiRequest(task: 'resume_import', prompt: 'hi'));
    }

    public function test_successful_json_completion(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => json_encode(['personal' => ['full_name' => 'Ada']])],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $provider = new GeminiAiProvider([
            'api_key' => 'key',
            'model' => 'gemini-2.5-flash',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'timeout' => 5,
            'max_retries' => 0,
        ]);

        $response = $provider->complete(new AiRequest(
            task: 'resume_import',
            prompt: 'extract',
            expectJson: true,
        ));

        $this->assertSame('Ada', $response->json['personal']['full_name']);
        $this->assertSame('gemini', $response->provider);
        $this->assertSame('gemini-2.5-flash', $response->model);
    }

    public function test_auth_failure_is_mapped(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'API key invalid']], 403),
        ]);

        $provider = new GeminiAiProvider([
            'api_key' => 'bad',
            'model' => 'gemini-2.5-flash',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'max_retries' => 0,
        ]);

        $this->expectException(AiProviderException::class);
        $this->expectExceptionMessage('Authentication failed');
        $provider->complete(new AiRequest(task: 'resume_import', prompt: 'extract', expectJson: true));
    }
}
