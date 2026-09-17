<?php

declare(strict_types=1);

use Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsDiscovery;
use Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsRegistry;
use Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsService;

beforeEach(function () {
    app(ModuleSettingsDiscovery::class)->discover();
});

it("discovers modules that contain a settings.php file", function () {
    $registry = app(ModuleSettingsRegistry::class);

    expect($registry->all())
        ->toBeArray();
});

it("registers modules with settings definitions", function () {
    $registry = app(ModuleSettingsRegistry::class);

    foreach ($registry->all() as $module => $configKey) {
        expect($module)->toBeString()
            ->and($configKey)->toBeString();

        expect(
            app(ModuleSettingsService::class)
                ->definitions($module)
        )->toBeArray();
    }
});

it("loads settings definitions from module settings.php", function () {
    $registry = app(ModuleSettingsRegistry::class);
    $service = app(ModuleSettingsService::class);

    foreach ($registry->all() as $module => $configKey) {
        $definitions = $service->definitions($module);

        expect($definitions)->toBeArray();

        foreach ($definitions as $key => $definition) {
            expect($key)->toBeString();

            if (is_array($definition)) {
                expect($definition)->toBeArray();
            }
        }
    }
});
