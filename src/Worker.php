<?php

namespace Laravel\Octane;

use Closure;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Laravel\Octane\Contracts\Client;
use Laravel\Octane\Contracts\ServesStaticFiles;
use Laravel\Octane\Contracts\Worker as WorkerContract;
use Laravel\Octane\Events\TaskReceived;
use Laravel\Octane\Events\TaskTerminated;
use Laravel\Octane\Events\TickReceived;
use Laravel\Octane\Events\TickTerminated;
use Laravel\Octane\Events\WorkerErrorOccurred;
use Laravel\Octane\Events\WorkerStarting;
use Laravel\Octane\Events\WorkerStopping;
use Laravel\Octane\Exceptions\TaskExceptionResult;
use Laravel\Octane\Swoole\TaskResult;
use RuntimeException;
use Throwable;

class Worker implements WorkerContract
{
    use DispatchesEvents;

    protected $requestHandledCallbacks = [];

    /**
     * The current application instance.
     *
     * @var Application
     */
    protected $app;

    /**
     * A clone of the warmed up initial application.
     *
     * @var ApplicationSnapshot
     */
    protected $appSnapshot;

    public function __construct(
        protected ApplicationFactory $appFactory,
        protected Client $client
    ) {
    }

    /**
     * Boot / initialize the Octane worker.
     */
    public function boot(array $initialInstances = []): void
    {
        // First we will create an instance of the Laravel application that can serve as
        // the base container instance we will clone from on every request. This will
        // also perform the initial bootstrapping that's required by the framework.
        $this->app = $app = $this->appFactory->createApplication(
            array_merge(
                $initialInstances,
                [Client::class => $this->client],
            )
        );

        $this->dispatchEvent($app, new WorkerStarting($app));
    }

    /**
     * Handle an incoming request and send the response to the client.
     */
    public function handle(Request $request, RequestContext $context): void
    {
        if ($this->client instanceof ServesStaticFiles &&
            $this->client->canServeRequestAsStaticFile($request, $context)) {
            $this->client->serveStaticFile($request, $context);

            return;
        }

        // We will clone the application instance so that we have a clean copy to switch
        // back to once the request has been handled. This allows us to easily delete
        // certain instances that got resolved / mutated during a previous request.
        $this->createAppSnapshot();

        $gateway = new ApplicationGateway($this->appSnapshot, $this->app);

        try {
            $responded = false;

            ob_start();

            $response = $gateway->handle($request);

            $output = ob_get_contents();

            if (ob_get_level()) {
                ob_end_clean();
            }

            // Here we will actually hand the incoming request to the Laravel application so
            // it can generate a response. We'll send this response back to the client so
            // it can be returned to a browser. This gateway will also dispatch events.
            $this->client->respond(
                $context,
                $octaneResponse = new OctaneResponse($response, $output),
            );

            $responded = true;

            $this->invokeRequestHandledCallbacks($request, $response, $this->app);

            $gateway->terminate($request, $response);
        } catch (Throwable $e) {
            $this->handleWorkerError($e, $this->app, $request, $context, $responded);
        } finally {
            // After the request handling process has completed we will unset some variables
            // plus reset the current application state back to its original state before
            // it was cloned. Then we will be ready for the next worker iteration loop.
            unset($gateway, $sandbox, $request, $response, $octaneResponse, $output);

            CurrentApplication::set($this->app);
        }
    }

    /**
     * Handle an incoming task.
     *
     * @param  mixed  $data
     * @return mixed
     */
    public function handleTask($data)
    {
        $result = false;

        // We will clone the application instance so that we have a clean copy to switch
        // back to once the request has been handled. This allows us to easily delete
        // certain instances that got resolved / mutated during a previous request.
        $this->createAppSnapshot();

        try {
            $this->dispatchEvent($this->app, new TaskReceived($this->appSnapshot, $this->app, $data));

            $result = $data();

            $this->dispatchEvent($this->app, new TaskTerminated($this->appSnapshot, $this->app, $data, $result));
        } catch (Throwable $e) {
            $this->dispatchEvent($this->app, new WorkerErrorOccurred($e, $this->app));

            return TaskExceptionResult::from($e);
        } finally {
            $this->app->flush();
        }

        return new TaskResult($result);
    }

    /**
     * Handle an incoming tick.
     */
    public function handleTick(): void
    {
        $this->createAppSnapshot();

        try {
            $this->dispatchEvent($this->app, new TickReceived($this->appSnapshot, $this->app));
            $this->dispatchEvent($this->app, new TickTerminated($this->appSnapshot, $this->app));
        } catch (Throwable $e) {
            $this->dispatchEvent($this->app, new WorkerErrorOccurred($e, $this->app));
        } finally {
            $this->app->flush();
        }
    }

    /**
     * Handle an uncaught exception from the worker.
     */
    protected function handleWorkerError(
        Throwable $e,
        Application $app,
        Request $request,
        RequestContext $context,
        bool $hasResponded
    ): void {
        if (! $hasResponded) {
            $this->client->error($e, $app, $request, $context);
        }

        $this->dispatchEvent($app, new WorkerErrorOccurred($e, $app));
    }

    /**
     * Invoke the request handled callbacks.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @param  \Illuminate\Foundation\Application  $sandbox
     */
    protected function invokeRequestHandledCallbacks($request, $response, $sandbox): void
    {
        foreach ($this->requestHandledCallbacks as $callback) {
            $callback($request, $response, $sandbox);
        }
    }

    /**
     * Register a closure to be invoked when requests are handled.
     *
     * @return $this
     */
    public function onRequestHandled(Closure $callback)
    {
        $this->requestHandledCallbacks[] = $callback;

        return $this;
    }

    /**
     * Get the application instance being used by the worker.
     */
    public function application(): Application
    {
        if (! $this->app) {
            throw new RuntimeException('Worker has not booted. Unable to access application.');
        }

        return $this->app;
    }

    /**
     * Terminate the worker.
     */
    public function terminate(): void
    {
        $this->dispatchEvent($this->app, new WorkerStopping($this->app));
    }

    protected function createAppSnapshot(): void
    {
        if (! isset($this->appSnapshot)) {
            $this->appSnapshot = ApplicationSnapshot::createSnapshotFrom($this->app);
        }
        $this->appSnapshot->loadSnapshotInto($this->app);
    }
}
