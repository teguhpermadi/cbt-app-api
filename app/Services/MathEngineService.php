<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class MathEngineService
{
    private string $baseUrl;

    private int $timeout;

    private int $retryAttempts;

    private int $retryDelay;

    public function __construct()
    {
        $this->baseUrl = config('mathengine.url', 'http://localhost:8001');
        $this->timeout = (int) config('mathengine.timeout', 30);
        $this->retryAttempts = (int) config('mathengine.retry.attempts', 2);
        $this->retryDelay = (int) config('mathengine.retry.delay', 1);
    }

    /**
     * Generate soal matematika via Python Math Engine.
     *
     * @param  string  $endpoint  API endpoint (e.g., 'arithmetic/generate')
     * @param  array<string, mixed>  $payload
     * @return array{status: string, data: mixed}
     *
     * @throws \RuntimeException
     */
    public function generate(string $endpoint, array $payload): array
    {
        $url = rtrim($this->baseUrl, '/').'/api/v1/'.$endpoint;

        $response = $this->makeRequest('POST', $url, $payload);

        if (! $response->successful()) {
            Log::error('Math Engine generation failed', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $message = $this->parseEngineError($response->status(), $response->body());

            throw new \RuntimeException($message, $response->status());
        }

        /** @var array{status: string, data: mixed} */
        return $response->json();
    }

    /**
     * Get level configuration from Math Engine.
     */
    public function getLevelInfo(int $level): array
    {
        $cacheKey = "mathengine.level.{$level}";

        /** @var array{level: int, allowed_number_types: list<string>, max_value: int, max_operations: int, allow_parentheses: bool} */
        return Cache::remember($cacheKey, 3600, function () use ($level): array {
            $url = rtrim($this->baseUrl, '/').'/api/v1/arithmetic/levels/'.$level;

            $response = $this->makeRequest('GET', $url);

            if (! $response->successful()) {
                throw new \RuntimeException(
                    'Math Engine level info failed: '.$response->body()
                );
            }

            return $response->json();
        });
    }

    /**
     * Check health of Math Engine.
     */
    public function healthCheck(): bool
    {
        try {
            $url = rtrim($this->baseUrl, '/').'/health';
            $response = $this->makeRequest('GET', $url);

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Get available domains and their configurations.
     */
    public function getDomains(): array
    {
        return config('mathengine.domains', []);
    }

    /**
     * Get level-difficulty mapping.
     *
     * @return array<int, string>
     */
    public function getLevelDifficultyMap(): array
    {
        return config('mathengine.level_difficulty_map', []);
    }

    /**
     * Map engine level to CBT difficulty.
     */
    public function mapLevelToDifficulty(int $level): string
    {
        $map = $this->getLevelDifficultyMap();

        return $map[$level] ?? 'sedang';
    }

    /**
     * Make HTTP request with retry logic.
     *
     * @param  string  $method
     * @param  string  $url
     * @param  array<string, mixed>|null  $payload
     */
    private function makeRequest(string $method, string $url, ?array $payload = null): Response
    {
        $lastException = null;

        for ($attempt = 0; $attempt <= $this->retryAttempts; $attempt++) {
            try {
                $http = $this->createPendingRequest();

                /** @var Response $response */
                $response = match ($method) {
                    'GET' => $http->get($url),
                    'POST' => $http->post($url, $payload),
                    default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
                };

                // Success or client error (4xx) - don't retry
                if ($response->successful() || $response->clientError()) {
                    return $response;
                }

                // Server error (5xx) - retry
                if ($response->serverError() && $attempt < $this->retryAttempts) {
                    Log::warning('Math Engine server error, retrying', [
                        'url' => $url,
                        'status' => $response->status(),
                        'attempt' => $attempt + 1,
                    ]);
                    usleep($this->retryDelay * 1000 * (1 << $attempt)); // Exponential backoff

                    continue;
                }

                return $response;
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $lastException = $e;

                if ($attempt < $this->retryAttempts) {
                    Log::warning('Math Engine connection failed, retrying', [
                        'url' => $url,
                        'attempt' => $attempt + 1,
                        'error' => $e->getMessage(),
                    ]);
                    usleep($this->retryDelay * 1000 * (1 << $attempt));

                    continue;
                }
            }
        }

        throw new \RuntimeException(
            'Math Engine connection failed after '.($this->retryAttempts + 1).' attempts: '
            .($lastException?->getMessage() ?? 'Unknown error')
        );
    }

    /**
     * Create a configured PendingRequest.
     */
    private function createPendingRequest(): PendingRequest
    {
        return Http::timeout($this->timeout)
            ->acceptJson()
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]);
    }

    /**
     * Parse Math Engine error response into a human-readable message.
     */
    private function parseEngineError(int $status, string $body): string
    {
        $json = json_decode($body, true);

        if (! is_array($json)) {
            return "Math Engine error ({$status})";
        }

        // MathEngineError format: {"status":"error","error_code":"...","message":"..."}
        if (isset($json['message']) && isset($json['error_code'])) {
            return $json['message'];
        }

        // FastAPI HTTPException: {"detail":"..."} or {"detail":[{"msg":"..."}]}
        if (isset($json['detail'])) {
            $detail = $json['detail'];

            if (is_string($detail)) {
                return $detail;
            }

            if (is_array($detail) && ! empty($detail[0]['msg'])) {
                return $detail[0]['msg'];
            }
        }

        return "Math Engine error ({$status})";
    }
}
