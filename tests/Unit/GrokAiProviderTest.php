<?php

namespace Tests\Unit;

use App\Services\AI\DTOs\AiRequest;
use App\Services\AI\Exceptions\AiProviderException;
use App\Services\AI\Providers\GrokAiProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GrokAiProviderTest extends TestCase
{
    public function test_missing_api_key_is_rejected(): void
    {
        $provider = new GrokAiProvider([
            'api_key' => '',
            'model' => 'test-model',
            'base_url' => 'https://api.x.ai/v1',
        ]);

        $this->expectException(AiProviderException::class);
        $provider->complete(new AiRequest(task: 'jd_extraction', prompt: 'hi'));
    }

    public function test_missing_model_is_rejected(): void
    {
        $provider = new GrokAiProvider([
            'api_key' => 'key',
            'model' => '',
            'base_url' => 'https://api.x.ai/v1',
        ]);

        $this->expectException(AiProviderException::class);
        $provider->complete(new AiRequest(task: 'jd_extraction', prompt: 'hi'));
    }

    public function test_successful_json_completion(): void
    {
        Http::fake([
            'api.x.ai/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode(['job_title' => 'Engineer'])]],
                ],
            ], 200),
        ]);

        $provider = new GrokAiProvider([
            'api_key' => 'key',
            'model' => 'test-model',
            'base_url' => 'https://api.x.ai/v1',
            'timeout' => 5,
            'max_retries' => 0,
        ]);

        $response = $provider->complete(new AiRequest(
            task: 'jd_extraction',
            prompt: 'extract',
            expectJson: true,
        ));

        $this->assertSame('Engineer', $response->json['job_title']);
        $this->assertSame('grok', $response->provider);
        $this->assertSame('test-model', $response->model);
    }

    public function test_auth_failure_is_mapped(): void
    {
        Http::fake([
            'api.x.ai/*' => Http::response(['error' => 'unauthorized'], 401),
        ]);

        $provider = new GrokAiProvider([
            'api_key' => 'bad',
            'model' => 'test-model',
            'base_url' => 'https://api.x.ai/v1',
            'max_retries' => 0,
        ]);

        $this->expectException(AiProviderException::class);
        $this->expectExceptionMessage('Authentication failed');
        $provider->complete(new AiRequest(task: 'jd_extraction', prompt: 'hi'));
    }
}
