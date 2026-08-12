<?php

namespace Bitsnio\Modules\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Bitsnio\Modules\Contracts\RepositoryInterface;

class MakeMenuCommand extends Command
{
    protected $signature = 'module:menu-manager 
    {module : The module name}
    {--add-sub-module : Add a new sub-module}
    {--add-action : Add a new action to a sub-module}';

    protected $description = 'Manage module menu structure';

    protected $routeTypes = ['full', 'get','post', 'put'];
    protected $defaultMiddleware = ['api', 'auth'];

    protected $repository;

    public function __construct(RepositoryInterface $repository)
    {
        parent::__construct();
        $this->repository = $repository;
    }

    public function handle(): void
    {
        $moduleName = $this->argument('module');
        $module = $this->repository->find($moduleName);

        if (!$module) {
            $this->error("Module [{$moduleName}] does not exist!");
            return;
        }

        if ($this->option('add-sub-module')) {
            $this->addSubModule($moduleName);
        } elseif ($this->option('add-action')) {
            $this->addAction($moduleName);
        }
    }

    protected function addSubModule(string $moduleName): void
    {
        $menuPath = $this->repository->find($moduleName)->getPath() . '/config/menu.php';
        $menu = require $menuPath;

        // Initialize sub_module array if it doesn't exist
        if (!isset($menu['module']['sub_module'])) {
            $menu['module']['sub_module'] = [];
        }

        $title = $this->askValid(
            'Enter sub-module title',
            'title',
            function ($value) {
                return !empty(trim($value));
            },
            'Title cannot be empty'
        );

        // Generate name in StudlyCase
        $name = Str::studly($title);

        // Validate if name already exists
        if ($this->subModuleExists($menu['module']['sub_module'] ?? [], $name)) {
            $this->error("A sub-module with name '{$name}' already exists!");
            return;
        }

        // Gather sub-module information
        $subModule = [
            'name' => $name,
            'title' => $title,
            'routes_type' => $this->choice(
                'Select routes type',
                $this->routeTypes,
                0
            ),
            'icon' => $this->ask(
                'Enter icon class (default: fas fa-list)',
                'fas fa-list'
            ),
            'middleware' => $this->askMiddleware(),
            'order' => $this->askOrder($menu['module']['sub_module'] ?? []),
            'actions' => [] // Initialize empty actions array
        ];

        // Add to menu
        $menu['module']['sub_module'][] = $subModule;

        // Sort by order
        usort($menu['module']['sub_module'], function ($a, $b) {
            return $a['order'] <=> $b['order'];
        });

        // Save updated menu
        $this->saveMenuConfig($menuPath, $menu);

        $this->info("Sub-module '{$subModule['name']}' added successfully!");

        if ($this->confirm('Would you like to add actions to this sub-module?', false)) {
            $this->addAction($moduleName, $subModule['name']);
        }
    }

    protected function addAction(string $moduleName, ?string $selectedSubModule = null): void
    {
        $menuPath = $this->repository->find($moduleName)->getPath() . '/config/menu.php';
        $menu = require $menuPath;

        if (!isset($menu['module']['sub_module'])) {
            $this->error('No sub-modules found. Please create a sub-module first.');
            return;
        }

        // If sub-module not provided, ask user to select one
        if (!$selectedSubModule) {
            $subModules = collect($menu['module']['sub_module'])
                ->pluck('title', 'name')
                ->toArray();

            if (empty($subModules)) {
                $this->error('No sub-modules found. Please create a sub-module first.');
                return;
            }

            $selectedSubModule = $this->choice(
                'Select sub-module to add action to',
                $subModules
            );
        }

        // Find the sub-module index
        $subModuleIndex = $this->findSubModuleIndex($menu['module']['sub_module'], $selectedSubModule);

        if ($subModuleIndex === false) {
            $this->error("Sub-module '{$selectedSubModule}' not found.");
            return;
        }

        // Ask for title first and generate name
        $title = $this->askValid(
            'Enter action title',
            'title',
            function ($value) {
                return !empty(trim($value));
            },
            'Title cannot be empty'
        );

        // Generate name in StudlyCase
        $name = Str::studly($title);

        // Validate if action already exists
        if ($this->actionExists($menu['module']['sub_module'][$subModuleIndex]['actions'] ?? [], $name)) {
            $this->error("An action with name '{$name}' already exists in this sub-module!");
            return;
        }

        // Gather action information
        $action = [
            'name' => $name,
            'title' => $title,
            'routes_type' => $this->choice(
                'Select routes type',
                $this->routeTypes,
                1
            ),
            'icon' => $this->ask(
                'Enter icon class (default: fas fa-list)',
                'fas fa-list'
            ),
            'middleware' => $this->askMiddleware(),
            'order' => $this->askOrder($menu['module']['sub_module'][$subModuleIndex]['actions'] ?? [])
        ];

        // Initialize actions array if it doesn't exist
        if (!isset($menu['module']['sub_module'][$subModuleIndex]['actions'])) {
            $menu['module']['sub_module'][$subModuleIndex]['actions'] = [];
        }

        // Add action
        $menu['module']['sub_module'][$subModuleIndex]['actions'][] = $action;

        // Sort actions by order
        usort($menu['module']['sub_module'][$subModuleIndex]['actions'], function ($a, $b) {
            return $a['order'] <=> $b['order'];
        });

        // Save updated menu
        $this->saveMenuConfig($menuPath, $menu);

        $this->info("Action '{$action['name']}' added successfully to '{$selectedSubModule}'!");

        if ($this->confirm('Would you like to add another action to this sub-module?', false)) {
            $this->addAction($module, $selectedSubModule);
        }
    }

    /**
     * Check if sub-module already exists
     */
    protected function askMiddleware(): array
    {
        $middleware = $this->defaultMiddleware;

        if ($this->confirm('Would you like to add additional middleware?', false)) {
            do {
                $newMiddleware = $this->ask('Enter middleware name');
                if (!in_array($newMiddleware, $middleware)) {
                    $middleware[] = $newMiddleware;
                }
            } while ($this->confirm('Add another middleware?', false));
        }

        return array_values(array_unique($middleware));
    }

    protected function askOrder(array $items): int
    {
        $maxOrder = empty($items) ? 0 : max(array_column($items, 'order'));
        return (int) $this->ask('Enter order number', $maxOrder + 1);
    }

    protected function subModuleExists(array $subModules, string $name): bool
    {
        return collect($subModules)->contains(function ($subModule) use ($name) {
            return strcasecmp($subModule['name'], $name) === 0;
        });
    }

    protected function actionExists(array $actions, string $name): bool
    {
        return collect($actions)->contains(function ($action) use ($name) {
            return strcasecmp($action['name'], $name) === 0;
        });
    }

    protected function findSubModuleIndex(array $subModules, string $name): int|false
    {
        foreach ($subModules as $index => $subModule) {
            if ($subModule['name'] === $name) {
                return $index;
            }
        }
        return false;
    }

    protected function askValid(
        string $question,
        string $field,
        callable $validator,
        string $errorMessage
    ): string {
        do {
            $value = $this->ask($question);

            if ($validator($value)) {
                return $value;
            }

            $this->error($errorMessage);
        } while (true);
    }

    /**
     * Save menu configuration with proper formatting
     */
    protected function saveMenuConfig(string $path, array $config): void
    {
        // Convert array to string representation
        $export = $this->arrayToString($config);

        // Clean up the formatting
        // $export = preg_replace( '/^([ ]*)(.*)/m', '$1$1$2', $export);
        // $export = preg_replace( ["/^array\s*\($/", "/\)$/"], ['[', ']'], $export);

        $content = "<?php\n\nreturn " . $export . ";\n";
        file_put_contents($path, $content);
    }

    protected function arrayToString(array $array, int $indent = 0): string
    {
        $output = "[\n";
        $indent += 4;

        foreach ($array as $key => $value) {
            $output .= str_repeat(' ', $indent);

            if (is_string($key)) {
                $output .= "'$key' => ";
            }

            if (is_array($value)) {
                // Special handling for middleware array to keep it in one line
                if ($key === 'middleware') {
                    $output .= "['" . implode("', '", $value) . "']";
                } else {
                    $output .= $this->arrayToString($value, $indent);
                }
            } elseif (is_string($value)) {
                $output .= "'$value'";
            } elseif (is_bool($value)) {
                $output .= $value ? 'true' : 'false';
            } elseif (is_null($value)) {
                $output .= 'null';
            } else {
                $output .= $value;
            }

            $output .= ",\n";
        }

        $indent -= 4;
        $output .= str_repeat(' ', $indent) . "]";

        return $output;
    }
}
