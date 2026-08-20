<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Core\Services;

use Bitsnio\AsasFlow\Core\Repositories\ModuleSettingsRepository;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class ModuleSettingsService
{
    public function __construct(
        protected ModuleSettingsDiscovery $discovery,
        protected ModuleSettingsRepository $repository,
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | Get one setting
    |--------------------------------------------------------------------------
    */

    public function get(
        string $module,
        string $key,
        mixed $default = null,
        ?int $companyId = null,
        ?int $siteId = null
    ): mixed {
        $settings = $this->all(
            $module,
            $companyId,
            $siteId
        );

        return data_get(
            $settings,
            $key,
            $default
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Get all effective settings
    |--------------------------------------------------------------------------
    */

    public function all(
        string $module,
        ?int $companyId = null,
        ?int $siteId = null
    ): array {
        $cacheKey = $this->cacheKey(
            $module,
            $companyId,
            $siteId
        );

        return Cache::remember(
            $cacheKey,
            now()->addHours(24),
            fn () => $this->resolve(
                $module,
                $companyId,
                $siteId
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve effective values
    |--------------------------------------------------------------------------
    */

    protected function resolve(
        string $module,
        ?int $companyId,
        ?int $siteId
    ): array {
        $definitions = $this->definitions($module);

        $moduleValues = $this->repository->values(
            $module
        );

        $companyValues = [];

        if ($companyId !== null) {
            $companyValues = $this->repository->values(
                $module,
                $companyId
            );
        }

        $siteValues = [];

        if ($companyId !== null && $siteId !== null) {
            $siteValues = $this->repository->values(
                $module,
                $companyId,
                $siteId
            );
        }

        $result = [];

        foreach ($definitions as $key => $definition) {

            $definition = $this->normalizeDefinition(
                $key,
                $definition
            );

            $scope = $definition['scope'];

            /*
             * Module-level:
             *
             * module DB override
             * otherwise default
             */
            if ($scope === 'module') {

                $result[$key] = array_key_exists(
                    $key,
                    $moduleValues
                )
                    ? $moduleValues[$key]
                    : $definition['default'];

                continue;
            }

            /*
             * Company-level:
             *
             * company override
             * otherwise module override
             * otherwise default
             */
            if ($scope === 'company') {

                if (array_key_exists($key, $companyValues)) {
                    $result[$key] = $companyValues[$key];

                } elseif (array_key_exists($key, $moduleValues)) {
                    $result[$key] = $moduleValues[$key];

                } else {
                    $result[$key] = $definition['default'];
                }

                continue;
            }

            /*
             * Site-level:
             *
             * site override
             * company override
             * module override
             * default
             */
            if ($scope === 'site') {

                if (array_key_exists($key, $siteValues)) {
                    $result[$key] = $siteValues[$key];

                } elseif (array_key_exists($key, $companyValues)) {
                    $result[$key] = $companyValues[$key];

                } elseif (array_key_exists($key, $moduleValues)) {
                    $result[$key] = $moduleValues[$key];

                } else {
                    $result[$key] = $definition['default'];
                }
            }
        }

        return $result;
    }

    /*
    |--------------------------------------------------------------------------
    | Update settings
    |--------------------------------------------------------------------------
    */

    public function update(
        string $module,
        array $values,
        ?int $companyId = null,
        ?int $siteId = null
    ): array {
        $definitions = $this->definitions($module);

        $existing = $this->repository->values(
            $module,
            $companyId,
            $siteId
        );

        foreach ($values as $key => $value) {

            if (!array_key_exists($key, $definitions)) {
                throw new InvalidArgumentException(
                    "Unknown setting [{$module}.{$key}]."
                );
            }

            $definition = $this->normalizeDefinition(
                $key,
                $definitions[$key]
            );

            $this->ensureScopeMatches(
                $key,
                $definition['scope'],
                $companyId,
                $siteId
            );

            $existing[$key] = $value;
        }

        $this->repository->save(
            $module,
            $existing,
            $companyId,
            $siteId
        );

        $this->forget(
            $module,
            $companyId,
            $siteId
        );

        return $this->all(
            $module,
            $companyId,
            $siteId
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Get definitions
    |--------------------------------------------------------------------------
    */

    public function definitions(string $module): array
    {
        return $this->discovery->settings($module);
    }

    /*
    |--------------------------------------------------------------------------
    | Frontend-friendly response
    |--------------------------------------------------------------------------
    */

    public function schema(
        string $module,
        ?int $companyId = null,
        ?int $siteId = null
    ): array {
        $definitions = $this->definitions($module);

        $values = $this->all(
            $module,
            $companyId,
            $siteId
        );

        $result = [];

        foreach ($definitions as $key => $definition) {

            $definition = $this->normalizeDefinition(
                $key,
                $definition
            );

            $result[$key] = array_merge(
                $definition,
                [
                    'key' => $key,
                    'value' => $values[$key] ?? null,
                ]
            );
        }

        return $result;
    }

    /*
    |--------------------------------------------------------------------------
    | Normalize definition
    |--------------------------------------------------------------------------
    */

    protected function normalizeDefinition(
        string $key,
        mixed $definition
    ): array {
        /*
         * Simplest possible form:
         *
         * 'foo' => 'bar'
         *
         * becomes:
         *
         * [
         *     'default' => 'bar',
         *     'scope' => 'module',
         * ]
         */
        if (!is_array($definition)) {
            return [
                'default' => $definition,
                'scope' => 'module',
            ];
        }

        return array_merge(
            [
                'label' => $key,
                'description' => null,
                'type' => null,
                'input' => null,
                'default' => null,
                'options' => [],
                'rules' => [],
                'scope' => 'module',
            ],
            $definition
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Validate scope
    |--------------------------------------------------------------------------
    */

    protected function ensureScopeMatches(
        string $key,
        string $scope,
        ?int $companyId,
        ?int $siteId
    ): void {
        if ($scope === 'module') {

            if ($companyId !== null || $siteId !== null) {
                throw new InvalidArgumentException(
                    "Setting [{$key}] is module-level and cannot be overridden per company/site."
                );
            }

            return;
        }

        if ($scope === 'company') {

            if ($companyId === null || $siteId !== null) {
                throw new InvalidArgumentException(
                    "Setting [{$key}] requires a company scope."
                );
            }

            return;
        }

        if ($scope === 'site') {

            if ($companyId === null || $siteId === null) {
                throw new InvalidArgumentException(
                    "Setting [{$key}] requires a site scope."
                );
            }

            return;
        }

        throw new InvalidArgumentException(
            "Invalid scope [{$scope}] for setting [{$key}]."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */

    protected function cacheKey(
        string $module,
        ?int $companyId,
        ?int $siteId
    ): string {
        return sprintf(
            'module-settings:%s:%s:%s',
            $module,
            $companyId ?? 'global',
            $siteId ?? 'global'
        );
    }

    public function forget(
        string $module,
        ?int $companyId = null,
        ?int $siteId = null
    ): void {
        Cache::forget(
            $this->cacheKey(
                $module,
                $companyId,
                $siteId
            )
        );
    }
}