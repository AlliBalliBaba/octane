<?php

namespace Laravel\Octane\Listeners;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Laravel\Octane\Tests\TestCase;

use function Livewire\invade;

class GiveNewApplicationInstanceToRouterTest extends TestCase
{
    public function test_router_has_new_instance()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/first', 'GET'),
            Request::create('/second', 'GET'),
        ]);

        $app['router']->middleware('web')->get('/first', function () {
            $route = collect(app('router')->getRoutes()->getRoutes())->firstWhere(fn (Route $route) => $route->uri() === 'second');

            return [
                spl_object_id(app()),
                spl_object_id(invade($route)->container),
            ];
        });

        $app['router']->middleware('web')->get('/second', function () {
            $route = collect(app('router')->getRoutes()->getRoutes())->firstWhere(fn (Route $route) => $route->uri() === 'first');

            return [
                spl_object_id(app()),
                spl_object_id(invade($route)->container),
            ];
        });

        $worker->run();

        $this->assertEquals($client->responses[0]->getData()[0], $client->responses[0]->getData()[1]);
        $this->assertEquals($client->responses[1]->getData()[0], $client->responses[1]->getData()[1]);
    }
}
