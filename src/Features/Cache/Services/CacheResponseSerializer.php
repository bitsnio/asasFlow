<?php

namespace Bitsnio\AsasFlow\Features\Cache\Services;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CacheResponseSerializer
{
    public function serialize(SymfonyResponse $response): array
    {
        return [
            'content' => $response->getContent(),
            'status_code' => $response->getStatusCode(),
            'headers' => $this->serializeHeaders($response->headers->all()),
            'cached_at' => now()->toIso8601String(),
            'etag' => $this->generateEtag($response),
        ];
    }

    public function deserialize(array $data): SymfonyResponse
    {
        return new Response(
            $data['content'] ?? '',
            $data['status_code'] ?? 200,
            $data['headers'] ?? []
        );
    }

    public function generateEtag(SymfonyResponse $response): string
    {
        return md5($response->getContent() . $response->getStatusCode());
    }

    public function isNotModified(Request $request, string $etag): bool
    {
        $ifNoneMatch = $request->header('If-None-Match');
        return $ifNoneMatch && $ifNoneMatch === $etag;
    }

    protected function serializeHeaders(array $headers): array
    {
        unset($headers['connection'], $headers['keep-alive'], $headers['transfer-encoding']);
        return $headers;
    }
}
