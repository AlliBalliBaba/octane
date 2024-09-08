<?php

namespace Laravel\Octane\Listeners;

class FlushLogContext
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if (! $event->sandbox->resolved('log')) {
            return;
        }
        $log = $event->sandbox['log'];

        if (method_exists($log, 'flushSharedContext')) {
            $log->flushSharedContext();
        }

        if (method_exists($log->driver(), 'withoutContext')) {
            $log->driver()->withoutContext();
        }

        if (method_exists($log, 'withoutContext')) {
            $log->withoutContext();
        }
    }
}
