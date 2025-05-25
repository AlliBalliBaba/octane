<?php

namespace Laravel\Octane\Tests;

use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Laravel\Octane\ApplicationInstanceResetter;

class ApplicationInstanceResetterTest extends TestCase
{
    public function test_the_global_container_should_not_be_reset_when_creating_a_snapshot()
    {
        $application = new Application;
        $resetter = new ApplicationInstanceResetter($application);

        [$snapshot, $sandbox] = $resetter->resetInstance();

        $this->assertSame($sandbox, $application);
        $this->assertNotSame($snapshot, $application);
        $this->assertSame($application, Container::getInstance());
    }

    public function test_forget_a_binding_after_reloading_from_snapshot()
    {
        $application = new Application;
        $resetter = new ApplicationInstanceResetter($application);
        $resetter->resetInstance();
        $application->bind('generic', fn () => new GenericObject($application));

        [$snapshot, $sandbox] = $resetter->resetInstance();

        $this->assertSame($sandbox, $application);
        $this->assertNotSame($snapshot, $application);
        $this->assertFalse($application->bound('generic'));
        $this->assertFalse($snapshot->bound('generic'));
    }

    public function test_forget_a_value_set_from_array_access_after_reloading_from_snapshot()
    {
        $application = new Application;
        $resetter = new ApplicationInstanceResetter($application);
        $resetter->resetInstance();
        $application['key'] = 'value';

        [$snapshot, $sandbox] = $resetter->resetInstance();

        $this->assertSame($sandbox, $application);
        $this->assertNotSame($snapshot, $application);
        $this->assertFalse($application->bound('key'));
    }

    public function test_forget_a_singleton_after_reloading_from_snapshot()
    {
        $application = new Application;
        $resetter = new ApplicationInstanceResetter($application);
        $resetter->resetInstance();
        $application->singleton(GenericObject::class);

        [$snapshot, $sandbox] = $resetter->resetInstance();

        $this->assertSame($sandbox, $application);
        $this->assertNotSame($snapshot, $application);
        $this->assertFalse($application->bound(GenericObject::class));
    }

    public function test_forget_an_instance_after_reloading_from_snapshot()
    {
        $application = new Application;
        $resetter = new ApplicationInstanceResetter($application);
        $resetter->resetInstance();
        $application->instance(GenericObject::class, new GenericObject($application));

        [$snapshot, $sandbox] = $resetter->resetInstance();

        $this->assertSame($sandbox, $application);
        $this->assertNotSame($snapshot, $application);
        $this->assertFalse($application->bound(GenericObject::class));
    }

    public function test_reset_the_base_path_after_reloading_from_snapshot()
    {
        $application = new Application;
        $resetter = new ApplicationInstanceResetter($application);
        $application->setBasePath('initial/path');
        $resetter->resetInstance();
        $application->setBasePath('changed/path');

        [$snapshot, $sandbox] = $resetter->resetInstance();

        $this->assertSame($sandbox, $application);
        $this->assertNotSame($snapshot, $application);
        $this->assertSame('initial/path', $application->basePath());
    }
}
