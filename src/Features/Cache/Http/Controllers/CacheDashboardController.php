<?php

namespace Bitsnio\AsasFlow\Features\Cache\Http\Controllers;

use Bitsnio\AsasFlow\Features\Cache\Services\CacheManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CacheDashboardController
{
    public function __construct(
        protected CacheManager $cacheManager,
    ) {}

    public function index(): Response
    {
        $stats = $this->cacheManager->getStats();
        $entries = $this->cacheManager->getEntries();

        return response()->view('asasflow-cache::dashboard', [
            'stats' => $stats,
            'entries' => $entries,
        ]);
    }

    public function stats(): JsonResponse
    {
        return response()->json($this->cacheManager->getStats());
    }

    public function entries(): JsonResponse
    {
        return response()->json([
            'entries' => $this->cacheManager->getEntries(),
        ]);
    }

    public function clear(Request $request): JsonResponse
    {
        $this->cacheManager->flush();

        return response()->json([
            'success' => true,
            'message' => 'Cache cleared',
        ]);
    }
}
