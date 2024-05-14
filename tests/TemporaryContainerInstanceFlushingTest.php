<?php

namespace Laravel\Octane\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TemporaryContainerInstanceFlushingTest extends TestCase
{
    public function test_temporary_container_bindings_are_flushed()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/first', 'GET'),
            Request::create('/first', 'GET'),
        ]);

        $app['config']['octane.flush'] = ['random-string'];

        $app->singleton('random-string', function () {
            return Str::random(10);
        });

        $app['random-string'];

        $app['router']->get('/first', function (Application $app) {
            return $app['random-string'];
        });

        $worker->run();

        $this->assertNotEquals(
            $client->responses[0]->original,
            $client->responses[1]->original
        );
    }

    public function test_scoped_container_instances_should_be_flushed()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/first', 'GET'),
            Request::create('/first', 'GET'),
        ]);

        $app->scoped('random-string', fn () => Str::random(10));

        $app['random-string'];

        $app['router']->get('/first', function (Application $app) {
            return $app['random-string'];
        });

        $worker->run();

        $this->assertNotEquals(
            $client->responses[0]->original,
            $client->responses[1]->original
        );
    }
}
