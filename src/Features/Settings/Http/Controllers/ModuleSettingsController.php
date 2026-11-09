<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Features\Settings\Http\Controllers;

use Bitsnio\AsasFlow\Features\Settings\Http\Requests\UpdateModuleSettingsRequest;
use Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsDiscovery;
use Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsRegistry;
use Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsService;
use Bitsnio\AsasFlow\Features\Tenancy\Services\TenantContext;
use Bitsnio\AsasFlow\Foundation\Http\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use InvalidArgumentException;

class ModuleSettingsController extends Controller
{
    use JsonResponseTrait;

    public function __construct(
        protected ModuleSettingsService $settings,
        protected ModuleSettingsRegistry $registry,
        protected ModuleSettingsDiscovery $discovery,
    ) {
    }

    /**
     * Get all registered modules that have settings (and optionally their settings).
     */
    public function index(Request $request): JsonResponse
    {
        if ($this->registry->all() === []) {
            $this->discovery->discover();
        }

        $modules = array_keys($this->registry->all());
        [$companyId, $siteId] = $this->resolveScopeContext($request);

        if ($request->filled('module')) {
            return $this->show($request, (string) $request->query('module'));
        }

        if ($request->boolean('with_settings', false)) {
            $allSettings = [];
            foreach ($modules as $module) {
                $allSettings[$module] = $this->settings->schema($module, $companyId, $siteId);
            }

            return $this->successResponse([
                'modules' => $modules,
                'settings' => $allSettings,
            ], 'Module settings retrieved successfully.');
        }

        return $this->successResponse([
            'modules' => $modules,
        ], 'Settings modules retrieved successfully.');
    }

    /**
     * Get settings schema and resolved values for one module.
     */
    public function show(
        Request $request,
        string $module
    ): JsonResponse {
        [$companyId, $siteId] = $this->resolveScopeContext($request);

        try {
            $schema = $this->settings->schema(
                $module,
                $companyId,
                $siteId
            );

            return $this->successResponse([
                'module' => strtolower($module),
                'settings' => $schema,
            ], 'Module settings retrieved successfully.');
        } catch (InvalidArgumentException $e) {
            return $this->notFoundResponse($e->getMessage());
        }
    }

    /**
     * Update settings for one module.
     */
    public function update(
        UpdateModuleSettingsRequest $request,
        string $module
    ): JsonResponse {
        [$companyId, $siteId] = $this->resolveScopeContext($request);

        $values = $request->input(
            'settings',
            []
        );

        try {
            $settings = $this->settings->update(
                $module,
                is_array($values) ? $values : [],
                $companyId,
                $siteId
            );

            return $this->successResponse([
                'module' => strtolower($module),
                'settings' => $settings,
            ], 'Settings updated successfully.');
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    protected function resolveScopeContext(Request $request): array
    {
        $companyId = $request->filled('company_id')
            ? (int) $request->input('company_id')
            : ($request->filled('tenant_id') ? (int) $request->input('tenant_id') : null);

        $siteId = $request->filled('site_id')
            ? (int) $request->input('site_id')
            : null;

        if ($companyId === null && config('asasflow.tenancy.enabled', false)) {
            $tenantId = TenantContext::getTenantId()
                ?? auth('api')->user()?->tenant_id
                ?? auth()->user()?->tenant_id;

            if ($tenantId !== null && is_numeric($tenantId)) {
                $companyId = (int) $tenantId;
            }
        }

        if ($siteId === null && config('asasflow.tenancy.enabled', false) && config('asasflow.tenancy.site_enabled', true)) {
            $resolvedSiteId = TenantContext::getSiteId()
                ?? auth('api')->user()?->site_id
                ?? auth()->user()?->site_id;

            if ($resolvedSiteId !== null && is_numeric($resolvedSiteId)) {
                $siteId = (int) $resolvedSiteId;
            }
        }

        return [$companyId, $siteId];
    }
}