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
 * Google Gemini generateContent provider (server-side only).
 */
class GeminiAiProvider implements AiProviderInterface
{
    public function __construct(
        private readonly array $config,
    ) {}

    public function name(): string
    {
        return 'gemini';
    }

    public function complete(AiRequest $request): AiResponse
    {
        if (empty($this->config['api_key'])) {
            throw AiProviderException::notConfigured('gemini');
        }

        $model = trim((string) ($this->config['model'] ?? ''));
        if ($model === '') {
            throw AiProviderException::notConfigured('gemini');
        }

        $httpTimeout = max(30, (int) ($this->config['timeout'] ?? 90));
        if (function_exists('set_time_limit')) {
            @set_time_limit($httpTimeout + 30);
        }
        if (function_exists('ini_set')) {
            @ini_set('max_execution_time', (string) ($httpTimeout + 30));
        }

        $system = (string) ($request->options['system'] ?? 'You are a careful JSON extraction assistant.');
        $payload = [
            'systemInstruction' => [
                'parts' => [
                    ['text' => $system],
                ],
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $request->prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => (float) ($request->options['temperature'] ?? 0.1),
            ],
        ];

        if ($request->expectJson) {
            $payload['generationConfig']['responseMimeType'] = 'application/json';
        }

        $path = '/models/'.rawurlencode($model).':generateContent';

        try {
            $response = $this->client()->post($path, $payload);
        } catch (ConnectionException $e) {
            throw AiProviderException::requestFailed('gemini', 'Connection timed out or failed.');
        } catch (Throwable $e) {
            throw AiProviderException::requestFailed('gemini', 'Unexpected transport error.');
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw AiProviderException::requestFailed('gemini', 'Authentication failed.');
        }

        if ($response->status() === 429) {
            throw AiProviderException::requestFailed('gemini', 'Rate limit exceeded.');
        }

        if (in_array($response->status(), [400, 404], true) && $this->looksLikeModelError($response->json())) {
            throw AiProviderException::unsupportedModel('gemini');
        }

        if ($response->failed()) {
            throw AiProviderException::requestFailed(
                'gemini',
                'HTTP '.$response->status().' from provider.'
            );
        }

        $content = $this->extractText($response->json());

        if ($content === null || trim($content) === '') {
            throw AiProviderException::requestFailed('gemini', 'Empty model response.');
        }

        $json = null;
        if ($request->expectJson) {
            $json = json_decode($this->extractJson($content), true);
            if (! is_array($json)) {
                throw AiProviderException::invalidJson('gemini');
            }
        }

        return new AiResponse(
            content: $content,
            json: $json,
            provider: 'gemini',
            model: $model,
            meta: [
                'id' => data_get($response->json(), 'responseId'),
                'usage' => data_get($response->json(), 'usageMetadata'),
            ],
        );
    }

    protected function client(): PendingRequest
    {
        $retries = min(2, (int) ($this->config['max_retries'] ?? 1));

        return Http::baseUrl(rtrim((string) $this->config['base_url'], '/'))
            ->withHeaders([
                'x-goog-api-key' => (string) $this->config['api_key'],
            ])
            ->timeout((int) ($this->config['timeout'] ?? 90))
            ->retry($retries, 300, function ($exception) {
                return $exception instanceof ConnectionException;
            }, throw: false)
            ->acceptJson()
            ->asJson();
    }

    private function extractText(mixed $json): ?string
    {
        $parts = data_get($json, 'candidates.0.content.parts');
        if (! is_array($parts)) {
            return null;
        }

        $chunks = [];
        foreach ($parts as $part) {
            if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
                $chunks[] = $part['text'];
            }
        }

        if ($chunks === []) {
            return null;
        }

        return implode('', $chunks);
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
