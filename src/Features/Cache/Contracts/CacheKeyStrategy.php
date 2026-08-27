<?php

namespace Bitsnio\AsasFlow\Features\Cache\Contracts;

use Illuminate\Http\Request;

interface CacheKeyStrategy
{
    public function generate(Request $request, array $context = []): string;
}
