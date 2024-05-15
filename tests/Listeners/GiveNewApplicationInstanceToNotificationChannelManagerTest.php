<?php

namespace Laravel\Octane\Tests\Listeners;

use Illuminate\Http\Request;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\NotificationServiceProvider;
use Laravel\Octane\Tests\TestCase;


class GiveNewApplicationInstanceToNotificationChannelManagerTest extends TestCase
{
    public function test_the_notification_manager_drivers_should_be_flushed_on_request()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/first', 'GET'),
        ]);
        $app->register(NotificationServiceProvider::class);
        $channelManager = $app->make(ChannelManager::class);
        $channelManager->driver();
        $initialDriverCount = count($channelManager->getDrivers());
        $app['router']->get('/first', fn () => 'Hello World');

        $worker->run();

        $this->assertSame(1, $initialDriverCount);
        $this->assertSame(0, count($channelManager->getDrivers()));
    }
}
