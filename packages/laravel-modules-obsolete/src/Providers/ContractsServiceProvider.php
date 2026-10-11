<?php

namespace Bitsnio\Modules\Providers;

use Illuminate\Support\ServiceProvider;
use Bitsnio\Modules\Contracts\RepositoryInterface;
use Bitsnio\Modules\Laravel\LaravelFileRepository;

class ContractsServiceProvider extends ServiceProvider
{
    /**
     * Register some binding.
     */
    public function register()
    {
        $this->app->bind(RepositoryInterface::class, LaravelFileRepository::class);
    }
}
