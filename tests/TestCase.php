<?php

namespace Tests;

abstract class TestCase extends \Illuminate\Foundation\Testing\TestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $connection = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.{$connection}.database");
        if (! $app->environment('testing') || ! in_array([$connection, $database], [['sqlite', ':memory:'], ['pgsql', 'blindem_test']], true)) {
            throw new \RuntimeException('Pruebas bloqueadas: solo se permite SQLite en memoria o la base PostgreSQL blindem_test en APP_ENV=testing.');
        }

        return $app;
    }
}
