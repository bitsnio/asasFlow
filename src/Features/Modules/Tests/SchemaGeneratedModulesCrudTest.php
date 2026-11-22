<?php

declare(strict_types=1);

use Bitsnio\AsasFlow\Foundation\Testing\InteractsWithModuleApi;

uses(InteractsWithModuleApi::class);

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
