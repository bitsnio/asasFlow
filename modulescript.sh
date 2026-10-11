#!/usr/bin/env bash
set -euo pipefail

# Run from the AsasFlow package root.
if [[ ! -d "src" ]]; then
    echo "Error: src/ directory not found. Run this script from the AsasFlow package root."
    exit 1
fi

CREATED=0
SKIPPED=0

create_dir() {
    local dir="$1"
    if [[ ! -d "$dir" ]]; then
        mkdir -p "$dir"
        echo "[DIR]     Created: $dir"
    fi
}

create_file() {
    local file="$1"
    if [[ -e "$file" ]]; then
        echo "[SKIP]    Exists:  $file"
        SKIPPED=$((SKIPPED + 1))
        return
    fi
    mkdir -p "$(dirname "$file")"
    cat > "$file"
    echo "[CREATE]  Created: $file"
    CREATED=$((CREATED + 1))
}

echo "Creating AsasFlow module-system structure..."

create_dir "src/Console/Commands/Module"
create_dir "src/Foundation/Contracts"
create_dir "src/Foundation/Support"
create_dir "src/Providers"
create_dir "src/Generators/Module"
create_dir "src/Generators/Stubs/Module"

create_file "src/Foundation/Support/ModuleDefinition.php" <<'PHP'
<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Foundation\Support;

final readonly class ModuleDefinition
{
    public function __construct(
        public string $name,
        public string $path,
        public string $namespace,
    ) {}

    public function configPath(): string { return $this->path . '/config'; }
    public function routesPath(): string { return $this->path . '/routes'; }
    public function migrationsPath(): string { return $this->path . '/database/migrations'; }

    public function providerClass(): string
    {
        return $this->namespace . '\\' . $this->name . '\\' . $this->name . 'ServiceProvider';
    }
}
PHP

create_file "src/Foundation/Contracts/ModuleRepositoryInterface.php" <<'PHP'
<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Foundation\Contracts;

use Bitsnio\AsasFlow\Foundation\Support\ModuleDefinition;

interface ModuleRepositoryInterface
{
    public function find(string $name): ?ModuleDefinition;

    /** @return array<string, ModuleDefinition> */
    public function all(): array;
}
PHP

create_file "src/Foundation/Support/ModuleRepository.php" <<'PHP'
<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Foundation\Support;

use Bitsnio\AsasFlow\Foundation\Contracts\ModuleRepositoryInterface;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ModuleRepository implements ModuleRepositoryInterface
{
    public function find(string $name): ?ModuleDefinition
    {
        $name = $this->normalizeName($name);
        $path = $this->modulesPath() . DIRECTORY_SEPARATOR . $name;

        if (! File::isDirectory($path)) {
            return null;
        }

        return new ModuleDefinition(
            name: $name,
            path: $path,
            namespace: trim($this->modulesNamespace(), '\\') . '\\' . $name,
        );
    }

    public function all(): array
    {
        $path = $this->modulesPath();
        if (! File::isDirectory($path)) {
            return [];
        }

        $modules = [];
        foreach (File::directories($path) as $directory) {
            $name = basename($directory);
            if (! preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name)) {
                continue;
            }

            $modules[$name] = new ModuleDefinition(
                name: $name,
                path: $directory,
                namespace: trim($this->modulesNamespace(), '\\') . '\\' . $name,
            );
        }

        return $modules;
    }

    private function normalizeName(string $name): string
    {
        $name = trim($name);
        if (
            $name === ''
            || str_contains($name, '/')
            || str_contains($name, '\\')
            || ! preg_match('/^[A-Za-z][A-Za-z0-9 _-]*$/', $name)
        ) {
            throw new InvalidArgumentException("Invalid module name [{$name}].");
        }

        $name = Str::studly($name);
        if (! preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name)) {
            throw new InvalidArgumentException("Invalid normalized module name [{$name}].");
        }

        return $name;
    }

    private function modulesPath(): string
    {
        return rtrim(
            (string) config('asasflow.modules.path', base_path('Modules')),
            DIRECTORY_SEPARATOR
        );
    }

    private function modulesNamespace(): string
    {
        return (string) config('asasflow.modules.namespace', 'Modules');
    }
}
PHP

create_file "src/Foundation/Support/ModuleScaffolder.php" <<'PHP'
<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Foundation\Support;

use Illuminate\Support\Facades\File;

final class ModuleScaffolder
{
    public function createDirectories(ModuleDefinition $module): void
    {
        foreach ((array) config('asasflow.modules.structure', []) as $directory) {
            $path = $module->path . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, $directory);
            File::ensureDirectoryExists($path);
        }
    }
}
PHP

create_file "src/Foundation/Support/ModuleBootstrapper.php" <<'PHP'
<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Foundation\Support;

use LogicException;

final class ModuleBootstrapper
{
    public function bootstrap(ModuleDefinition $module): void
    {
        throw new LogicException(
            'ModuleBootstrapper is a placeholder and has not been implemented.'
        );
    }
}
PHP

create_file "src/Providers/ModuleServiceProvider.php" <<'PHP'
<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Providers;

use Illuminate\Support\ServiceProvider;

abstract class ModuleServiceProvider extends ServiceProvider
{
    protected string $name;
    protected string $nameLower;

    public function modulePath(string $path = ''): string
    {
        $modulePath = rtrim(
            (string) config('asasflow.modules.path', base_path('Modules')),
            DIRECTORY_SEPARATOR
        ) . DIRECTORY_SEPARATOR . $this->name;

        return $path === ''
            ? $modulePath
            : $modulePath . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }
}
PHP

create_file "src/Generators/Module/ModuleGenerator.php" <<'PHP'
<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Generators\Module;

use Bitsnio\AsasFlow\Foundation\Support\ModuleDefinition;
use Bitsnio\AsasFlow\Foundation\Support\ModuleScaffolder;

final class ModuleGenerator
{
    public function __construct(
        private readonly ModuleScaffolder $scaffolder,
    ) {}

    public function generate(ModuleDefinition $module): void
    {
        $this->scaffolder->createDirectories($module);
    }
}
PHP

create_file "src/Console/Commands/Module/MakeModuleCommand.php" <<'PHP'
<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Console\Commands\Module;

use Illuminate\Console\Command;

final class MakeModuleCommand extends Command
{
    protected $signature = 'asasflow:make-module {name}';
    protected $description = 'Create an AsasFlow module (not wired yet).';

    public function handle(): int
    {
        $this->error('This command is a placeholder and is not wired to the module generator yet.');
        return self::FAILURE;
    }
}
PHP

create_file "src/Generators/Stubs/Module/module-service-provider.stub" <<'STUB'
<?php

declare(strict_types=1);

namespace {{ MODULE_NAMESPACE }}\Providers;

use Bitsnio\AsasFlow\Providers\ModuleServiceProvider as BaseModuleServiceProvider;

class {{ MODULE }}ServiceProvider extends BaseModuleServiceProvider
{
    protected string $name = '{{ MODULE }}';
    protected string $nameLower = '{{ MODULE_LOWER }}';
}
STUB

create_file "src/Generators/Stubs/Module/config.stub" <<'STUB'
<?php

return [
    // Module-specific configuration.
];
STUB

create_file "src/Generators/Stubs/Module/menu.stub" <<'STUB'
<?php

return [
    'module' => [
        'name' => '{{ MODULE_LOWER }}',
        'title' => '{{ MODULE_TITLE }}',
        'children' => [],
    ],
];
STUB

create_file "src/Generators/Stubs/Module/settings.stub" <<'STUB'
<?php

return [
    // Module settings. Keep compatible with the AsasFlow settings implementation.
];
STUB

create_file "src/Generators/Stubs/Module/api.stub" <<'STUB'
<?php

use Illuminate\Support\Facades\Route;

Route::prefix('{{ MODULE_LOWER }}')->group(function (): void {
    // Add module API routes here.
});
STUB

create_file "src/Generators/Stubs/Module/module-json.stub" <<'STUB'
{
    "name": "{{ MODULE }}",
    "alias": "{{ MODULE_LOWER }}",
    "namespace": "Modules\\{{ MODULE }}",
    "provider": "Modules\\{{ MODULE }}\\Providers\\{{ MODULE }}ServiceProvider"
}
STUB

echo
echo "Module-system setup finished."
echo "Created: $CREATED file(s)"
echo "Skipped: $SKIPPED existing file(s)"
echo "No existing files were overwritten."
echo "This creates starter files only; it does not replace the current module runtime."
