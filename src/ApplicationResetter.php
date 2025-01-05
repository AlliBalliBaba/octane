<?php

namespace Laravel\Octane;

use Carbon\Carbon;
use Carbon\Laravel\ServiceProvider as CarbonServiceProvider;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\NamespacedItemResolver;
use Illuminate\Support\Str;
use Inertia\ResponseFactory;
use Laravel\Scout\EngineManager;
use Laravel\Socialite\Contracts\Factory;
use Livewire\LivewireManager;
use Monolog\ResettableInterface;

class ApplicationResetter
{

    public Kernel $kernel;
    private Application $sandbox;
    private \Illuminate\Contracts\Config\Repository $config;
    private $translator;
    private $cookies = null;
    private $db = null;
    private $session = null;
    private $url = null;

    private $log = null;
    private $logDefaultDriver = null;
    private $auth = null;
    private $arrayCache = null;

    private $octaneHttps = false;
    private $mailManager = null;
    private $channelManager = null;

    private $responseFactory = null;
    private $livewireManager = null;
    private $engineManager = null;
    private $socialiteFactory = null;

    private $originalAppLocale = null;

    public function __construct(Application $sandbox)
    {
        $this->sandbox = $sandbox;
        $this->config = $sandbox['config'];
        $this->translator = $sandbox->make('translator');
        if ($sandbox->resolved('cookie')) {
            $this->cookies = $sandbox->make('cookie');
        }
        if ($sandbox->resolved('db')) {
            $this->db = $sandbox->make('db');
        }
        if ($sandbox->resolved('session')) {
            $this->session = $sandbox->make('session');
        }
        $this->url = $sandbox['url'];
        if ($this->config->get('cache.stores.array')) {
            $this->arrayCache = $this->sandbox->make('cache')->store('array');
        }
        if ($sandbox->resolved('log')) {
            $this->log = $sandbox->make('log');
            $this->logDefaultDriver = $this->log->driver();
        }

        if ($sandbox->resolved('auth.driver')) {
            $sandbox->forgetInstance('auth.driver');
        }

        if ($sandbox->resolved('auth')) {
            $this->auth = $sandbox->make('auth');
        }

        if ($this->sandbox->resolved('mail.manager')) {
            $this->mailManager = $this->sandbox->make('mail.manager');
        }

        if ($this->sandbox->resolved(ChannelManager::class)) {
            $this->channelManager = $this->sandbox->make(ChannelManager::class);
        }

        $this->octaneHttps = $this->config->get('octane.https');

        if ($sandbox->resolved(ResponseFactory::class)) {
            $this->responseFactory = $sandbox->make(ResponseFactory::class);
        }

        if ($sandbox->resolved(LivewireManager::class)) {
            $this->livewireManager = $sandbox->make(LivewireManager::class);
        }

        if ($sandbox->resolved(EngineManager::class)) {
            $this->engineManager = $sandbox->make(EngineManager::class);
        }

        if ($sandbox->resolved(Factory::class)) {
            $this->socialiteFactory = $sandbox->make(Factory::class);
        }

        $this->originalAppLocale = $this->sandbox->getLocale();

        $this->kernel = $sandbox->make(Kernel::class);
    }

    public function prepareApplicationForNextRequest(Request $request)
    {
        $this->flushLocaleState();
        $this->flushCookies();
        $this->flushSession();
        $this->flushAuthenticationState();
        $this->enforceRequestScheme($request);
        $this->ensureRequestServerPortMatchesScheme($request);
        $this->giveNewRequestInstanceToApplication($request);
    }

    public function prepareApplicationForNextOperation()
    {
        $this->createConfigurationSandbox();
        $this->createUrlGeneratorSandbox();
        $this->giveApplicationInstanceToMailManager();
        $this->giveApplicationInstanceToNotificationChannelManager();
        $this->flushDatabaseState();
        $this->flushLogContext();
        $this->flushMonologState();
        $this->flushArrayCache();
        Str::flushCache();
        $this->flushTranslatorCache();

        // First-Party Packages...
        $this->prepareInertiaForNextOperation();
        $this->prepareLivewireForNextOperation();
        $this->prepareScoutForNextOperation();
        $this->prepareSocialiteForNextOperation();
    }


    private function flushLocaleState(): void
    {
        $this->translator->setLocale($this->config->get('app.locale'));
        $this->translator->setFallback($this->config->get('app.fallback_locale'));

        // resetting the locale is an expensive operation
        // only do it if the locale has changed
        if(Carbon::getLocale() !== $this->originalAppLocale) {
            (new CarbonServiceProvider($this->sandbox))->updateLocale();
        }
    }

    private function flushCookies()
    {
        if ($this->cookies !== null) {
            $this->cookies->flushQueuedCookies();
        }
    }

    private function flushSession(): void
    {
        if ($this->session === null) {
            return;
        }

        $driver = $this->session->driver();

        $driver->flush();
        $driver->regenerate();
    }

    private function flushAuthenticationState(): void
    {
        if ($this->auth !== null) {
            $this->auth->forgetGuards();
        }
    }

    public function enforceRequestScheme(Request $request): void
    {
        if (!$this->octaneHttps) {
            return;
        }

        $this->url->forceScheme('https');

        $request->server->set('HTTPS', 'on');
    }

    public function ensureRequestServerPortMatchesScheme(Request $request): void
    {
        $port = $request->getPort();

        if (is_null($port) || $port === '') {
            $request->server->set(
                'SERVER_PORT',
                $request->getScheme() === 'https' ? 443 : 80
            );
        }
    }

    public function giveNewRequestInstanceToApplication(Request $request): void
    {
        $this->sandbox->instance('request', $request);
    }

    private function createConfigurationSandbox()
    {
        $this->sandbox->instance('config', clone $this->config);
    }

    private function createUrlGeneratorSandbox()
    {
        $this->sandbox->instance('url', clone $this->url);
    }

    private function giveApplicationInstanceToMailManager(): void
    {
        if ($this->mailManager !== null) {
            $this->mailManager->forgetMailers();
        }
    }

    private function giveApplicationInstanceToNotificationChannelManager(): void
    {
        if ($this->channelManager !== null) {
            $this->channelManager->forgetDrivers();
        }
    }

    private function flushDatabaseState(): void
    {
        if ($this->db === null) {
            return;
        }

        foreach ($this->db->getConnections() as $connection) {
            $connection->forgetRecordModificationState();
            $connection->flushQueryLog();

            // refresh query duration handling
            if (
                method_exists($connection, 'resetTotalQueryDuration')
                && method_exists($connection, 'allowQueryDurationHandlersToRunAgain')
            ) {
                $connection->resetTotalQueryDuration();
                $connection->allowQueryDurationHandlersToRunAgain();
            }
        }
    }

    private function flushLogContext(): void
    {
        if ($this->log === null) {
            return;
        }

        if (method_exists($this->log, 'flushSharedContext')) {
            $this->log->flushSharedContext();
        }

        if (method_exists($this->logDefaultDriver, 'withoutContext')) {
            $this->logDefaultDriver->withoutContext();
        }

        if (method_exists($this->log, 'withoutContext')) {
            $this->log->withoutContext();
        }
    }

    private function flushArrayCache(): void
    {
        if ($this->arrayCache) {
            $this->arrayCache->flush();
        }
    }

    private function flushMonologState(): void
    {
        if ($this->log === null) {
            return;
        }

        foreach ($this->log->getChannels() as $channel) {
            $logger = $channel->getLogger();
            if ($logger instanceof ResettableInterface) {
                $logger->reset();
            }
        }
    }

    private function flushTranslatorCache()
    {
        if ($this->translator instanceof NamespacedItemResolver) {
            $this->translator->flushParsedKeys();
        }
    }

    private function prepareInertiaForNextOperation(): void
    {
        if ($this->responseFactory === null) {
            return;
        }

        if (method_exists($this->responseFactory, 'flushShared')) {
            $this->responseFactory->flushShared();
        }
    }

    private function prepareLivewireForNextOperation(): void
    {
        if ($this->livewireManager === null) {
            return;
        }

        if (method_exists($this->livewireManager, 'flushState')) {
            $this->livewireManager->flushState();
        }
    }

    private function prepareScoutForNextOperation(): void
    {
        if ($this->engineManager === null) {
            return;
        }

        if (!method_exists($this->engineManager, 'forgetEngines')) {
            return;
        }

        $this->engineManager->forgetEngines();
    }

    private function prepareSocialiteForNextOperation(): void
    {
        if ($this->socialiteFactory === null) {
            return;
        }

        if (!method_exists($this->socialiteFactory, 'forgetDrivers')) {
            return;
        }

        $this->socialiteFactory->forgetDrivers();
    }

}
