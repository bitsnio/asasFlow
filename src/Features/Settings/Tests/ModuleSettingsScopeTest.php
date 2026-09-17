<?php

declare(strict_types=1);

use Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsDiscovery;
use Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsRegistry;
use Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsService;

beforeEach(function () {
    app(ModuleSettingsDiscovery::class)->discover();
});

it("supports module level settings", function () {
    $registry = app(ModuleSettingsRegistry::class);
    $service = app(ModuleSettingsService::class);

    foreach ($registry->all() as $module => $configKey) {
        $definitions = $service->definitions($module);

        foreach ($definitions as $key => $definition) {
            $definition = is_array($definition)
                ? array_merge([
                    "scope" => "module",
                ], $definition)
                : [
                    "scope" => "module",
                ];

            if ($definition["scope"] !== "module") {
                continue;
            }

            $result = $service->update(
                $module,
                [
                    $key => $definition["default"],
                ]
            );

            expect($result)
                ->toHaveKey($key);
        }
    }
});

it("rejects company scope for module level settings", function () {
    $registry = app(ModuleSettingsRegistry::class);
    $service = app(ModuleSettingsService::class);

    foreach ($registry->all() as $module => $configKey) {
        $definitions = $service->definitions($module);

        foreach ($definitions as $key => $definition) {
            $definition = is_array($definition)
                ? array_merge([
                    "scope" => "module",
                ], $definition)
                : [
                    "scope" => "module",
                ];

            if ($definition["scope"] !== "module") {
                continue;
            }

            expect(fn () => $service->update(
                $module,
                [
                    $key => $definition["default"],
                ],
                1
            ))->toThrow(\InvalidArgumentException::class);
        }
    }
});

it("rejects site scope without a company", function () {
    $registry = app(ModuleSettingsRegistry::class);
    $service = app(ModuleSettingsService::class);

    foreach ($registry->all() as $module => $configKey) {
        $definitions = $service->definitions($module);

        foreach ($definitions as $key => $definition) {
            $definition = is_array($definition)
                ? array_merge([
                    "scope" => "module",
                ], $definition)
                : [
                    "scope" => "module",
                ];

            if ($definition["scope"] !== "site") {
                continue;
            }

            expect(fn () => $service->update(
                $module,
                [
                    $key => $definition["default"],
                ],
                null,
                1
            ))->toThrow(\InvalidArgumentException::class);
        }
    }
});
