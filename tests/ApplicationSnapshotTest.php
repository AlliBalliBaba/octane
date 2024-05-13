<?php

namespace Laravel\Octane\Tests;

use Illuminate\Http\Request;
use Laravel\Octane\ApplicationSnapshot;

class ApplicationSnapshotTest extends TestCase
{

    public function test_the_snapshot_should_be_reloaded_twice_on_2_consecutive_requests()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/some-route', 'GET'),
            Request::create('/some-route', 'GET'),
        ]);
        $snapShotMock = $this->createMock(ApplicationSnapshot::class);
        $snapShotMock->expects($spy = $this->any())->method('loadSnapshotInto')->with($app);
        $worker->setApplicationSnapshot($snapShotMock);
        $app['router']->get('/first', fn() => 'Hello World');

        $worker->run();

        self::assertSame(2, $spy->numberOfInvocations());
    }

}
