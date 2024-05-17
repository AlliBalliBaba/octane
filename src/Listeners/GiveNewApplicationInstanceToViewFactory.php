<?php

namespace Laravel\Octane\Listeners;

use Illuminate\View\ViewServiceProvider;

class GiveNewApplicationInstanceToViewFactory
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if (! $event->sandbox->resolved('view')) {
            return;
        }

        with($event->sandbox->make('view'), function ($view) use ($event) {
            $view->setContainer($event->sandbox);

            $view->share('app', $event->sandbox);
        });

        $provider = $event->sandbox->getProvider(ViewServiceProvider::class);
        if ($provider && method_exists($provider, 'setApplication')) {
            $provider->setApplication($event->sandbox);
        }
    }
}
