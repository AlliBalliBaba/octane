<?php

namespace Laravel\Octane\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

class BindingStateTest extends TestCase
{
    public function test_container_instances_given_to_dependencies_will_always_be_fresh()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/first', 'GET'),
        ]);

        $app->bind(GenericObject::class, fn () => new GenericObject($app));

        $app['router']->get('/first', function (Application $app) {
            return [
                'app' => spl_object_hash($app),
                'state' => spl_object_hash($app->make(GenericObject::class)->state),
            ];
        });

        $worker->run();

        $this->assertEquals(
            $client->responses[0]->original['app'],
            $client->responses[0]->original['state']
        );
    }

    public function test_container_instances_given_to_dependencies_will_be_fresh_if_singleton_and_resolved_during_boot()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/first', 'GET'),
        ]);

        $app->singleton(GenericObject::class, fn($app) => new GenericObject($app));

        $app->make(GenericObject::class);

        $app['router']->get('/first', function (Application $app) {
            return [
                'app' => spl_object_hash($app),
                'state' => spl_object_hash($app->make(GenericObject::class)->state),
            ];
        });

        $worker->run();

        $this->assertEquals(
            $client->responses[0]->original['app'],
            $client->responses[0]->original['state']
        );
    }

    public function test_injecting_request_from_bind_will_always_be_fresh_since_sandbox_request_is_rebound()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/first?name=Taylor', 'GET'),
            Request::create('/first?name=Abigail', 'GET'),
        ]);

        $app->bind(GenericObject::class, fn() => new GenericObject($app['request']));

        $app['router']->get('/first', function (Application $app) {
            return $app->make(GenericObject::class)->state->query('name');
        });

        $worker->run();

        $this->assertEquals('Taylor', $client->responses[0]->original);
        $this->assertEquals('Abigail', $client->responses[1]->original);
    }

    public function test_injecting_request_from_singleton_can_be_fresh_if_it_is_not_resolved_during_boot()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/first?name=Taylor', 'GET'),
            Request::create('/first?name=Abigail', 'GET'),
        ]);

        $app->singleton(GenericObject::class, fn() => new GenericObject($app['request']));

        $app['router']->get('/first', function (Application $app) {
            return $app->make(GenericObject::class)->state->query('name');
        });

        $worker->run();

        $this->assertEquals('Taylor', $client->responses[0]->original);
        $this->assertEquals('Abigail', $client->responses[1]->original);
    }

    public function test_injecting_request_from_singleton_can_be_stale_if_it_is_resolved_during_boot()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/first?name=Taylor', 'GET'),
            Request::create('/first?name=Abigail', 'GET'),
        ]);

        $app->singleton(GenericObject::class, fn() => new GenericObject($app['request']));

        $app->make(GenericObject::class);

        $app['router']->get('/first', function (Application $app) {
            return $app->make(GenericObject::class)->state->query('name');
        });

        $worker->run();

        $this->assertNull($client->responses[0]->original);
        $this->assertNull($client->responses[1]->original);
    }

    public function test_container_variables_set_with_array_access_will_be_reset_across_requests()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/first', 'GET'),
            Request::create('/second', 'GET'),
        ]);
        $app['parameter'] = 'Abigail';
        $app['router']->get('/first', function (Application $app) {
            $app['parameter'] = 'Taylor';
            return $app['parameter'];
        });
        $app['router']->get('/second', function (Application $app) {
            return $app['parameter'];
        });

        $worker->run();

        $this->assertEquals('Taylor', $client->responses[0]->original);
        $this->assertEquals('Abigail', $client->responses[1]->original);
    }
}

class GenericObject
{
    public $state;

    public function __construct($state)
    {
        $this->state = $state;
    }
}
