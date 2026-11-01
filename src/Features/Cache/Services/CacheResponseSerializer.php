<?php

namespace Bitsnio\AsasFlow\Features\Cache\Services;

use Bitsnio\AsasFlow\Foundation\Http\ApiResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CacheResponseSerializer
{
    public function serialize(SymfonyResponse $response): array
    {
        $etag = $this->generateEtag($response);

        if (config('asasflow-cache.headers.etag', true)) {
            $response->headers->set('ETag', $etag);
        }

        $isJson = $response instanceof JsonResponse
            || str_contains((string) $response->headers->get('Content-Type'), 'application/json');

        return [
            'content' => (string) $response->getContent(),
            'status_code' => $response->getStatusCode(),
            'headers' => $this->serializeHeaders($response->headers->all()),
            'is_json' => $isJson,
            'cached_at' => now()->toIso8601String(),
            'etag' => $etag,
        ];
    }

    public function deserialize(array $data): SymfonyResponse
    {
        $content = (string) ($data['content'] ?? '');
        $status = (int) ($data['status_code'] ?? 200);
        $headers = is_array($data['headers'] ?? null) ? $data['headers'] : [];
        $isJson = (bool) ($data['is_json'] ?? false);

        if ($isJson) {
            $response = JsonResponse::fromJsonString($content, $status, $headers);
        } else {
            $response = new Response($content, $status, $headers);
        }

        if (!empty($data['etag']) && config('asasflow-cache.headers.etag', true)) {
            $response->headers->set('ETag', (string) $data['etag']);
        }

        $response->headers->set('X-AsasFlow-Cache', 'HIT');

        return $response;
    }

    public function generateEtag(SymfonyResponse $response): string
    {
        return md5((string) $response->getContent() . $response->getStatusCode());
    }

    public function isNotModified(Request $request, string $etag): bool
    {
        if ($etag === '') {
            return false;
        }

        $ifNoneMatch = $request->header('If-None-Match');
        if (!$ifNoneMatch) {
            return false;
        }

        return trim((string) $ifNoneMatch, '" ') === trim($etag, '" ');
    }

    protected function serializeHeaders(array $headers): array
    {
        $modelTagHeader = strtolower(ApiResponseFactory::MODEL_TAGS_HEADER);

        unset(
            $headers['connection'],
            $headers['keep-alive'],
            $headers['transfer-encoding'],
            $headers[$modelTagHeader],
            $headers['x-asasflow-cache'],
        );

        return $headers;
    }
}
