<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

namespace OpenSalesTax\Bagisto\Providers;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use OpenSalesTax\Bagisto\Listeners\CartTotalsListener;
use OpenSalesTax\Bagisto\Support\CartPayloadBuilder;
use OpenSalesTax\Bagisto\Support\EngineConnectionTester;
use OpenSalesTax\Bagisto\Support\OpenSalesTaxClientFactory;
use OpenSalesTax\Bagisto\Support\OpenSalesTaxClientFactoryInterface;
use OpenSalesTax\Bagisto\Support\RateCache;
use Psr\Log\LoggerInterface;

/**
 * Laravel service provider for opensalestax-bagisto.
 *
 * Auto-discovered via `composer.json` -> `extra.laravel.providers`. Merges
 * + publishes the package config, binds the client factory + cart builder
 * + rate cache, and attaches the CartTotalsListener to Bagisto's
 * `checkout.cart.collect.totals.after` event.
 */
final class OpenSalesTaxServiceProvider extends ServiceProvider
{
    /**
     * Path to the package config file (relative to project root).
     */
    private const CONFIG_PATH = __DIR__ . '/../../config/opensalestax.php';

    /**
     * Path to the admin route file.
     */
    private const ADMIN_ROUTES_PATH = __DIR__ . '/../Http/admin-routes.php';

    /**
     * Path to the package's blade view directory.
     */
    private const VIEWS_PATH = __DIR__ . '/../../resources/views';

    /**
     * Bagisto event fired after the cart totals collector finishes.
     */
    public const EVENT_NAME = 'checkout.cart.collect.totals.after';

    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIG_PATH, 'opensalestax');

        $this->app->singleton(OpenSalesTaxClientFactory::class, function ($app) {
            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);
            /** @var LoggerInterface $logger */
            $logger = $app->make(LoggerInterface::class);
            return new OpenSalesTaxClientFactory($config, $logger);
        });
        $this->app->alias(OpenSalesTaxClientFactory::class, OpenSalesTaxClientFactoryInterface::class);

        $this->app->singleton(CartPayloadBuilder::class, fn () => new CartPayloadBuilder());

        $this->app->singleton(RateCache::class, function ($app) {
            /** @var CacheRepository $cache */
            $cache = $app->make(CacheRepository::class);
            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);
            /** @var mixed $rawTtl */
            $rawTtl = $config->get('opensalestax.cache_ttl', 86400);
            $ttl = is_numeric($rawTtl) ? (int) $rawTtl : 86400;
            return new RateCache($cache, $ttl);
        });

        $this->app->singleton(EngineConnectionTester::class, function ($app) {
            /** @var LoggerInterface $logger */
            $logger = $app->make(LoggerInterface::class);
            return new EngineConnectionTester(
                $app->make(OpenSalesTaxClientFactoryInterface::class),
                $logger,
            );
        });

        $this->app->singleton(CartTotalsListener::class, function ($app) {
            /** @var ConfigRepository $config */
            $config = $app->make(ConfigRepository::class);
            /** @var LoggerInterface $logger */
            $logger = $app->make(LoggerInterface::class);
            return new CartTotalsListener(
                $app->make(OpenSalesTaxClientFactoryInterface::class),
                $app->make(CartPayloadBuilder::class),
                $app->make(RateCache::class),
                $config,
                $logger,
            );
        });
    }

    public function boot(Dispatcher $events): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                self::CONFIG_PATH => $this->resolvePublishedConfigPath(),
            ], 'config');
        }

        // Register the package's admin views under the `opensalestax`
        // namespace so the TestConnectionController can resolve
        // `opensalestax::admin.test-connection`.
        $this->loadViewsFrom(self::VIEWS_PATH, 'opensalestax');

        // Register the admin route file. Bagisto's admin auth middleware
        // (registered globally as the `admin` alias) gates these routes.
        if (file_exists(self::ADMIN_ROUTES_PATH)) {
            $this->loadRoutesFrom(self::ADMIN_ROUTES_PATH);
        }

        $events->listen(self::EVENT_NAME, [CartTotalsListener::class, 'handle']);
    }

    private function resolvePublishedConfigPath(): string
    {
        if (function_exists('config_path')) {
            return config_path('opensalestax.php');
        }
        return base_path('config/opensalestax.php');
    }
}
