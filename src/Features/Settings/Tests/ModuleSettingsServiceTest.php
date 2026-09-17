<?php

declare(strict_types=1);

use Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsService;
use Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsRegistry;

beforeEach(function () {
    app(\Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsDiscovery::class)
        ->discover();
});

it("returns module settings with defaults", function () {
    $registry = app(ModuleSettingsRegistry::class);
    $service = app(ModuleSettingsService::class);

    foreach ($registry->all() as $module => $configKey) {
        $definitions = $service->definitions($module);
        $values = $service->all($module);

        expect($values)->toBeArray();

        foreach ($definitions as $key => $definition) {
            $normalized = is_array($definition)
                ? array_merge([
                    "default" => null,
                    "scope" => "module",
                ], $definition)
                : [
                    "default" => $definition,
                    "scope" => "module",
                ];

            expect($values)
                ->toHaveKey($key);

            expect($values[$key])
                ->toBe($normalized["default"]);
        }
    }
});

it("can retrieve a single setting", function () {
    $registry = app(ModuleSettingsRegistry::class);
    $service = app(ModuleSettingsService::class);

    foreach ($registry->all() as $module => $configKey) {
        $definitions = $service->definitions($module);

        foreach ($definitions as $key => $definition) {
            $value = $service->get($module, $key);

            expect($value)->not->toBeNull();
        }
    }
});

it("returns the supplied fallback for an unknown setting key", function () {
    $registry = app(ModuleSettingsRegistry::class);
    $service = app(ModuleSettingsService::class);

    foreach ($registry->all() as $module => $configKey) {
        expect(
            $service->get(
                $module,
                "__non_existing_setting__",
                "fallback-value"
            )
        )->toBe("fallback-value");
    }
});

it("rejects an unknown setting during update", function () {
    $registry = app(ModuleSettingsRegistry::class);
    $service = app(ModuleSettingsService::class);

    foreach ($registry->all() as $module => $configKey) {
        expect(fn () => $service->update(
            $module,
            [
                "__non_existing_setting__" => "test",
            ]
        ))->toThrow(
            \InvalidArgumentException::class
        );
    }
});

it("returns a complete schema", function () {
    $registry = app(ModuleSettingsRegistry::class);
    $service = app(ModuleSettingsService::class);

    foreach ($registry->all() as $module => $configKey) {
        $schema = $service->schema($module);

        expect($schema)->toBeArray();

        foreach ($schema as $key => $definition) {
            expect($definition)
                ->toHaveKey("key")
                ->toHaveKey("value")
                ->toHaveKey("default")
                ->toHaveKey("scope");
        }
    }
});
