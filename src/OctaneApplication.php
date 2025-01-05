<?php

namespace Laravel\Octane;

use Illuminate\Foundation\Application;

class OctaneApplication extends Application
{

    private static array $allowInstantResolution = [
        "Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull" => 1,
        "Laravel\Octane\Listeners\FlushTemporaryContainerInstances" => 1,
        "Illuminate\Foundation\Http\Middleware\ValidatePostSize" => 1,
        "Illuminate\Routing\Contracts\ControllerDispatcher" => 1,
        "Illuminate\Contracts\Routing\ResponseFactory" => 1,
        "Illuminate\Contracts\Events\Dispatcher" => 1,
        "Laravel\Octane\Listeners\FlushOnce" => 1,
        "App\Http\Middleware\TrimStrings" => 1,

        "octane" => 1,
        "events" => 1,
        "config" => 1,
        "env" => 1,
        "request" => 1,
        "url" => 1,
        "redirect" => 1,
        "session.store" => 1
    ];

    private array $instantlyResolved = [];

    public function make($abstract, array $parameters = [])
    {
        if (!array_key_exists($abstract, self::$allowInstantResolution)) {
            return parent::make($abstract, $parameters);
        }

        if (!array_key_exists($abstract, $this->instantlyResolved)) {
            $this->instantlyResolved[$abstract] = parent::make($abstract, $parameters);
        }
        return $this->instantlyResolved[$abstract];
    }

    public function instance($abstract, $instance)
    {
        if(array_key_exists($abstract, self::$allowInstantResolution)) {
            $this->instantlyResolved[$abstract] = $instance;
        }
        parent::instance($abstract, $instance);
    }

    public function bind($abstract, $concrete = null, $shared = false)
    {
        if(array_key_exists($abstract, self::$allowInstantResolution)) {
            unset($this->instantlyResolved[$abstract]);
        }
        parent::bind($abstract, $concrete, $shared);
    }

    public function singleton($abstract, $concrete = null)
    {
        if(array_key_exists($abstract, self::$allowInstantResolution)) {
            unset($this->instantlyResolved[$abstract]);
        }
        parent::singleton($abstract, $concrete);
    }

}
