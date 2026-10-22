<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");
        if ($connection !== 'pgsql' || ! is_string($database) || ! str_ends_with($database, '_test')) {
            throw new \RuntimeException('Tests require a dedicated PostgreSQL database ending in _test.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }
}
