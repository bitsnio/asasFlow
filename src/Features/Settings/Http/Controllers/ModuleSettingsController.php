<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Features\Settings\Http\Controllers;

use Bitsnio\AsasFlow\Features\Settings\Http\Requests\UpdateModuleSettingsRequest;
use Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsRegistry;
use Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class ModuleSettingsController extends Controller
{
    public function __construct(
        protected ModuleSettingsService $settings,
        protected ModuleSettingsRegistry $registry,
    ) {
    }

    /**
     * Get all registered modules that have settings.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'modules' => array_keys(
                $this->registry->all()
            ),
        ]);
    }

    /**
     * Get settings schema and resolved values for one module.
     */
    public function show(
        string $module
    ): JsonResponse {
        return response()->json([
            'module' => $module,

            'settings' => $this->settings->schema(
                $module
            ),
        ]);
    }

    /**
     * Update settings for one module.
     */
    public function update(
        UpdateModuleSettingsRequest $request,
        string $module
    ): JsonResponse {

        $companyId = $request->input('company_id');

        $siteId = $request->input('site_id');

        $values = $request->input(
            'settings',
            []
        );

        $settings = $this->settings->update(
            $module,
            $values,
            $companyId,
            $siteId
        );

        return response()->json([
            'message' => 'Settings updated successfully.',

            'module' => $module,

            'settings' => $settings,
        ]);
    }
}