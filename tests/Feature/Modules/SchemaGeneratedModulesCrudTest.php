<?php

declare(strict_types=1);

use Bitsnio\AsasFlow\Foundation\Testing\InteractsWithModuleApi;

uses(InteractsWithModuleApi::class);

beforeEach(function () {
    $externalModulesPath = '/Users/haseeb/Desktop/sourcecode/project_to_test_local_package/Modules';

    if (is_dir($externalModulesPath) && @is_readable($externalModulesPath) && @scandir($externalModulesPath) !== false) {
        config([
            'asasflow.modules.path' => $externalModulesPath,
            'asasflow.modules.namespace' => 'Modules',
        ]);
    }
});

it('verifies routes, schemas, validation, CRUD, relational sync, custom actions, and dropdowns across all modules generated via generate-from-schema', function () {
    $onlyModule = env('ASASFLOW_TEST_MODULE');

    $discovered = $this->discoverSchemaGeneratedModules(
        is_string($onlyModule) && $onlyModule !== '' ? $onlyModule : null,
    );

    if ($discovered === []) {
        $this->markTestSkipped('No schema-generated modules with migrations and controllers were found.');
    }

    $testedResources = $this->assertAllSchemaGeneratedModulesCrud(
        is_string($onlyModule) && $onlyModule !== '' ? $onlyModule : null,
    );

    expect($testedResources)->toBeGreaterThan(0);
});
