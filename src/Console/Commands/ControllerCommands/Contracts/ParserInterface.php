<?php

namespace Bitsnio\AsasFlow\Console\Commands\ControllerCommands\Contracts;

interface ParserInterface
{
    public function parse(array $data): array;
    public function validate(array $data): bool;
}
