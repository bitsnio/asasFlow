<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Core\Http\Controllers;

use Bitsnio\AsasFlow\Core\Http\Requests\UpdateModuleSettingsRequest;
use Bitsnio\AsasFlow\Core\Services\ModuleSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class ModuleSettingsController extends Controller
{
    public function __construct(
        protected ModuleSettingsService $settings
    ) {
    }

    public function index(
        string $module
    ): JsonResponse {
        return response()->json([
            'module' => $module,
            'settings' => $this->settings->schema(
                $module
            ),
        ]);
    }

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

        return response()->json([
            'module' => $module,
            'settings' => $this->settings->update(
                $module,
                $values,
                $companyId,
                $siteId
            ),
        ]);
    }
}