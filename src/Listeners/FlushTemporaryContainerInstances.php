<?php

namespace Laravel\Octane\Listeners;

class FlushTemporaryContainerInstances
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if (method_exists($event->sandbox, 'resetScope')) {
            $event->sandbox->resetScope();
        }

        if (method_exists($event->sandbox, 'forgetScopedInstances')) {
            $event->sandbox->forgetScopedInstances();
        }

        foreach ($event->sandbox->make('config')->get('octane.flush', []) as $binding) {
            $event->sandbox->forgetInstance($binding);
        }
    }
}
