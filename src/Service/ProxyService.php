<?php

declare(strict_types=1);

namespace Tourze\OpenAiHttpProxyBundle\Service;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tourze\OpenAiContracts\Client\OpenAiCompatibleClientInterface;
use Tourze\OpenAiHttpProxyBundle\Exception\ClientConfigurationException;

#[WithMonologChannel(channel: 'open_ai_http_proxy')]
final class ProxyService
{
    /**
     * @var array<string, array{requests: int, total_latency: float, successes: int, failures: int}>
     */
    private array $clientMetrics = [];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ClientSelectorService $clientSelector,
        private readonly LoggerInterface $logger,
    ) {
    }

    private function getDefaultTimeout(): int
    {
        $timeout = $_ENV['OPENAI_PROXY_DEFAULT_TIMEOUT'] ?? 30;

        return is_numeric($timeout) ? (int) $timeout : 30;
    }

    private function getMaxRetries(): int
    {
        $retries = $_ENV['OPENAI_PROXY_MAX_RETRIES'] ?? 3;

        return is_numeric($retries) ? (int) $retries : 3;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     */
    public function forward(string $endpoint, array $data, array $context = []): Response
    {
        $modelValue = $data['model'] ?? 'gpt-3.5-turbo';
        $model = is_string($modelValue) ? $modelValue : 'gpt-3.5-turbo';
        $client = $this->clientSelector->selectClientWithFallback($model, $context);

        if (null === $client) {
            return new JsonResponse([
                'error' => [
                    'message' => 'No available OpenAI client for model: ' . $model,
                    'type' => 'service_unavailable',
                    'code' => 'no_client_available',
                ],
            ], 503);
        }

        $startTime = microtime(true);

        try {
            $url = $this->buildUrl($client, $endpoint);
            $headers = $this->buildHeaders($client);

            $response = $this->httpClient->request('POST', $url, [
                'headers' => $headers,
                'json' => $data,
                'timeout' => $context['timeout'] ?? $this->getDefaultTimeout(),
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);

            $latencyMs = (microtime(true) - $startTime) * 1000;
            $this->clientSelector->recordRequest($client, $latencyMs, true);

            $this->logRequest($client, $endpoint, $model, $statusCode, $latencyMs);

            return new JsonResponse(
                json_decode($content, true),
                $statusCode
            );
        } catch (\Exception $e) {
            $latencyMs = (microtime(true) - $startTime) * 1000;
            $this->clientSelector->recordRequest($client, $latencyMs, false);

            $this->logger->error('Request failed', [
                'client' => $client->getName(),
                'endpoint' => $endpoint,
                'model' => $model,
                'error' => $e->getMessage(),
                'latency_ms' => $latencyMs,
            ]);

            if (!isset($context['exclude']) || !is_array($context['exclude'])) {
                $context['exclude'] = [];
            }
            $context['exclude'][] = spl_object_id($client);

            if (count($context['exclude']) < $this->getMaxRetries()) {
                return $this->forward($endpoint, $data, $context);
            }

            return new JsonResponse([
                'error' => [
                    'message' => 'Request failed after multiple attempts',
                    'type' => 'request_failed',
                    'details' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    private function buildUrl(OpenAiCompatibleClientInterface $client, string $endpoint): string
    {
        $baseUrl = rtrim($client->getBaseUrl(), '/');
        $endpoint = ltrim($endpoint, '/');

        return "{$baseUrl}/{$endpoint}";
    }

    /**
     * @return array<string, string>
     */
    private function buildHeaders(OpenAiCompatibleClientInterface $client): array
    {
        $headers = [
            'Content-Type' => 'application/json',
        ];

        $apiKey = $client->getApiKey();
        if ('' === $apiKey) {
            throw new ClientConfigurationException('Client has no API key configured');
        }

        $baseUrl = $client->getBaseUrl();

        if (str_contains($baseUrl, 'azure.com')) {
            $headers['api-key'] = $apiKey;
        } elseif (str_contains($baseUrl, 'anthropic.com')) {
            $headers['x-api-key'] = $apiKey;
            $headers['anthropic-version'] = '2023-06-01';
        } elseif (str_contains($baseUrl, 'google')) {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
            $headers['x-goog-api-key'] = $apiKey;
        } else {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }

        // Ensure all values are strings
        return array_map('strval', $headers);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     */
    public function forwardStream(string $endpoint, array $data, array $context = []): StreamedResponse
    {
        $modelValue = $data['model'] ?? 'gpt-3.5-turbo';
        $model = is_string($modelValue) ? $modelValue : 'gpt-3.5-turbo';
        $client = $this->clientSelector->selectClientWithFallback($model, $context);

        if (null === $client) {
            return new StreamedResponse(function () use ($model): void {
                echo 'data: ' . json_encode([
                    'error' => [
                        'message' => 'No available OpenAI client for model: ' . $model,
                        'type' => 'service_unavailable',
                    ],
                ]) . "\n\n";
                flush();
            }, 503);
        }

        $url = $this->buildUrl($client, $endpoint);
        $headers = $this->buildHeaders($client);
        $startTime = microtime(true);

        $response = new StreamedResponse(function () use ($url, $headers, $data, $client, $endpoint, $model, $startTime): void {
            try {
                $this->handleStreamRequest($url, $headers, $data, $client, $endpoint, $model, $startTime);
            } catch (\Exception $e) {
                $this->handleStreamError($e, $client, $endpoint, $model, $startTime);
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $data
     */
    private function handleStreamRequest(
        string $url,
        array $headers,
        array $data,
        OpenAiCompatibleClientInterface $client,
        string $endpoint,
        string $model,
        float $startTime,
    ): void {
        $this->clearOutputBuffer();

        $response = $this->httpClient->request('POST', $url, [
            'headers' => $headers,
            'json' => $data,
            'buffer' => false,
            'timeout' => 60,
        ]);

        $this->setStreamHeaders();
        $hasDone = $this->processStreamChunks($response);
        $this->finishStream($hasDone);

        $latencyMs = (microtime(true) - $startTime) * 1000;
        $this->clientSelector->recordRequest($client, $latencyMs, true);
        $this->logRequest($client, $endpoint, $model, 200, $latencyMs);
    }

    private function handleStreamError(
        \Exception $e,
        OpenAiCompatibleClientInterface $client,
        string $endpoint,
        string $model,
        float $startTime,
    ): void {
        $latencyMs = (microtime(true) - $startTime) * 1000;
        $this->clientSelector->recordRequest($client, $latencyMs, false);

        $this->logger->error('Stream request failed', [
            'client' => $client->getName(),
            'endpoint' => $endpoint,
            'model' => $model,
            'error' => $e->getMessage(),
            'latency_ms' => $latencyMs,
        ]);

        echo 'data: ' . json_encode([
            'error' => [
                'message' => 'Stream request failed',
                'details' => $e->getMessage(),
            ],
        ]) . "\n\n";
        flush();
    }

    private function clearOutputBuffer(): void
    {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    private function setStreamHeaders(): void
    {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
    }

    private function processStreamChunks(ResponseInterface $response): bool
    {
        $hasDone = false;
        foreach ($this->httpClient->stream([$response]) as $r => $chunk) {
            if ($chunk->isTimeout() || $chunk->isFirst()) {
                continue;
            }

            $content = $chunk->getContent();
            if ('' !== $content) {
                echo $content;
                flush();
                $this->flushOutputBuffer();

                if (str_contains($content, '[DONE]')) {
                    $hasDone = true;
                }
            }

            if ($chunk->isLast()) {
                break;
            }
        }

        return $hasDone;
    }

    private function flushOutputBuffer(): void
    {
        if (ob_get_level() > 0) {
            ob_flush();
        }
    }

    private function finishStream(bool $hasDone): void
    {
        if (!$hasDone) {
            echo "data: [DONE]\n\n";
            flush();
        }
    }

    private function logRequest(
        OpenAiCompatibleClientInterface $client,
        string $endpoint,
        string $model,
        int $statusCode,
        float $latencyMs,
    ): void {
        $this->logger->info('OpenAI request forwarded', [
            'client' => $client->getName(),
            'base_url' => $client->getBaseUrl(),
            'endpoint' => $endpoint,
            'model' => $model,
            'status_code' => $statusCode,
            'latency_ms' => round($latencyMs, 2),
        ]);

        $clientKey = $client->getName() . ':' . $model;
        if (!isset($this->clientMetrics[$clientKey])) {
            $this->clientMetrics[$clientKey] = [
                'requests' => 0,
                'total_latency' => 0,
                'successes' => 0,
                'failures' => 0,
            ];
        }

        ++$this->clientMetrics[$clientKey]['requests'];
        $this->clientMetrics[$clientKey]['total_latency'] += $latencyMs;

        if ($statusCode < 400) {
            ++$this->clientMetrics[$clientKey]['successes'];
        } else {
            ++$this->clientMetrics[$clientKey]['failures'];
        }
    }

    /**
     * @return array<string, array{requests: int, success_rate: float, avg_latency_ms: float}>
     */
    public function getMetrics(): array
    {
        $metrics = [];
        foreach ($this->clientMetrics as $key => $data) {
            $avgLatency = $data['requests'] > 0
                ? $data['total_latency'] / $data['requests']
                : 0;

            $metrics[$key] = [
                'requests' => $data['requests'],
                'success_rate' => $data['requests'] > 0
                    ? ($data['successes'] / $data['requests']) * 100
                    : 0,
                'avg_latency_ms' => round($avgLatency, 2),
            ];
        }

        return $metrics;
    }

    /**
     * @return array{total_providers: int, total_clients: int, healthy_clients: int, clients: array<array{name: string, provider: string, base_url: string, models: mixed, is_healthy: bool, stats: mixed}>}
     */
    public function getPoolStatus(): array
    {
        return $this->clientSelector->getPoolStatus();
    }
}
