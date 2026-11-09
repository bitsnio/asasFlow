<?php

declare(strict_types=1);

use Bitsnio\AsasFlow\Foundation\Contracts\ModuleRepositoryInterface;
use Bitsnio\AsasFlow\Foundation\Support\ModuleDefinition;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->tempPath = sys_get_temp_dir() . '/asasflow-db-commands-test-' . uniqid();
    mkdir($this->tempPath . '/Blog/database/seeders', 0777, true);
    mkdir($this->tempPath . '/Blog/database/factories', 0777, true);

    $this->module = new ModuleDefinition(
        name: 'Blog',
        path: $this->tempPath . '/Blog',
        namespace: 'Modules\\Blog'
    );

    $module = $this->module;
    $repo = new class($module) implements ModuleRepositoryInterface {
        public function __construct(private ModuleDefinition $module) {}

        public function find(string $name): ?ModuleDefinition
        {
            return $name === 'Blog' ? $this->module : null;
        }

        public function all(): array
        {
            return ['Blog' => $this->module];
        }
    };

    app()->instance(ModuleRepositoryInterface::class, $repo);
});

afterEach(function () {
    if (is_dir($this->tempPath)) {
        File::deleteDirectory($this->tempPath);
    }
});

it('creates a standard seeder in the module', function () {
    $exitCode = Artisan::call('asasflow:make-seeder', [
        'name' => 'Post',
        'module' => 'Blog',
    ]);

    expect($exitCode)->toBe(0);

    $file = $this->tempPath . '/Blog/database/seeders/PostSeeder.php';
    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);
    expect($content)->toContain('namespace Modules\\Blog\\Database\\Seeders;')
        ->toContain('class PostSeeder extends Seeder');
});

it('creates a master database seeder in the module using --master', function () {
    $exitCode = Artisan::call('asasflow:make-seeder', [
        'name' => 'Blog',
        'module' => 'Blog',
        '--master' => true,
    ]);

    expect($exitCode)->toBe(0);

    $file = $this->tempPath . '/Blog/database/seeders/BlogDatabaseSeeder.php';
    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);
    expect($content)->toContain('class BlogDatabaseSeeder extends Seeder');
});

it('rejects seeder name that clashes with base Seeder class', function () {
    $exitCode = Artisan::call('asasflow:make-seeder', [
        'name' => 'Seeder',
        'module' => 'Blog',
    ]);

    expect($exitCode)->toBe(1);
});

it('creates an Eloquent factory in the module with derived model', function () {
    $exitCode = Artisan::call('asasflow:make-factory', [
        'name' => 'Post',
        'module' => 'Blog',
    ]);

    expect($exitCode)->toBe(0);

    $file = $this->tempPath . '/Blog/database/factories/PostFactory.php';
    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);
    expect($content)->toContain('namespace Modules\\Blog\\Database\\Factories;')
        ->toContain('class PostFactory extends Factory')
        ->toContain('use Modules\\Blog\\Models\\Post;')
        ->toContain('protected $model = Post::class;');
});

it('creates an Eloquent factory in the module with explicit --model option', function () {
    $exitCode = Artisan::call('asasflow:make-factory', [
        'name' => 'ArticleFactory',
        'module' => 'Blog',
        '--model' => 'CustomArticle',
    ]);

    expect($exitCode)->toBe(0);

    $file = $this->tempPath . '/Blog/database/factories/ArticleFactory.php';
    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);
    expect($content)->toContain('class ArticleFactory extends Factory')
        ->toContain('use Modules\\Blog\\Models\\CustomArticle;')
        ->toContain('protected $model = CustomArticle::class;');
});

it('warns when running asasflow:seed for a module without seeders', function () {
    $exitCode = Artisan::call('asasflow:seed', [
        'module' => 'Blog',
    ]);

    expect($exitCode)->toBe(0);
});

it('fails when targeting a non-existent module', function () {
    $exitCode = Artisan::call('asasflow:make-seeder', [
        'name' => 'User',
        'module' => 'NonExistent',
    ]);

    expect($exitCode)->toBe(1);

    $factoryExitCode = Artisan::call('asasflow:make-factory', [
        'name' => 'User',
        'module' => 'NonExistent',
    ]);

    expect($factoryExitCode)->toBe(1);

    $seedExitCode = Artisan::call('asasflow:seed', [
        'module' => 'NonExistent',
    ]);

    expect($seedExitCode)->toBe(1);
});
