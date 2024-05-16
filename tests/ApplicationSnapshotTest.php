<?php

namespace Laravel\Octane\Tests;

use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Laravel\Octane\ApplicationSnapshot;

class ApplicationSnapshotTest extends TestCase
{
    public function test_the_global_container_should_not_be_reset_when_creating_a_snapshot()
    {
        $application = new Application();

        ApplicationSnapshot::createSnapshotFrom($application);

        $this->assertSame($application, Container::getInstance());
    }

    public function test_forget_a_binding_after_reloading_from_snapshot()
    {
        $application = new Application();
        $snapshot = ApplicationSnapshot::createSnapshotFrom($application);
        $application->bind('generic', fn () => new GenericObject($application));

        $snapshot->loadSnapshotInto($application);

        $this->assertFalse($application->bound('generic'));
    }

    public function test_forget_a_value_set_from_array_access_after_reloading_from_snapshot()
    {
        $application = new Application();
        $snapshot = ApplicationSnapshot::createSnapshotFrom($application);
        $application['key'] = 'value';

        $snapshot->loadSnapshotInto($application);

        $this->assertFalse($application->bound('key'));
    }

    public function test_forget_a_singleton_after_reloading_from_snapshot()
    {
        $application = new Application();
        $snapshot = ApplicationSnapshot::createSnapshotFrom($application);
        $application->singleton(GenericObject::class);

        $snapshot->loadSnapshotInto($application);

        $this->assertFalse($application->bound(GenericObject::class));
    }

    public function test_forget_an_instance_after_reloading_from_snapshot()
    {
        $application = new Application();
        $snapshot = ApplicationSnapshot::createSnapshotFrom($application);
        $application->instance(GenericObject::class, new GenericObject($application));

        $snapshot->loadSnapshotInto($application);

        $this->assertFalse($application->bound(GenericObject::class));
    }

    public function test_reset_the_base_path_after_reloading_from_snapshot()
    {
        $application = new Application();
        $application->setBasePath('initial/path');
        $snapshot = ApplicationSnapshot::createSnapshotFrom($application);
        $application->setBasePath('changed/path');

        $snapshot->loadSnapshotInto($application);

        $this->assertSame('initial/path', $application->basePath());
    }

    public function test_the_snapshot_is_loaded_twice_into_the_application_2_consecutive_requests()
    {
        [$app, $worker, $client] = $this->createOctaneContext([
            Request::create('/some-route', 'GET'),
            Request::create('/some-route', 'GET'),
        ]);
        $snapShotMock = $this->createMock(ApplicationSnapshot::class);
        $snapShotMock->expects($spy = $this->any())->method('loadSnapshotInto')->with($app);
        $worker->setAppSnapshot($snapShotMock);
        $app['router']->get('/first', fn () => 'Hello World');

        $worker->run();

        $this->assertSame(2, $spy->numberOfInvocations());
    }

    /**
     * The ApplicationSnapshot strategy only reliably works if there are no private
     * properties in the Application and Container classes. If this test breaks
     * then private properties were added to these classes inside of Laravel
     */
    public function test_the_application_container_has_no_private_properties()
    {
        $appReflection = new \ReflectionClass(Application::class);
        $containerReflection = new \ReflectionClass(Container::class);

        $privatePropsInApp = $appReflection->getProperties(\ReflectionProperty::IS_PRIVATE);
        $privatePropsInContainer = $containerReflection->getProperties(\ReflectionProperty::IS_PRIVATE);

        $this->assertSame(0, count($privatePropsInApp));
        $this->assertSame(0, count($privatePropsInContainer));
    }
}
