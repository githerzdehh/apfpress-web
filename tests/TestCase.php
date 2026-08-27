<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        // Docker supplies the local MySQL environment before PHPUnit is loaded.
        // Override the bootstrapped configuration here so database-refreshing
        // tests can never migrate or truncate the developer's local catalogue.
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('database.connections.sqlite.foreign_key_constraints', true);
        $app['config']->set('apf.import_download_media', false);
        $app->make('db')->purge();

        return $app;
    }
}
