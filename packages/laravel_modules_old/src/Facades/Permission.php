<?php

namespace Bitsnio\Modules\Facades;

use Illuminate\Support\Facades\Facade;

class Permission extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'modules.permission';
    }
}
