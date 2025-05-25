<?php

namespace Laravel\Octane;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use ReflectionObject;
use ReflectionProperty;

class ApplicationInstanceResetter
{

    /**
     * @var ReflectionProperty[]
     */
    private array $appVars;
    private Application $sandbox;
    private Application $snapshot;

    public function __construct(Application $sandbox)
    {
        $this->sandbox = $sandbox;
        $this->appVars = $this->getNonStaticVars($sandbox);
    }

    /**
     * Reset the sandbox instance to the application instance.
     */
    public function resetInstance(): array
    {
        Facade::clearResolvedInstances();

        $snapshot = $this->snapshot ??= clone $this->sandbox;
        foreach ($this->appVars as $var) {
            $var->setValue(
                $this->sandbox,
                $var->getValue($snapshot)
            );
        }

        return [$snapshot, $this->sandbox];
    }

    private function getNonStaticVars(Application $app): array
    {
        $reflection = new ReflectionObject($app);
        return array_filter(
            $reflection->getProperties(),
            fn($property) => !$property->isStatic()
        );
    }

}
