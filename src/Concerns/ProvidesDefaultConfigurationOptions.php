<?php

namespace Laravel\Octane\Concerns;

trait ProvidesDefaultConfigurationOptions
{
    /**
     * This method is kept for BC reasons.
     */
    public static function prepareApplicationForNextRequest(): array
    {
        return [];
    }

    /**
     * This method is kept for BC reasons.
     */
    public static function prepareApplicationForNextOperation(): array
    {
        return [];
    }

    /**
     * Get the container bindings / services that should be pre-resolved by default.
     */
    public static function defaultServicesToWarm(): array
    {
        return [
            'auth',
            'cache',
            'cache.store',
            'config',
            'cookie',
            'db',
            'db.factory',
            'db.transactions',
            'encrypter',
            'files',
            'hash',
            'log',
            'router',
            'routes',
            'session',
            'session.store',
            'translator',
            'url',
            'view',
        ];
    }
}
