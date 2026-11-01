<?php

declare(strict_types=1);

use Bitsnio\AsasFlow\Foundation\Support\MenuDefinition;
use Bitsnio\AsasFlow\Foundation\Support\ModuleDefinition;
use Bitsnio\AsasFlow\Foundation\Support\SchemaDefinition;
use Bitsnio\AsasFlow\Generators\Request\RequestGenerator;
use Bitsnio\AsasFlow\Generators\Resource\ResourceGenerator;
use Illuminate\Filesystem\Filesystem;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/asasflow-req-res-gen-test-' . uniqid();
    mkdir($this->tempDir . '/Core/app/Http/Requests', 0777, true);
    mkdir($this->tempDir . '/Core/app/Http/Resources', 0777, true);

    $this->module = new ModuleDefinition(
        name: 'Core',
        path: $this->tempDir . '/Core',
        namespace: 'Modules\\Core',
    );
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        (new Filesystem())->deleteDirectory($this->tempDir);
    }
});

it('generates FormRequest with HandlesApiValidation trait and populates validation rules from schema', function () {
    $generator = app(RequestGenerator::class);

    $userDef = new MenuDefinition(
        module: 'Core',
        name: 'users',
        title: 'Users',
        path: ['core', 'access', 'users'],
        type: 'resource',
        model: true,
        controllerClass: 'UsersController',
        resourceClass: 'UsersResource',
    );

    $schema = new SchemaDefinition([
        'schema' => [
            'type' => 'object',
            'required' => ['firstName', 'email'],
            'properties' => [
                'firstName' => ['type' => 'string', 'maxLength' => 100],
                'email' => ['type' => 'string', 'format' => 'email'],
                'email_verified_at' => ['type' => ['string', 'null'], 'format' => 'date-time', 'readOnly' => true],
            ],
        ],
    ]);

    // Phase 1: generate initial request file
    $genResults = $generator->generate($this->module, [$userDef]);
    $requestPath = $genResults[0]['full_path'];

    // Phase 2: sync rules from schema
    $syncResult = $generator->syncFromSchema($this->module, $userDef, $schema);
    $content = file_get_contents($requestPath);

    expect($syncResult['action'])->toBe('updated')
        ->and($content)
        ->toContain('use Bitsnio\AsasFlow\Foundation\Http\Traits\HandlesApiValidation;')
        ->toContain('use HandlesApiValidation;')
        ->toContain('// @asasflow:generated-rules:start')
        ->toContain("'firstName' => ['required', 'string', 'max:100']")
        ->toContain("'email' => ['required', 'string', 'email']")
        ->toContain("'email_verified_at' => ['nullable', 'string', 'date']");
});

it('upgrades legacy @asasflow:rules:start marker when syncing existing FormRequest files', function () {
    $generator = app(RequestGenerator::class);

    $roleDef = new MenuDefinition(
        module: 'Core',
        name: 'roles',
        title: 'Roles',
        path: ['core', 'access', 'roles'],
        type: 'resource',
        model: true,
        controllerClass: 'RolesController',
        resourceClass: 'RolesResource',
    );

    $genResults = $generator->generate($this->module, [$roleDef]);
    $requestPath = $genResults[0]['full_path'];

    // Simulate a legacy generated request file with @asasflow:rules:start
    $legacyContent = <<<'PHP'
<?php

namespace Modules\Core\Http\Requests\Core\Access\Roles;

use Illuminate\Foundation\Http\FormRequest;

class RolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // @asasflow:rules:start
        return [];
        // @asasflow:rules:end
    }
}
PHP;
    file_put_contents($requestPath, $legacyContent);

    $schema = new SchemaDefinition([
        'schema' => [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                'name' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 125],
            ],
        ],
    ]);

    $syncResult = $generator->syncFromSchema($this->module, $roleDef, $schema);
    $updatedContent = file_get_contents($requestPath);

    expect($syncResult['action'])->toBe('updated')
        ->and($updatedContent)
        ->toContain('// @asasflow:generated-rules:start')
        ->toContain("'name' => ['required', 'string', 'min:2', 'max:125']");
});

it('prepends id, includes tenancy fields, and excludes password/writeOnly fields in ResourceGenerator', function () {
    $generator = app(ResourceGenerator::class);

    $userDef = new MenuDefinition(
        module: 'Core',
        name: 'users',
        title: 'Users',
        path: ['core', 'access', 'users'],
        type: 'resource',
        model: true,
        controllerClass: 'UsersController',
        resourceClass: 'UsersResource',
    );

    $schema = new SchemaDefinition([
        'schema' => [
            'type' => 'object',
            'properties' => [
                'firstName' => ['type' => 'string'],
                'email' => ['type' => 'string'],
                'password' => [
                    'type' => 'string',
                    'widget' => [
                        'formlyConfig' => [
                            'templateOptions' => ['type' => 'password'],
                        ],
                    ],
                ],
                'secretPin' => [
                    'type' => 'string',
                    'writeOnly' => true,
                ],
            ],
        ],
    ]);

    $generator->generate($this->module, [$userDef]);
    $syncResult = $generator->syncFromSchema($this->module, $userDef, $schema);
    $content = file_get_contents($syncResult['path']);

    expect($syncResult['action'])->toBe('updated')
        ->and($content)
        ->toContain("'id' => \$this->id,")
        ->toContain("'tenant_id' => \$this->tenant_id,")
        ->toContain("'site_id' => \$this->site_id,")
        ->toContain("'firstName' => \$this->firstName,")
        ->toContain("'email' => \$this->email,")
        ->not->toContain("'password'")
        ->not->toContain("'secretPin'");
});
