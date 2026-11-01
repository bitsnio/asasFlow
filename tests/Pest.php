<?php

require_once __DIR__ . '/TestCase.php';

use Tests\TestCase;

pest()->extend(TestCase::class)
    ->in('Feature', 'Unit');

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});
