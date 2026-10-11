<?php

namespace Bitsnio\Modules\Laravel;

use Illuminate\Container\Container;
use Bitsnio\Modules\FileRepository;

class LaravelFileRepository extends FileRepository
{
    /**
     * {@inheritdoc}
     */
    protected function createModule(Container $app, string $name, string $path): Module
    {
        return new Module($app, $name, $path);
    }
}
