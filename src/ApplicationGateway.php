<?php

namespace Laravel\Octane;

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Laravel\Octane\Events\RequestHandled;
use Laravel\Octane\Events\RequestReceived;
use Laravel\Octane\Events\RequestTerminated;
use Laravel\Octane\Facades\Octane;
use Symfony\Component\HttpFoundation\Response;

class ApplicationGateway
{
    use DispatchesEvents;

    public function __construct(protected ApplicationResetter $resetter, protected Application $snapshot, protected Application $sandbox)
    {
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request): Response
    {
        $request->enableHttpMethodParameterOverride();

        // reset all default services
        $this->resetter->prepareApplicationForNextRequest($request);
        $this->resetter->prepareApplicationForNextOperation();

        $this->dispatchEvent($this->sandbox, new RequestReceived($this->snapshot, $this->sandbox, $request));

        if (Octane::hasRouteFor($request->getMethod(), '/'.$request->path())) {
            return Octane::invokeRoute($request, $request->getMethod(), '/'.$request->path());
        }

        return tap($this->resetter->kernel->handle($request), function ($response) use ($request) {
            $this->dispatchEvent($this->sandbox, new RequestHandled($this->sandbox, $request, $response));
        });
    }

    /**
     * "Shut down" the application after a request.
     */
    public function terminate(Request $request, Response $response): void
    {
        $this->resetter->kernel->terminate($request, $response);

        $this->dispatchEvent($this->sandbox, new RequestTerminated($this->snapshot, $this->sandbox, $request, $response));

        $route = $request->route();

        if ($route instanceof Route && method_exists($route, 'flushController')) {
            $route->flushController();
        }
    }
}
