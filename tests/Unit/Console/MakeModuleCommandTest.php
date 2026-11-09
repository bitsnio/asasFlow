<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->tempModulesPath = sys_get_temp_dir() . '/asasflow-make-module-test-' . uniqid();
    mkdir($this->tempModulesPath, 0777, true);
    config(['asasflow.modules.path' => $this->tempModulesPath]);
});

afterEach(function () {
    if (is_dir($this->tempModulesPath)) {
        File::deleteDirectory($this->tempModulesPath);
    }
});

it('creates a single module', function () {
    $exitCode = Artisan::call('asasflow:make-module', ['name' => ['Blog']]);

    expect($exitCode)->toBe(0);
    expect(is_dir($this->tempModulesPath . '/Blog'))->toBeTrue();
    expect(file_exists($this->tempModulesPath . '/Blog/module.json'))->toBeTrue();
});

it('creates multiple modules using space-separated arguments', function () {
    $exitCode = Artisan::call('asasflow:make-module', ['name' => ['Blog', 'Shop', 'Billing']]);

    expect($exitCode)->toBe(0);
    expect(is_dir($this->tempModulesPath . '/Blog'))->toBeTrue();
    expect(is_dir($this->tempModulesPath . '/Shop'))->toBeTrue();
    expect(is_dir($this->tempModulesPath . '/Billing'))->toBeTrue();
});

it('creates multiple modules using comma-separated arguments', function () {
    $exitCode = Artisan::call('asasflow:make-module', ['name' => ['Blog,Shop,Billing']]);

    expect($exitCode)->toBe(0);
    expect(is_dir($this->tempModulesPath . '/Blog'))->toBeTrue();
    expect(is_dir($this->tempModulesPath . '/Shop'))->toBeTrue();
    expect(is_dir($this->tempModulesPath . '/Billing'))->toBeTrue();
});

it('creates multiple modules with mixed space and comma-separated arguments', function () {
    $exitCode = Artisan::call('asasflow:make-module', ['name' => ['Blog,Shop', 'Billing']]);

    expect($exitCode)->toBe(0);
    expect(is_dir($this->tempModulesPath . '/Blog'))->toBeTrue();
    expect(is_dir($this->tempModulesPath . '/Shop'))->toBeTrue();
    expect(is_dir($this->tempModulesPath . '/Billing'))->toBeTrue();
});

it('warns when a module already exists unless --force is used', function () {
    mkdir($this->tempModulesPath . '/Blog', 0777, true);

    $exitCode = Artisan::call('asasflow:make-module', ['name' => ['Blog', 'Shop']]);

    expect($exitCode)->toBe(1);
    // Shop should still be created even if Blog existed
    expect(is_dir($this->tempModulesPath . '/Shop'))->toBeTrue();

    // With --force, it should succeed
    $forceExitCode = Artisan::call('asasflow:make-module', ['name' => ['Blog'], '--force' => true]);
    expect($forceExitCode)->toBe(0);
});
