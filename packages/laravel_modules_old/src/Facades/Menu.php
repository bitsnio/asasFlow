<?php

namespace Bitsnio\Modules\Facades;

use Illuminate\Support\Facades\Facade;

class Menu extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'modules.menu';
    }
}
