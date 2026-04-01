<?php

declare(strict_types=1);

namespace App\Prism\Providers;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use JsonException;
use Prism\Prism\Contracts\Message;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Providers\Provider;
use Prism\Prism\Structured\Request as StructuredRequest;
use Prism\Prism\Structured\Response as StructuredResponse;
use Prism\Prism\Text\Request as TextRequest;
use Prism\Prism\Text\Response as TextResponse;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\ToolResultMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

final class LMStudioProvider extends Provider
{
    protected string $baseUrl;

    protected ?string $apiKey;

    protected int $timeout;

    public function __construct(
        ?string $baseUrl = null,
        ?string $apiKey = null,
        int $timeout = 120
    ) {
        $this->baseUrl = rtrim($baseUrl ?? config('prism.providers.lmstudio.url', 'http://localhost:1234/v1'), '/');
        $this->apiKey = $apiKey ?? config('prism.providers.lmstudio.api_key', '');
        $this->timeout = $timeout;
    }

    public function text(TextRequest $request): TextResponse
    {
        $body = $this->buildTextRequestBody($request);

        $response = $this->makeRequest($body);
        $data = $response->json();

        $text = $data['choices'][0]['message']['content'] ?? '';
        $usage = $this->buildUsage($data['usage'] ?? []);
        $finishReason = $this->mapFinishReason($data['choices'][0]['finish_reason'] ?? 'stop');

        return new TextResponse(
            steps: new Collection(),
            text: $text,
            finishReason: $finishReason,
            toolCalls: [],
            toolResults: [],
            usage: $usage,
            meta: new Meta(
                id: $data['id'] ?? uniqid('lm_'),
                model: $request->model()
            ),
            messages: new Collection()
        );
    }

    public function structured(StructuredRequest $request): StructuredResponse
    {
        $body = $this->buildStructuredRequestBody($request);

        $response = $this->makeRequest($body);
        $data = $response->json();

        $text = $data['choices'][0]['message']['content'] ?? '';
        $structured = $this->parseStructuredResponse($text);
        $usage = $this->buildUsage($data['usage'] ?? []);
        $finishReason = $this->mapFinishReason($data['choices'][0]['finish_reason'] ?? 'stop');

        return new StructuredResponse(
            steps: new Collection(),
            text: $text,
            structured: $structured,
            finishReason: $finishReason,
            usage: $usage,
            meta: new Meta(
                id: $data['id'] ?? uniqid('lm_'),
                model: $request->model()
            )
        );
    }

    protected function buildTextRequestBody(TextRequest $request): array
    {
        $body = [
            'model' => $request->model(),
            'messages' => $this->mapMessages(array_merge(
                $request->systemPrompts(),
                $request->messages()
            )),
        ];

        if ($request->maxTokens()) {
            $body['max_tokens'] = $request->maxTokens();
        }

        if ($request->temperature()) {
            $body['temperature'] = $request->temperature();
        }

        if ($request->topP()) {
            $body['top_p'] = $request->topP();
        }

        return $body;
    }

    protected function buildStructuredRequestBody(StructuredRequest $request): array
    {
        $body = [
            'model' => $request->model(),
            'messages' => $this->mapMessages(array_merge(
                $request->systemPrompts(),
                $request->messages()
            )),
            'response_format' => ['type' => 'json_object'],
        ];

        if ($request->maxTokens()) {
            $body['max_tokens'] = $request->maxTokens();
        }

        if ($request->temperature()) {
            $body['temperature'] = $request->temperature();
        }

        return $body;
    }

    /**
     * @param  array<int, Message>  $messages
     * @return array<int, array{role: string, content: string}>
     */
    protected function mapMessages(array $messages): array
    {
        return array_map(function (Message $message): array {
            return match (true) {
                $message instanceof SystemMessage => [
                    'role' => 'system',
                    'content' => $message->content,
                ],
                $message instanceof UserMessage => [
                    'role' => 'user',
                    'content' => $message->text(),
                ],
                $message instanceof AssistantMessage => [
                    'role' => 'assistant',
                    'content' => $message->content,
                ],
                $message instanceof ToolResultMessage => [
                    'role' => 'tool',
                    'content' => is_string($message->toolResults[0]->result ?? null)
                        ? $message->toolResults[0]->result
                        : json_encode($message->toolResults[0]->result ?? ''),
                ],
                default => [
                    'role' => 'user',
                    'content' => (string) $message,
                ],
            };
        }, $messages);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected function makeRequest(array $body): Response
    {
        $http = Http::timeout($this->timeout)
            ->acceptJson();

        if (! empty($this->apiKey)) {
            $http->withToken($this->apiKey);
        }

        /** @var Response $response */
        $response = $http->post($this->baseUrl.'/chat/completions', $body);

        return $response;
    }

    protected function parseStructuredResponse(string $text): ?array
    {
        $text = trim($text);

        if (str_starts_with($text, '```json')) {
            $text = mb_substr($text, 7);
        }
        if (str_starts_with($text, '```')) {
            $text = mb_substr($text, 3);
        }
        if (str_ends_with(trim($text), '```')) {
            $text = mb_substr(trim($text), 0, -3);
        }

        $text = trim($text);

        try {
            $decoded = json_decode($text, true, 512, JSON_THROW_ON_ERROR);

            if (is_array($decoded)) {
                if (isset($decoded['score'], $decoded['notes'])) {
                    return $decoded;
                }

                if (isset($decoded['correction']['score'], $decoded['correction']['notes'])) {
                    return $decoded['correction'];
                }

                foreach ($decoded as $value) {
                    if (is_array($value) && isset($value['score'], $value['notes'])) {
                        return $value;
                    }
                }
            }

            return $decoded;
        } catch (JsonException $e) {
            return null;
        }
    }

    protected function buildUsage(array $data): Usage
    {
        return new Usage(
            promptTokens: $data['prompt_tokens'] ?? 0,
            completionTokens: $data['completion_tokens'] ?? 0,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function mapFinishReason(string $reason): FinishReason
    {
        return match ($reason) {
            'stop' => FinishReason::Stop,
            'length' => FinishReason::Length,
            'content_filter' => FinishReason::ContentFilter,
            'tool_calls' => FinishReason::ToolCalls,
            'error' => FinishReason::Error,
            default => FinishReason::Other,
        };
    }
}
