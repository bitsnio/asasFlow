<?php

namespace AsasFlow\Features\ControllerGeneration\Contracts;

interface MenuParserInterface
{
    public function parse(array $menu): array;
    public function validate(array $menu): bool;
}
