<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Core\Http\Controllers;

use Bitsnio\AsasFlow\Core\Http\Requests\UpdateModuleSettingsRequest;
use Bitsnio\AsasFlow\Core\Services\ModuleSettingsRegistry;
use Bitsnio\AsasFlow\Core\Services\ModuleSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class ModuleSettingsController extends Controller
{
    public function __construct(
        protected ModuleSettingsService $service,
        protected ModuleSettingsRegistry $registry,
    ) {
    }

    /**
     * GET /api/settings
     *
     * Return settings for all modules.
     */
    public function index(): JsonResponse
    {
        $data = [];

        foreach ($this->registry->all() as $module => $class) {
            $data[] = $this->service->get($module);
        }

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * GET /api/settings/{module}
     *
     * Return settings for one module.
     */
    public function show(string $module): JsonResponse
    {
        return response()->json([
            'data' => $this->service->get($module),
        ]);
    }

    /**
     * PUT /api/settings/{module}
     *
     * Update settings for one module.
     */
    public function update(
        UpdateModuleSettingsRequest $request,
        string $module
    ): JsonResponse {
        $this->service->update(
            $module,
            $request->validated()
        );

        return response()->json([
            'message' => 'Settings updated successfully.',
            'data' => $this->service->get($module),
        ]);
    }
}