<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AiProviderInterface;
use App\Services\AI\DTOs\AiRequest;
use App\Services\AI\DTOs\AiResponse;
use App\Services\AI\Exceptions\AiProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Official xAI Grok chat completions provider.
 * Credentials remain server-side via config/ai.php.
 */
class GrokAiProvider implements AiProviderInterface
{
    public function __construct(
        private readonly array $config,
    ) {}

    public function name(): string
    {
        return 'grok';
    }

    public function complete(AiRequest $request): AiResponse
    {
        if (empty($this->config['api_key'])) {
            throw AiProviderException::notConfigured('grok');
        }

        $model = trim((string) ($this->config['model'] ?? ''));
        if ($model === '') {
            throw AiProviderException::notConfigured('grok');
        }

        // PHP max_execution_time (often 60s) must exceed the HTTP client timeout,
        // otherwise long Grok calls die with a FatalError mid-request.
        $httpTimeout = max(30, (int) ($this->config['timeout'] ?? 45));
        if (function_exists('set_time_limit')) {
            @set_time_limit($httpTimeout + 30);
        }
        if (function_exists('ini_set')) {
            @ini_set('max_execution_time', (string) ($httpTimeout + 30));
        }

        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => (string) ($request->options['system'] ?? 'You are a careful JSON extraction assistant.'),
                ],
                [
                    'role' => 'user',
                    'content' => $request->prompt,
                ],
            ],
            'temperature' => (float) ($request->options['temperature'] ?? 0.1),
            'stream' => false,
        ];

        if ($request->expectJson) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        try {
            $response = $this->client()->post('/chat/completions', $payload);
        } catch (ConnectionException $e) {
            throw AiProviderException::requestFailed('grok', 'Connection timed out or failed.');
        } catch (Throwable $e) {
            throw AiProviderException::requestFailed('grok', 'Unexpected transport error.');
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw AiProviderException::requestFailed('grok', 'Authentication failed.');
        }

        if ($response->status() === 429) {
            throw AiProviderException::requestFailed('grok', 'Rate limit exceeded.');
        }

        if (in_array($response->status(), [400, 404], true) && $this->looksLikeModelError($response->json())) {
            throw AiProviderException::unsupportedModel('grok');
        }

        if ($response->failed()) {
            // Never include raw provider bodies in exceptions (may echo request metadata).
            throw AiProviderException::requestFailed(
                'grok',
                'HTTP '.$response->status().' from provider.'
            );
        }

        $content = data_get($response->json(), 'choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw AiProviderException::requestFailed('grok', 'Empty model response.');
        }

        $json = null;
        if ($request->expectJson) {
            $json = json_decode($this->extractJson($content), true);
            if (! is_array($json)) {
                throw AiProviderException::invalidJson('grok');
            }
        }

        return new AiResponse(
            content: $content,
            json: $json,
            provider: 'grok',
            model: $model,
            meta: [
                'id' => data_get($response->json(), 'id'),
                'usage' => data_get($response->json(), 'usage'),
            ],
        );
    }

    protected function client(): PendingRequest
    {
        $retries = min(2, (int) ($this->config['max_retries'] ?? 1));

        return Http::baseUrl(rtrim((string) $this->config['base_url'], '/'))
            ->withToken((string) $this->config['api_key'])
            ->timeout((int) ($this->config['timeout'] ?? 45))
            ->retry($retries, 300, function ($exception) {
                // Retry only transient connection failures; never loop on auth errors.
                return $exception instanceof ConnectionException;
            }, throw: false)
            ->acceptJson()
            ->asJson();
    }

    private function extractJson(string $content): string
    {
        $trimmed = trim($content);

        if (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```(?:json)?\s*/i', '', $trimmed) ?? $trimmed;
            $trimmed = preg_replace('/\s*```$/', '', $trimmed) ?? $trimmed;
        }

        return trim($trimmed);
    }

    private function looksLikeModelError(mixed $json): bool
    {
        $error = data_get($json, 'error.message', data_get($json, 'error'));
        if (is_array($error) || is_object($error)) {
            $message = mb_strtolower((string) json_encode($error));
        } else {
            $message = mb_strtolower(trim((string) $error));
        }

        if ($message === '') {
            $message = mb_strtolower((string) json_encode($json));
        }

        return str_contains($message, 'model')
            || str_contains($message, 'not found')
            || str_contains($message, 'does not exist')
            || str_contains($message, 'unsupported');
    }
}
