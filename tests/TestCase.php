<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        if (is_file(dirname(__DIR__).'/bootstrap/cache/config.php')) {
            throw new \RuntimeException(
                'Testes bloqueados: execute "composer test" para limpar o cache e usar SQLite em memoria.'
            );
        }

        parent::setUp();

        if (! app()->environment('testing') || config('database.default') !== 'sqlite') {
            throw new \RuntimeException('Testes bloqueados fora do ambiente SQLite de testes.');
        }
    }
}
