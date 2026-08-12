<?php

namespace Bitsnio\Modules\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Bitsnio\Modules\Facades\Module;
use Illuminate\Support\Str;

class ModelsFromSchemaCommand extends Command
{
    protected $signature = 'module:make-from-schema 
                            {module : The module name}
                            {--schema= : Specific schema filename without extension}
                            {--force : Overwrite existing files}';

    protected $description = 'Generate models, requests, migrations, API resources with validations from JSON schema';

    // Reserved fields that shouldn't be processed from schema
    protected array $reservedFields = [
        'id', 'created_at', 'updated_at', 'deleted_at', 
        'company_id', 'site_id', 'created_by'
    ];

    public function handle()
    {
        $moduleName = $this->argument('module');
        $module = Module::findOrFail($moduleName);
        $schemasPath = $module->getPath() . '/Schemas';

        if (!File::exists($schemasPath)) {
            $this->error("Schemas directory not found in module [{$moduleName}]");
            return;
        }

        $files = $this->option('schema')
            ? [$this->option('schema') . '.json']
            : File::files($schemasPath);

        foreach ($files as $file) {
            $filename = is_string($file) ? $file : $file->getFilename();

            if (!str_ends_with($filename, '.json')) {
                continue;
            }

            $this->processSchemaFile(
                module: $module,
                filename: $filename,
                schemaPath: $schemasPath . '/' . $filename
            );
        }
    }

    protected function processSchemaFile($module, $filename, $schemaPath)
    {
        $schema = json_decode(File::get($schemaPath), true);
        
        if (!$schema) {
            $this->error("Invalid JSON schema in file: {$filename}");
            return;
        }

        $modelName = $this->getModelNameFromFilename($filename);

        $this->info("Processing schema: {$filename}");

        // Ensure all required directories exist
        $this->ensureDirectoriesExist($module);

        // 1. Generate Model with scopes and soft deletes
        $this->createModelClass($module, $modelName, $schema);

        // 2. Generate Form Request with validation rules
        $this->createRequestClass($module, $modelName, $schema);

        // 3. Generate Migration with full constraints
        $this->createMigration($module, $modelName, $schema);

        // 4. Generate API Resource
        $this->createApiResource($module, $modelName, $schema);
    }

    protected function createModelClass($module, $modelName, $schema)
    {
        // Ensure the Models directory exists
        $modelsPath = $module->getPath() . '/App/Models';
        if (!File::exists($modelsPath)) {
            File::makeDirectory($modelsPath, 0755, true);
        }
        
        $modelPath = $modelsPath . "/{$modelName}.php";
        
        // Only create if it doesn't exist or --force is used
        if (!$this->option('force') && File::exists($modelPath)) {
            $this->info("Model already exists: {$modelName}");
            return;
        }

        $fillableFields = $this->getFillableFields($schema);
        $tableName = Str::snake(Str::plural($modelName));

        $modelStub = <<<PHP
        <?php
        
        namespace Modules\\{$module->getName()}\App\Models;
        
        use Illuminate\Database\Eloquent\Model;
        use Illuminate\Database\Eloquent\SoftDeletes;
        use Illuminate\Database\Eloquent\Factories\HasFactory;
        use App\Traits\TenantScopes;
        use App\Models\Sites;
        use App\Models\Companies;
        
        class {$modelName} extends Model
        {
            use HasFactory, SoftDeletes, TenantScopes;
        
            protected \$fillable = [{$fillableFields}];
        
            protected \$table = '{$tableName}';
        
            protected \$dates = ['deleted_at'];
        
            protected \$casts = [
                'created_at' => 'datetime',
                'updated_at' => 'datetime',
                'deleted_at' => 'datetime',
            ];
        
            // Relationships
            public function company()
            {
                return \$this->belongsTo(Companies::class);
            }
        
            public function sites()
            {
                return \$this->belongsTo(Sites::class);
            }
        
            public function creator()
            {
                return \$this->belongsTo(User::class, 'created_by');
            }
        }
        PHP;

        File::put($modelPath, $modelStub);
        $this->info("Model created with scopes and soft deletes: {$modelName}");
    }

    protected function createApiResource($module, $modelName, $schema)
    {
        $resourceName = "{$modelName}Resource";
        $collectionName = "{$modelName}Collection";

        // Ensure the Resources directory exists in the correct location
        $resourcesPath = $module->getPath() . '/App/Http/Resources';
        if (!File::exists($resourcesPath)) {
            File::makeDirectory($resourcesPath, 0755, true);
        }

        // Generate resource fields from schema, excluding reserved fields
        $resourceFields = $this->generateResourceFields($schema, $this->reservedFields);

        // Create resource file only if it doesn't exist or --force is used
        $resourcePath = $resourcesPath . "/{$resourceName}.php";
        if ($this->option('force') || !File::exists($resourcePath)) {
            $resourceStub = <<<PHP
            <?php

            namespace Modules\\{$module->getName()}\App\Http\Resources;

            use Illuminate\Http\Resources\Json\JsonResource;

            class {$resourceName} extends JsonResource
            {
                public function toArray(\$request)
                {
                    return [
                        'id' => \$this->id,
                        {$resourceFields}
                        'company_id' => \$this->company_id,
                        'site_id' => \$this->site_id,
                        'created_by' => \$this->created_by,
                        'created_at' => \$this->created_at,
                        'updated_at' => \$this->updated_at,
                        'deleted_at' => \$this->deleted_at,
                        
                        // Relationships
                        'company' => \$this->whenLoaded('compnies'),
                        'sites' => \$this->whenLoaded('sites'),
                        'creator' => \$this->whenLoaded('users'),
                    ];
                }
            }
            PHP;
            File::put($resourcePath, $resourceStub);
            $this->info("API Resource created: {$resourceName}");
        }

        // Create collection file only if it doesn't exist or --force is used
        $collectionPath = $resourcesPath . "/{$collectionName}.php";
        if ($this->option('force') || !File::exists($collectionPath)) {
            $collectionStub = <<<PHP
            <?php

            namespace Modules\\{$module->getName()}\App\Http\Resources;

            use Illuminate\Http\Resources\Json\ResourceCollection;

            class {$collectionName} extends ResourceCollection
            {
                public function toArray(\$request)
                {
                    return [
                        'data' => \$this->collection,
                        'meta' => [
                            'total' => \$this->count(),
                            'per_page' => \$request->get('per_page', 15),
                            'current_page' => \$request->get('page', 1),
                        ],
                        'links' => [
                            'self' => url()->current(),
                        ],
                    ];
                }
            }
            PHP;
            File::put($collectionPath, $collectionStub);
            $this->info("API Resource Collection created: {$collectionName}");
        }
    }

    protected function generateResourceFields($schema, array $exclude = []): string
    {
        $properties = $this->extractProperties($schema);
        $fields = [];

        foreach ($properties as $field => $config) {
            if (in_array($field, $exclude)) {
                continue;
            }
            $fields[] = "            '{$field}' => \$this->{$field},";
        }

        return implode("\n", $fields);
    }

    protected function createRequestClass($module, $modelName, $schema)
    {
        $requestName = "{$modelName}Request";
        $rules = $this->generateValidationRules($schema);

        // Ensure the Requests directory exists
        $requestsPath = $module->getPath() . '/App/Http/Requests';
        if (!File::exists($requestsPath)) {
            File::makeDirectory($requestsPath, 0755, true);
        }

        // Create the request file path
        $requestPath = $requestsPath . "/{$requestName}.php";

        // Only create if it doesn't exist or --force is used
        if (!$this->option('force') && File::exists($requestPath)) {
            $this->info("Request already exists: {$requestName}");
            return;
        }

        $stub = <<<PHP
        <?php
            
        namespace Modules\\{$module->getName()}\App\Http\Requests;

        use Illuminate\Foundation\Http\FormRequest;

        class {$requestName} extends FormRequest
        {
            public function authorize()
            {
                return true;
            }

            public function rules()
            {
                return {$rules};
            }

            public function messages()
            {
                return [
                    'required' => 'The :attribute field is required.',
                    'string' => 'The :attribute must be a string.',
                    'email' => 'The :attribute must be a valid email address.',
                    'numeric' => 'The :attribute must be a number.',
                    'boolean' => 'The :attribute must be true or false.',
                    'array' => 'The :attribute must be an array.',
                    'date' => 'The :attribute must be a valid date.',
                ];
            }

            protected function prepareForValidation()
            {
                // Auto-assign company_id and location_id from authenticated user
                if (auth()->check()) {
                    \$this->merge([
                        'company_id' => \$this->company_id ?? auth()->user()->company_id,
                        'site_id' => \$this->location_id ?? auth()->user()->site_id,
                        'created_by' => \$this->created_by ?? auth()->user()->id,
                    ]);
                }
            }
        }
        PHP;

        File::put($requestPath, $stub);
        $this->info("Request created with validation rules: {$requestName}");
    }

    protected function getModelNameFromFilename(string $filename): string
    {
        return Str::studly(str_replace('.json', '', $filename));
    }

    protected function getFillableFields(array $schema): string
    {
        $properties = $this->extractProperties($schema);
        $fillable = array_keys($properties);
        
        // Add standard fields that should be fillable
        $standardFields = ['company_id', 'site_id', 'created_by'];
        $fillable = array_merge($fillable, $standardFields);
        
        // Remove duplicates and format
        $fillable = array_unique($fillable);
        
        return "'" . implode("', '", $fillable) . "'";
    }

    protected function extractProperties(array $node, string $prefix = ''): array
    {
        $fields = [];

        if (!isset($node['properties']) || !is_array($node['properties'])) {
            return $fields;
        }

        foreach ($node['properties'] as $key => $config) {
            // Skip reserved fields to avoid duplicates
            if (in_array($key, $this->reservedFields)) {
                continue;
            }

            if (isset($config['type']) && $config['type'] === 'object' && isset($config['properties'])) {
                $nested = $this->extractProperties($config, $prefix . $key . '_');
                $fields = array_merge($fields, $nested);
            } else {
                $fields[$prefix . $key] = $config;
            }
        }

        return $fields;
    }

    protected function generateValidationRules(array $schema): string
    {
        $rules = [];
        $properties = $this->extractProperties($schema);
        $requiredFields = $this->getRequiredFields($schema);

        foreach ($properties as $field => $config) {
            $fieldRules = [];

            // Required rule
            if (in_array(str_replace('_', '.', $field), $requiredFields)) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            // Type-specific rules
            if (isset($config['type'])) {
                switch ($config['type']) {
                    case 'string':
                        $fieldRules[] = 'string';
                        if (isset($config['minLength'])) {
                            $fieldRules[] = "min:{$config['minLength']}";
                        }
                        if (isset($config['maxLength'])) {
                            $fieldRules[] = "max:{$config['maxLength']}";
                        }
                        if (isset($config['pattern'])) {
                            $pattern = str_replace('/', '\/', $config['pattern']);
                            $fieldRules[] = "regex:/{$pattern}/";
                        }
                        if (isset($config['format'])) {
                            if ($config['format'] === 'email') {
                                $fieldRules[] = 'email';
                            }
                            if ($config['format'] === 'date-time' || $config['format'] === 'date') {
                                $fieldRules[] = 'date';
                            }
                            if ($config['format'] === 'uri' || $config['format'] === 'url') {
                                $fieldRules[] = 'url';
                            }
                        }
                        break;

                    case 'integer':
                        $fieldRules[] = 'integer';
                        if (isset($config['minimum'])) {
                            $fieldRules[] = "min:{$config['minimum']}";
                        }
                        if (isset($config['maximum'])) {
                            $fieldRules[] = "max:{$config['maximum']}";
                        }
                        break;

                    case 'number':
                        $fieldRules[] = 'numeric';
                        if (isset($config['minimum'])) {
                            $fieldRules[] = "min:{$config['minimum']}";
                        }
                        if (isset($config['maximum'])) {
                            $fieldRules[] = "max:{$config['maximum']}";
                        }
                        break;

                    case 'boolean':
                        $fieldRules[] = 'boolean';
                        break;

                    case 'array':
                        $fieldRules[] = 'array';
                        if (isset($config['minItems'])) {
                            $fieldRules[] = "min:{$config['minItems']}";
                        }
                        if (isset($config['maxItems'])) {
                            $fieldRules[] = "max:{$config['maxItems']}";
                        }
                        break;
                }
            }

            // Enum validation
            if (isset($config['enum']) && is_array($config['enum'])) {
                $allowed = implode(',', $config['enum']);
                $fieldRules[] = "in:{$allowed}";
            }

            $rules[$field] = implode('|', $fieldRules);
        }

        // Add validation for standard fields
        $rules['company_id'] = 'nullable|integer|exists:companies,id';
        $rules['site_id'] = 'nullable|integer|exists:sites,id';
        $rules['created_by'] = 'nullable|integer|exists:users,id';

        return $this->formatArrayForExport($rules);
    }

    protected function formatArrayForExport(array $array): string
    {
        $lines = [];
        $lines[] = '[';
        
        foreach ($array as $key => $value) {
            $lines[] = "        '{$key}' => '{$value}',";
        }
        
        $lines[] = '    ]';
        
        return implode("\n", $lines);
    }

    protected function getRequiredFields(array $schema): array
    {
        $required = [];
        $this->collectRequiredFields($schema, '', $required);
        return array_unique($required);
    }

    protected function collectRequiredFields(array $node, string $prefix, array &$required)
    {
        if (isset($node['required']) && is_array($node['required'])) {
            foreach ($node['required'] as $field) {
                // Skip reserved fields
                if (!in_array($field, $this->reservedFields)) {
                    $required[] = $prefix . $field;
                }
            }
        }

        if (isset($node['properties'])) {
            foreach ($node['properties'] as $key => $config) {
                if (isset($config['type']) && $config['type'] === 'object') {
                    $this->collectRequiredFields($config, $prefix . $key . '.', $required);
                }
            }
        }
    }

    protected function createMigration($module, $modelName, $schema)
    {
        $tableName = Str::snake(Str::plural($modelName));
        $properties = $this->extractProperties($schema);
        $requiredFields = $this->getRequiredFields($schema);

        $migrationStub = $this->generateMigrationStub($tableName, $properties, $requiredFields);
        $migrationPath = $this->writeMigrationStub($module, $tableName, $migrationStub);

        $this->info("Migration created: " . basename($migrationPath));
    }

    protected function generateMigrationStub(string $tableName, array $properties, array $requiredFields): string
    {
        $fields = [];
        $indexes = [];

        // Add standard fields first
        $fields[] = '$table->unsignedBigInteger(\'company_id\')->nullable()->index();';
        $fields[] = '$table->unsignedBigInteger(\'site_id\')->nullable()->index();';
        $fields[] = '$table->unsignedBigInteger(\'created_by\')->nullable()->index();';

        // Add foreign key constraints
        $fields[] = '$table->foreign(\'company_id\')->references(\'id\')->on(\'companies\')->onDelete(\'cascade\');';
        $fields[] = '$table->foreign(\'site_id\')->references(\'id\')->on(\'sites\')->onDelete(\'set null\');';
        $fields[] = '$table->foreign(\'created_by\')->references(\'id\')->on(\'users\')->onDelete(\'set null\');';

        foreach ($properties as $field => $config) {
            $fieldDefinition = $this->getFieldDefinition($field, $config);

            // Add nullable if not required
            if (!in_array(str_replace('_', '.', $field), $requiredFields)) {
                $fieldDefinition .= '->nullable()';
            }

            // Add unique for emails
            if (isset($config['format']) && $config['format'] === 'email') {
                $fieldDefinition .= '->unique()';
            }

            $fields[] = $fieldDefinition . ';';

            // Add indexes for required fields and foreign keys
            if (in_array(str_replace('_', '.', $field), $requiredFields) || 
                str_ends_with($field, '_id')) {
                $indexes[] = "\$table->index('{$field}');";
            }
        }

        // Add soft deletes
        $fields[] = '$table->softDeletes();';

        return implode("\n            ", array_merge($fields, $indexes));
    }

    protected function getFieldDefinition(string $field, array $config): string
    {
        if (!isset($config['type'])) {
            return "\$table->string('{$field}')";
        }

        $definition = match ($config['type']) {
            'string' => $this->getStringFieldDefinition($field, $config),
            'integer' => "\$table->integer('{$field}')",
            'number' => "\$table->decimal('{$field}', 10, 2)",
            'boolean' => "\$table->boolean('{$field}')",
            'array' => "\$table->json('{$field}')",
            default => "\$table->string('{$field}')"
        };

        return $definition;
    }

    protected function getStringFieldDefinition(string $field, array $config): string
    {
        if (isset($config['format'])) {
            $definition = match ($config['format']) {
                'email' => "\$table->string('{$field}')",
                'date-time' => "\$table->dateTime('{$field}')",
                'date' => "\$table->date('{$field}')",
                'time' => "\$table->time('{$field}')",
                'uri', 'url' => "\$table->text('{$field}')",
                default => "\$table->string('{$field}')"
            };
        } else {
            $definition = "\$table->string('{$field}')";
        }

        // Add length constraint for strings
        if (isset($config['maxLength']) && $config['maxLength'] <= 255) {
            $definition = str_replace("string('{$field}')", "string('{$field}', {$config['maxLength']})", $definition);
        } elseif (isset($config['maxLength']) && $config['maxLength'] > 255) {
            $definition = str_replace("string('{$field}')", "text('{$field}')", $definition);
        }

        return $definition;
    }

    protected function writeMigrationStub($module, $tableName, $content): string
    {
        $stub = <<<STUB
            <?php

            use Illuminate\Database\Migrations\Migration;
            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\Schema;

            return new class extends Migration
            {
                public function up()
                {
                    Schema::create('{$tableName}', function (Blueprint \$table) {
                        \$table->id();
                        {$content}
                        \$table->timestamps();
                    });
                }

                public function down()
                {
                    Schema::dropIfExists('{$tableName}');
                }
            };
            STUB;

        $migrationName = 'create_' . $tableName . '_table';
        $migrationsPath = $module->getPath() . '/Database/Migrations';
        
        if (!File::exists($migrationsPath)) {
            File::makeDirectory($migrationsPath, 0755, true);
        }
        
        $path = $migrationsPath . '/' . date('Y_m_d_His') . '_' . $migrationName . '.php';

        file_put_contents($path, $stub);

        return $path;
    }

    /**
     * Ensure all required directories exist for the module
     */
    protected function ensureDirectoriesExist($module)
    {
        $directories = [
            $module->getPath() . '/App',
            $module->getPath() . '/App/Models',
            $module->getPath() . '/App/Http',
            $module->getPath() . '/App/Http/Requests',
            $module->getPath() . '/App/Http/Resources',
            $module->getPath() . '/Database',
            $module->getPath() . '/Database/Migrations',
        ];

        foreach ($directories as $directory) {
            if (!File::exists($directory)) {
                File::makeDirectory($directory, 0755, true);
                $this->info("Created directory: " . basename($directory));
            }
        }
    }
}