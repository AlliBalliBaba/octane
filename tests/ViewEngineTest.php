<?php

namespace Laravel\Octane\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

class ViewEngineTest extends TestCase
{


    public function test_forget_blade_view_engine()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/first', 'GET'),
            Request::create('/first', 'GET'),
        ]);

        $app['router']->get('/first', function (Application $app) {
            return spl_object_hash($app['view.engine.resolver']->resolve('blade'));
        });

        $worker->run();

        $this->assertNotEquals($client->responses[0]->original, $client->responses[1]->original);
    }

    public function test_forget_php_view_engine()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/first', 'GET'),
            Request::create('/first', 'GET'),
        ]);

        $app['router']->get('/first', function (Application $app) {
            return spl_object_hash($app['view.engine.resolver']->resolve('blade'));
        });

        $worker->run();

        $this->assertNotEquals($client->responses[0]->original, $client->responses[1]->original);
    }

}
