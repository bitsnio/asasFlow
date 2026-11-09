<?php

declare(strict_types=1);

use Bitsnio\AsasFlow\Features\Cache\Traits\CacheAware;
use Bitsnio\AsasFlow\Features\Settings\Http\Controllers\ModuleSettingsController;
use Bitsnio\AsasFlow\Features\Settings\Services\ModuleSettingsRegistry;
use Bitsnio\AsasFlow\Features\Tenancy\Services\TenantContext;
use Bitsnio\AsasFlow\Foundation\Contracts\ModuleRepositoryInterface;
use Bitsnio\AsasFlow\Foundation\Support\MenuDefinition;
use Bitsnio\AsasFlow\Foundation\Support\MenuService;
use Bitsnio\AsasFlow\Http\Controllers\DropdownController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class DropdownSiteModel extends Model
{
    use CacheAware;

    protected $table = 'dropdown_sites';
    protected $guarded = [];
}

class DropdownTestUser extends AuthenticatableUser
{
    protected $table = 'dropdown_users';
    protected $guarded = [];
}

beforeEach(function () {
    Cache::store('array')->flush();
    TenantContext::reset();

    Schema::dropIfExists('dropdown_sites');
    Schema::create('dropdown_sites', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('tenant_id')->nullable();
        $table->string('code');
        $table->string('name');
        $table->string('category')->default('branch');
        $table->boolean('is_active')->default(true);
        $table->json('meta')->nullable();
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::dropIfExists('dropdown_users');
    Schema::create('dropdown_users', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('tenant_id')->nullable();
        $table->string('firstName');
        $table->string('lastName');
        $table->string('email');
        $table->string('password')->default('secret');
        $table->timestamps();
    });

    Schema::dropIfExists('module_settings');
    Schema::create('module_settings', function (Blueprint $table) {
        $table->id();
        $table->string('module');
        $table->unsignedBigInteger('company_id')->nullable();
        $table->unsignedBigInteger('site_id')->nullable();
        $table->json('values')->nullable();
        $table->timestamps();
    });

    Schema::dropIfExists('password_resets');
    Schema::create('password_resets', function (Blueprint $table) {
        $table->string('email');
        $table->string('token');
    });

    DropdownSiteModel::create([
        'tenant_id' => 1,
        'code' => 'HQ',
        'name' => 'Headquarters',
        'category' => 'main',
        'is_active' => true,
        'meta' => json_encode(['region' => 'North America']),
    ]);

    DropdownSiteModel::create([
        'tenant_id' => 1,
        'code' => 'LAH',
        'name' => 'Lahore Branch',
        'category' => 'branch',
        'is_active' => true,
        'meta' => json_encode(['region' => 'Asia']),
    ]);

    DropdownSiteModel::create([
        'tenant_id' => 1,
        'code' => 'OLD',
        'name' => 'Archived Site',
        'category' => 'branch',
        'is_active' => false,
        'deleted_at' => now(),
    ]);

    DropdownSiteModel::create([
        'tenant_id' => 2,
        'code' => 'T2',
        'name' => 'Other Tenant Site',
        'category' => 'main',
        'is_active' => true,
    ]);

    DB::table('dropdown_users')->insert([
        [
            'tenant_id' => 1,
            'firstName' => 'Haseeb',
            'lastName' => 'Ahmad',
            'email' => 'haseeb@example.com',
            'password' => 'hashed-secret',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'tenant_id' => 1,
            'firstName' => 'Ali',
            'lastName' => 'Khan',
            'email' => 'ali@example.com',
            'password' => 'hashed-secret',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    Route::get('dropdown/{table}/{label?}/{value?}', DropdownController::class)
        ->name('test.dropdown.index');
    Route::get('api/dropdown/{table}/{label?}/{value?}', DropdownController::class)
        ->name('test.api.dropdown.index');

    Route::get('api/settings', [ModuleSettingsController::class, 'index']);
    Route::get('api/settings/{module}', [ModuleSettingsController::class, 'show']);
    Route::put('api/settings/{module}', [ModuleSettingsController::class, 'update']);
});

it('returns standard label/value options from a database table and excludes soft-deleted rows', function () {
    config()->set('asasflow.tenancy.enabled', false);

    $this->getJson('/api/dropdown/dropdown_sites/name/code')
        ->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                ['label' => 'Headquarters', 'value' => 'HQ'],
                ['label' => 'Lahore Branch', 'value' => 'LAH'],
                ['label' => 'Other Tenant Site', 'value' => 'T2'],
            ],
        ]);
});

it('supports composite comma-separated labels and automatic name to firstName/lastName fallback', function () {
    config()->set('asasflow.tenancy.enabled', false);

    // Automatic fallback when `name` column does not exist on dropdown_users
    $this->getJson('/api/dropdown/dropdown_users/name/id')
        ->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                ['label' => 'Haseeb Ahmad', 'value' => 1],
                ['label' => 'Ali Khan', 'value' => 2],
            ],
        ]);

    // Explicit composite label `code,name`
    $this->getJson('/dropdown/dropdown_sites/code,name/id?tenant_id=1')
        ->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                ['label' => 'HQ Headquarters', 'value' => 1],
                ['label' => 'LAH Lahore Branch', 'value' => 2],
            ],
        ]);
});

it('supports JSON dot-notation columns, filtering, search, limit, and group_by', function () {
    config()->set('asasflow.tenancy.enabled', false);

    // JSON dot-notation
    $this->getJson('/api/dropdown/dropdown_sites/meta.region/code?tenant_id=1')
        ->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                ['label' => 'North America', 'value' => 'HQ'],
                ['label' => 'Asia', 'value' => 'LAH'],
            ],
        ]);

    // Search + limit
    $this->getJson('/api/dropdown/dropdown_sites/name/code?search=Lahore&limit=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.value', 'LAH');

    // Grouped dropdown options
    $this->getJson('/api/dropdown/dropdown_sites/name/code?tenant_id=1&group_by=category')
        ->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                'main' => [
                    ['label' => 'Headquarters', 'value' => 'HQ'],
                ],
                'branch' => [
                    ['label' => 'Lahore Branch', 'value' => 'LAH'],
                ],
            ],
        ]);
});

it('automatically scopes database dropdowns by tenant_id in single-DB tenancy mode', function () {
    config()->set('asasflow.tenancy.enabled', true);
    config()->set('asasflow.tenancy.database_strategy', 'single');

    $user = new DropdownTestUser(['id' => 1, 'tenant_id' => 1, 'firstName' => 'Haseeb', 'lastName' => 'Ahmad']);
    $this->actingAs($user, 'api');

    $this->getJson('/api/dropdown/dropdown_sites/name/code')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJson([
            'success' => true,
            'data' => [
                ['label' => 'Headquarters', 'value' => 'HQ'],
                ['label' => 'Lahore Branch', 'value' => 'LAH'],
            ],
        ]);
});

it('blocks protected tables (403), protected columns (403), missing tables (404), and invalid columns (422)', function () {
    $this->getJson('/api/dropdown/password_resets/email/token')
        ->assertStatus(403)
        ->assertJsonPath('success', false);

    $this->getJson('/api/dropdown/dropdown_users/password/id')
        ->assertStatus(403)
        ->assertJsonPath('success', false);

    $this->getJson('/api/dropdown/non_existent_table/name/id')
        ->assertStatus(404)
        ->assertJsonPath('success', false);

    $this->getJson('/api/dropdown/dropdown_sites/unknown_column/id')
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('resolves virtual modules and permissions dropdowns', function () {
    app()->bind(ModuleRepositoryInterface::class, fn () => new class implements ModuleRepositoryInterface {
        public function find(string $name): ?\Bitsnio\AsasFlow\Foundation\Support\ModuleDefinition
        {
            return null;
        }

        public function all(): array
        {
            return ['Core' => ['name' => 'Core'], 'Crm' => ['name' => 'Crm']];
        }
    });

    // Rebind DropdownService so it picks up the test ModuleRepositoryInterface
    app()->forgetInstance(\Bitsnio\AsasFlow\Foundation\Http\Dropdown\DropdownService::class);

    $this->getJson('/api/dropdown/modules/name')
        ->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                ['label' => 'Core', 'value' => 'Core'],
                ['label' => 'Crm', 'value' => 'Crm'],
            ],
        ]);

    // Configure a test module menu for permissions
    app()->bind(MenuService::class, fn () => new class(
        app(ModuleRepositoryInterface::class),
        app(\Bitsnio\AsasFlow\Generators\Menu\MenuBuilder::class),
    ) extends MenuService {
        public function getDefinitions(?string $moduleName = null): array
        {
            return [
                new MenuDefinition(
                    module: 'Core',
                    name: 'users',
                    title: 'Users',
                    path: ['core', 'users'],
                    type: 'resource',
                    permissions: [
                        'view' => 'View Users',
                    ],
                ),
            ];
        }
    });
    app()->forgetInstance(\Bitsnio\AsasFlow\Foundation\Support\PermissionService::class);
    app()->forgetInstance(\Bitsnio\AsasFlow\Foundation\Http\Dropdown\DropdownService::class);

    $this->getJson('/api/dropdown/permissions/name?module=Core')
        ->assertOk()
        ->assertJsonPath('data.0', ['label' => 'All Permissions', 'value' => ''])
        ->assertJsonPath('data.1', ['label' => 'View Users', 'value' => 'core.users.view']);
});

it('resolves virtual settings dropdowns (both listing settings and fetching options for a specific setting key) and works with ModuleSettingsController', function () {
    $registry = app(ModuleSettingsRegistry::class);
    $registry->clear();
    $registry->register('core', 'core');

    config()->set('core.settings', [
        'maintenance_mode' => [
            'label' => 'Maintenance Mode',
            'type' => 'boolean',
            'input' => 'switch',
            'default' => false,
            'scope' => 'module',
        ],
        'default_currency' => [
            'label' => 'Default Currency',
            'type' => 'string',
            'input' => 'select',
            'default' => 'USD',
            'scope' => 'company',
            'options' => [
                ['label' => 'US Dollar', 'value' => 'USD'],
                ['label' => 'Pakistani Rupee', 'value' => 'PKR'],
            ],
        ],
    ]);

    // Mode A: List settings definitions (label -> key)
    $this->getJson('/api/dropdown/settings/label/key?module=core')
        ->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                ['label' => 'Maintenance Mode', 'value' => 'maintenance_mode'],
                ['label' => 'Default Currency', 'value' => 'default_currency'],
            ],
        ]);

    // Mode A: List settings resolved values (label -> value)
    $this->getJson('/api/dropdown/settings/label/value?module=core')
        ->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                ['label' => 'Maintenance Mode', 'value' => false],
                ['label' => 'Default Currency', 'value' => 'USD'],
            ],
        ]);

    // Mode B: Populate select options from a specific setting key
    $this->getJson('/api/dropdown/settings/label/value?module=core&key=default_currency')
        ->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                ['label' => 'US Dollar', 'value' => 'USD'],
                ['label' => 'Pakistani Rupee', 'value' => 'PKR'],
            ],
        ]);

    // Shorthand Mode B: /api/dropdown/settings/default_currency?module=core
    $this->getJson('/api/dropdown/settings/default_currency?module=core')
        ->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                ['label' => 'US Dollar', 'value' => 'USD'],
                ['label' => 'Pakistani Rupee', 'value' => 'PKR'],
            ],
        ]);

    // Centralised ModuleSettingsController unified responses
    $this->getJson('/api/settings?with_settings=1')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.modules', ['core'])
        ->assertJsonPath('data.settings.core.default_currency.value', 'USD');

    $this->putJson('/api/settings/core', [
        'company_id' => 1,
        'settings' => [
            'default_currency' => 'PKR',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.settings.default_currency', 'PKR');
});

it('caches database dropdown responses with dropdown:{table} tag and invalidates when a CacheAware model changes', function () {
    config()->set('asasflow.tenancy.enabled', false);
    config()->set('asasflow.dropdown.cache.enabled', true);

    // 1st call -> MISS
    $this->getJson('/api/dropdown/dropdown_sites/name/code?tenant_id=1')
        ->assertOk()
        ->assertHeader('X-AsasFlow-Cache', 'MISS')
        ->assertJsonCount(2, 'data');

    // 2nd call -> HIT
    $this->getJson('/api/dropdown/dropdown_sites/name/code?tenant_id=1')
        ->assertOk()
        ->assertHeader('X-AsasFlow-Cache', 'HIT')
        ->assertJsonCount(2, 'data');

    // Mutate DropdownSiteModel -> ModelCacheObserver invalidates tag `dropdown:dropdown_sites`
    DropdownSiteModel::create([
        'tenant_id' => 1,
        'code' => 'ISB',
        'name' => 'Islamabad Branch',
        'category' => 'branch',
        'is_active' => true,
    ]);

    // 3rd call -> MISS with fresh 3 items
    $this->getJson('/api/dropdown/dropdown_sites/name/code?tenant_id=1')
        ->assertOk()
        ->assertHeader('X-AsasFlow-Cache', 'MISS')
        ->assertJsonCount(3, 'data');
});
