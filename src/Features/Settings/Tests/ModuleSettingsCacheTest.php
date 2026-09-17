<?php

declare(strict_types=1);

use Bitsnio\AsasFlow\Features\Settings\Repositories\ModuleSettingsRepository;
use Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsDiscovery;
use Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsRegistry;
use Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsService;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    app(ModuleSettingsDiscovery::class)->discover();

    Cache::flush();
});

it("caches resolved settings", function () {
    $registry = app(ModuleSettingsRegistry::class);
    $service = app(ModuleSettingsService::class);

    foreach ($registry->all() as $module => $configKey) {
        $first = $service->all($module);

        expect($first)->toBeArray();

        $second = $service->all($module);

        expect($second)->toBe($first);
    }
});

it("forgets the cache after an update", function () {
    $registry = app(ModuleSettingsRegistry::class);
    $service = app(ModuleSettingsService::class);

    foreach ($registry->all() as $module => $configKey) {
        $definitions = $service->definitions($module);

        foreach ($definitions as $key => $definition) {
            $definition = is_array($definition)
                ? array_merge([
                    "scope" => "module",
                    "default" => null,
                ], $definition)
                : [
                    "scope" => "module",
                    "default" => $definition,
                ];

            if ($definition["scope"] !== "module") {
                continue;
            }

            $newValue = "__cache_test__";

            $service->update(
                $module,
                [
                    $key => $newValue,
                ]
            );

            expect(
                $service->get($module, $key)
            )->toBe($newValue);

            return;
        }
    }

    $this->markTestSkipped(
        "No module-level settings are available."
    );
});

it("isolates cache entries by company and site", function () {
    $service = app(ModuleSettingsService::class);
    $registry = app(ModuleSettingsRegistry::class);

    foreach ($registry->all() as $module => $configKey) {
        $companyValues = $service->all(
            $module,
            100
        );

        $siteValues = $service->all(
            $module,
            100,
            200
        );

        expect($companyValues)->toBeArray()
            ->and($siteValues)->toBeArray();

        expect($siteValues)->not->toBeSameAs($companyValues);
    }
});
