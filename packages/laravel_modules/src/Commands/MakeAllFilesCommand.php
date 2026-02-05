<?php

namespace Bitsnio\Modules\Commands;

use Bitsnio\JsonToLaravelMigrations\JsonParser;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Bitsnio\Modules\Support\Config\GenerateConfigReader;
use Bitsnio\Modules\Support\Stub;
use Bitsnio\Modules\Traits\ModuleCommandTrait;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\HMS\App\Models\SubModule;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

class MakeAllFilesCommand extends GeneratorCommand
{
    use ModuleCommandTrait;

    /**
     * The name of argument name.
     *
     * @var string
     */
    protected $argumentName = 'model';

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'module:make-All';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create all files including model, migration, seeder, factory, controller and request file';

    public $created_files = [];

    public function handle(): int
    {
        if (parent::handle() === E_ERROR) {
            return E_ERROR;
        }
        $this->handleAllOption();
        return 0;
    }

    /**
     * Create a proper migration name:
     * ProductDetail: product_details
     * Product: products
     * @return string
     */
    private function createMigrationName()
    {
        $pieces = preg_split('/(?=[A-Z])/', $this->argument('model'), -1, PREG_SPLIT_NO_EMPTY);

        $string = '';
        foreach ($pieces as $i => $piece) {
            if ($i+1 < count($pieces)) {
                $string .= strtolower($piece) . '_';
            } else {
                $string .= Str::plural(strtolower($piece));
            }
        }

        return $string;
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['model', InputArgument::REQUIRED, 'The name of model will be created.'],
            ['module', InputArgument::OPTIONAL, 'The name of module will be used.'],
        ];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['fillable', null, InputOption::VALUE_OPTIONAL, 'The fillable attributes.', null],
        ];
    }

    protected function handleAllOption(){
        DB::beginTransaction();
        try{
            $base_path = base_path('Modules/'.$this->argument('module').'/');
            $this->created_files[] = $base_path.'APP/Models/'.$this->getModelName().'.php';
            //Create Request file
            $requestName = "{$this->getModelName()}Request";
            $this->call('module:make-request', array_filter([
                'name' => $requestName,
                'module' => $this->argument('module')
            ]));
            $this->created_files[] = $base_path.'App/HTTP/Requests/'.$requestName.'.php';

            //create factory file
            $this->call('module:make-factory', array_filter([
                'name' => $this->getModelName(),
                'module' => $this->argument('module')
            ]));
            $this->created_files[] = $base_path.'Database/Factories/'.$this->getModelName().'Factory.php';


            //create seeder file
            $seedName = "{$this->getModelName()}Seeder";
            $this->call('module:make-seed', array_filter([
                'name' => $seedName,
                'module' => $this->argument('module')
            ]));
            $this->created_files[] =$base_path.'Database/Seeders/'.$seedName.'.php';


            //create controller file
            $controllerName = "{$this->getModelName()}Controller";
            $this->call('module:make-controller', array_filter([
                'controller' => $controllerName,
                '--api' => true,
                'module' => $this->argument('module'),

            ]));
            $this->created_files[] = $base_path.'App/HTTP/Controllers/'.$controllerName.'.php';


            /**
             * First read all migrations in migration directory
             * store all files in array to keep record of migrations
             * create new migrations from JSON Schema
             * then call function currentMigarionFles it store new created files in created_files array
            */
            $migrationsPath = base_path('Modules/'.$this->argument('module').'/Database/migrations/');
            $initialFiles = File::files($migrationsPath);
            $initialFileNames = collect($initialFiles)->map(function ($file) {
                return $file->getFilename();
            })->toArray();

            $path = $this->laravel['modules']->getModulePath($this->getModuleName());
            $generatorPath = GenerateConfigReader::read('migration');
            $this->call('json:migrate', ['file' => 'Schema/'.$this->getModuleName().'/'.$this->getModelName().'.json', 'path' => $path . $generatorPath->getPath() . '/']);
            $this->currentMigrationFiles($initialFileNames, $migrationsPath);
            
            $this->generatePermissions();

            $this->createSubModule();

            $this->generateRoute($controllerName);
            DB::commit();
        }
        catch(Throwable $th){
            DB::rollBack();
            foreach ($this->created_files as $file) {
                if (File::exists($file)) {
                    File::delete($file);
                    // $this->warn('Deleted: ' . $file);
                }
            }

            throw new Exception('Failed to create resorce, actions reversed, try again, error :'.$th->getMessage());
        }

    }

    /**
     * @return mixed
     */
    protected function getTemplateContents()
    {
        $module = $this->laravel['modules']->findOrFail($this->getModuleName());

        return (new Stub('/model.stub', [
            'NAME'              => $this->getModelName(),
            'FILLABLE'          => $this->getFillable(),
            'NAMESPACE'         => $this->getClassNamespace($module),
            'CLASS'             => $this->getClass(),
            'LOWER_NAME'        => $module->getLowerName(),
            'MODULE'            => $this->getModuleName(),
            'STUDLY_NAME'       => $module->getStudlyName(),
            'MODULE_NAMESPACE'  => $this->laravel['modules']->config('namespace'),
        ]))->render();
    }

    /**
     * @return mixed
     */
    protected function getDestinationFilePath()
    {
        $path = $this->laravel['modules']->getModulePath($this->getModuleName());

        $modelPath = GenerateConfigReader::read('model');

        return $path . $modelPath->getPath() . '/' . $this->getModelName() . '.php';
    }

    /**
     * @return mixed|string
     */
    private function getModelName()
    {
        return Str::studly($this->argument('model'));
    }

    /**
     * @return string
     */
    private function getFillable()
    {
        $data = $this->getFileContent();
        $fillable = collect($data[$this->getModelName()])->keys()->toArray();
        if (!is_null($fillable)) {
            $arrays = (is_string($fillable)) ? explode(',', $fillable) : $fillable;
            return json_encode($arrays);
        }
        return '[]';
    }

    public function getFileContent($get_module_info = false){
        $parser = new JsonParser('Schema/'.$this->argument('module').'/'.$this->getModelName().'.json');
        return $parser->get($get_module_info);
    }

    /**
     * Get default namespace.
     *
     * @return string
     */
    public function getDefaultNamespace(): string
    {
        $module = $this->laravel['modules'];

        return $module->config('paths.generator.model.namespace') ?: $module->config('paths.generator.model.path', 'Entities');
    }

    private function generateRoute($controllerName){
        $name_space = "\nModules\\".$this->argument('module')."\App\Http\Controllers\\".$controllerName;
        $dynamic_route = "\nRoute::apiResource('".$this->createRouteName()."', ".$name_space."::class);";
        $route_file_path = base_path("Modules/".$this->argument('module')."/routes/api.php");
        // File::append($route_file_path, $name_space);
        File::append($route_file_path, $dynamic_route);
    }

    private function createRouteName(){
        return strtolower($this->argument('module'))."/".Str::plural(strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $this->getModelName())));
    }

    private function generatePermissions(){
        if (!Schema::hasTable('permissions')) $this->info('Stoped generating permissions, table permissions not found.');
        else{
            DB::table('permissions')->insert([
                ['name' => 'view '.$this->getModelName().'_'.$this->argument('module'), 'guard_name' => 'api', 'created_at' => now()],
                ['name' => 'viewAny '.$this->getModelName().'_'.$this->argument('module'), 'guard_name' => 'api', 'created_at' => now()],
                ['name' => 'create '.$this->getModelName().'_'.$this->argument('module'), 'guard_name' => 'api', 'created_at' => now()],
                ['name' => 'update '.$this->getModelName().'_'.$this->argument('module'), 'guard_name' => 'api', 'created_at' => now()],
                ['name' => 'delete '.$this->getModelName().'_'.$this->argument('module'), 'guard_name' => 'api', 'created_at' => now()],
                ['name' => 'restore '.$this->getModelName().'_'.$this->argument('module'), 'guard_name' => 'api', 'created_at' => now()],
                ['name' => 'force-delete '.$this->getModelName().'_'.$this->argument('module'), 'guard_name' => 'api', 'created_at' => now()],
            ]);
        }
        if (!Schema::hasTable('module_has_permissions')) $this->info('Stoped adding permissions for modules, table module_has_permissions not found.');
        else{
            DB::table('module_has_permissions')->insert([
                ['name' => 'view '.$this->getModelName().'_'.$this->argument('module'), 'module' => $this->argument('module'), 'created_at' => now()],
                ['name' => 'viewAny '.$this->getModelName().'_'.$this->argument('module'), 'module' => $this->argument('module'), 'created_at' => now()],
                ['name' => 'create '.$this->getModelName().'_'.$this->argument('module'), 'module' => $this->argument('module'), 'created_at' => now()],
                ['name' => 'update '.$this->getModelName().'_'.$this->argument('module'), 'module' => $this->argument('module'), 'created_at' => now()],
                ['name' => 'delete '.$this->getModelName().'_'.$this->argument('module'), 'module' => $this->argument('module'), 'created_at' => now()],
                ['name' => 'restore '.$this->getModelName().'_'.$this->argument('module'), 'module' => $this->argument('module'), 'created_at' => now()],
                ['name' => 'force-delete '.$this->getModelName().'_'.$this->argument('module'), 'module' => $this->argument('module'), 'created_at' => now()],
            ]);
        }
        if (!Schema::hasTable('route_has_permissions')) $this->info('Stoped adding permissions for routes, table route_has_permissions not found.');
        else{
            DB::table('route_has_permissions')->insert([
                ['permission' => 'view '.$this->getModelName().'_'.$this->argument('module'), 'method' => 'GET', 'route' => strtolower($this->argument('module'))."/".Str::plural(strtolower($this->getModelName())), 'created_at' => now()],
                ['permission' => 'viewAny '.$this->getModelName().'_'.$this->argument('module'), 'method' => 'GET', 'route' => strtolower($this->argument('module'))."/".Str::plural(strtolower($this->getModelName())), 'created_at' => now()],
                ['permission' => 'create '.$this->getModelName().'_'.$this->argument('module'), 'method' => 'POST', 'route' => strtolower($this->argument('module'))."/".Str::plural(strtolower($this->getModelName())), 'created_at' => now()],
                ['permission' => 'update '.$this->getModelName().'_'.$this->argument('module'), 'method' => 'PUT', 'route' => strtolower($this->argument('module'))."/".Str::plural(strtolower($this->getModelName())), 'created_at' => now()],
                ['permission' => 'delete '.$this->getModelName().'_'.$this->argument('module'), 'method' => 'DELETE', 'route' => strtolower($this->argument('module'))."/".Str::plural(strtolower($this->getModelName())), 'created_at' => now()],
                ['permission' => 'restore '.$this->getModelName().'_'.$this->argument('module'), 'method' => 'POST', 'route' => strtolower($this->argument('module'))."/restore-".Str::plural(strtolower($this->getModelName())), 'created_at' => now()],
                ['permission' => 'force-delete '.$this->getModelName().'_'.$this->argument('module'), 'method' => 'POST', 'route' => strtolower($this->argument('module'))."/-force-delete-".Str::plural(strtolower($this->getModelName())), 'created_at' => now()],
            ]);
        }
    }

    private function createSubModule(){
        $module_info = $this->getFileContent(true);
        $sub_module['route'] = $this->createRouteName();
        $sub_module['main_module_id'] = $module_info['id'];
        $sub_module['icon'] = $module_info['icon'];
        $sub_module['slug'] = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $this->getModelName()));
        $sub_module['menu_order'] = $module_info['menu_order'];
        $sub_module['title'] = $module_info['sub_module'];
        $sub_module['created_by'] = 1;
        SubModule::create($sub_module);
    }
    function currentMigrationFiles($initialFileNames, $migrationsPath){
        $currentFiles = File::files($migrationsPath);
        $currentFileNames = collect($currentFiles)->map(function ($file) {
            return $file->getFilename();
        })->toArray();

        // Step 3: Find new files created during execution
        $newFiles = array_diff($currentFileNames, $initialFileNames);
        foreach($newFiles as $file){
            $this->created_files[] = $migrationsPath.$file;
        }

    }
}
