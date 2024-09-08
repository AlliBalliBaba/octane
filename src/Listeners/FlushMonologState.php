<?php

namespace Laravel\Octane\Listeners;

use Monolog\ResettableInterface;

class FlushMonologState
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

        foreach ($event->sandbox->make('log')->getChannels() as $channel) {
            $logger = $channel->getLogger();
            if($logger instanceof ResettableInterface){
                $logger->reset();
            }
        }
    }
}
