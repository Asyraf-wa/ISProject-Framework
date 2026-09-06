<?php

namespace IsProject\Framework\Tests;

use Illuminate\Support\Facades\File;
use IsProject\Framework\IsProjectServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /** Everything generated during a test lands here and is removed afterwards. */
    protected string $sandbox = 'build/generated';

    protected function getPackageProviders($app): array
    {
        return [IsProjectServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        // The web middleware group encrypts the session, which needs a key.
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        // Keep generated files out of the Testbench skeleton application.
        $app['config']->set('isproject.paths', [
            'model' => $this->sandbox.'/Models',
            'controller' => $this->sandbox.'/Controllers',
            'request' => $this->sandbox.'/Requests',
            'policy' => $this->sandbox.'/Policies',
            'factory' => $this->sandbox.'/Factories',
            'views' => $this->sandbox.'/views',
        ]);

        // Route appending needs a real file; the tests assert on it separately.
        $app['config']->set('isproject.routes_file', null);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path('build'));

        parent::tearDown();
    }

    protected function generated(string $path): string
    {
        return base_path($this->sandbox.'/'.$path);
    }

    /** Assert the file is syntactically valid PHP by shelling out to php -l. */
    protected function assertValidPhp(string $path): void
    {
        $this->assertFileExists($path);

        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1', $output, $status);

        $this->assertSame(0, $status, "Generated PHP is invalid:\n".implode("\n", $output));
    }
}
