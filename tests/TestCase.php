<?php

namespace Tests;

use Bitsnio\AsasFlow\AsasFlowServiceProvider;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = new Application(dirname(__DIR__));

        Container::setInstance($app);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);

        $app->instance('app', $app);
        $app->instance(Container::class, $app);
        $app->instance('request', Request::create('http://localhost'));

        $tempBootstrap = sys_get_temp_dir() . '/asasflow-test-bootstrap/cache';
        if (!is_dir($tempBootstrap)) {
            mkdir($tempBootstrap, 0777, true);
        }
        $app->useBootstrapPath(dirname($tempBootstrap));

        $config = new ConfigRepository([
            'app' => [
                'name' => 'AsasFlowTest',
                'env' => 'testing',
                'debug' => false,
                'url' => 'http://localhost',
                'timezone' => 'UTC',
                'locale' => 'en',
                'fallback_locale' => 'en',
                'key' => 'base64:' . base64_encode(str_repeat('a', 32)),
                'cipher' => 'AES-256-CBC',
                'providers' => ServiceProvider::defaultProviders()->merge([
                    AsasFlowServiceProvider::class,
                ])->toArray(),
            ],
            'auth' => [
                'defaults' => [
                    'guard' => 'api',
                    'passwords' => 'users',
                ],
                'guards' => [
                    'web' => [
                        'driver' => 'session',
                        'provider' => 'users',
                    ],
                    'api' => [
                        'driver' => 'token',
                        'provider' => 'users',
                    ],
                ],
                'providers' => [
                    'users' => [
                        'driver' => 'eloquent',
                        'model' => \Illuminate\Foundation\Auth\User::class,
                    ],
                ],
            ],
            'cache' => [
                'default' => 'array',
                'stores' => [
                    'array' => [
                        'driver' => 'array',
                        'serialize' => false,
                    ],
                    'file' => [
                        'driver' => 'file',
                        'path' => sys_get_temp_dir() . '/asasflow-test-cache',
                    ],
                ],
                'prefix' => 'asasflow_test_',
            ],
            'database' => [
                'default' => 'sqlite',
                'connections' => [
                    'sqlite' => [
                        'driver' => 'sqlite',
                        'database' => ':memory:',
                        'prefix' => '',
                    ],
                ],
                'migrations' => 'migrations',
            ],
            'session' => [
                'driver' => 'array',
                'lifetime' => 120,
                'expire_on_close' => false,
                'encrypt' => false,
                'cookie' => 'asasflow_test_session',
                'path' => '/',
                'domain' => null,
                'same_site' => 'lax',
            ],
            'view' => [
                'paths' => [dirname(__DIR__) . '/resources/views'],
                'compiled' => sys_get_temp_dir(),
            ],
        ]);

        $app->instance('config', $config);

        $config->set('asasflow', require dirname(__DIR__) . '/config/asasflow.php');
        $config->set('asasflow-cache', require dirname(__DIR__) . '/config/asasflow-cache.php');
        $config->set('asasflow.routes.enabled', false);
        $config->set('asasflow.cache.enabled', true);
        $config->set('asasflow-cache.enabled', true);
        $config->set('asasflow-cache.store', 'array');
        $config->set('asasflow-cache.dashboard.enabled', false);

        $app->singleton(HttpKernelContract::class, function ($app) {
            return new class($app, $app['router']) extends HttpKernel {
                protected $bootstrappers = [
                    \Illuminate\Foundation\Bootstrap\RegisterFacades::class,
                    \Illuminate\Foundation\Bootstrap\BootProviders::class,
                ];
            };
        });
        $app->singleton(ConsoleKernelContract::class, function ($app) {
            return new class($app, $app['events']) extends ConsoleKernel {
                protected $bootstrappers = [
                    \Illuminate\Foundation\Bootstrap\RegisterFacades::class,
                    \Illuminate\Foundation\Bootstrap\BootProviders::class,
                ];
            };
        });
        $app->singleton(ExceptionHandlerContract::class, ExceptionHandler::class);

        foreach ($config->get('app.providers') as $providerClass) {
            if (class_exists($providerClass)) {
                $app->register($providerClass);
            }
        }

        $app->make(ConsoleKernelContract::class)->bootstrap();

        Route::get('/', fn () => response()->success(['status' => 'ok']));

        return $app;
    }
}
