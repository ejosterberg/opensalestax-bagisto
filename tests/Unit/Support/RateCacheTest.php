<?php

// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace OpenSalesTax\Bagisto\Tests\Unit\Support;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use OpenSalesTax\Bagisto\Support\RateCache;
use OpenSalesTax\Responses\CalculatedLine;
use OpenSalesTax\Responses\CalculateResponse;
use OpenSalesTax\Responses\JurisdictionRate;
use PHPUnit\Framework\TestCase;

final class RateCacheTest extends TestCase
{
    public function testCacheMissCallsResolverAndStoresPayload(): void
    {
        $store = new InMemoryCache();
        $cache = new RateCache($store, ttlSeconds: 60);

        $calls = 0;
        $resolver = function () use (&$calls): CalculateResponse {
            $calls++;
            return $this->buildResponse('1.00');
        };

        $response = $cache->remember('55401', $resolver);

        self::assertSame(1, $calls);
        self::assertSame('1.00', $response->taxTotal);
        self::assertArrayHasKey('ost:rate:55401', $store->store);
    }

    public function testCacheHitDoesNotCallResolver(): void
    {
        $store = new InMemoryCache();
        $cache = new RateCache($store, ttlSeconds: 60);

        $resolver = function (): CalculateResponse {
            return $this->buildResponse('1.23');
        };

        // First call populates.
        $cache->remember('55401', $resolver);

        // Second call should NOT invoke resolver.
        $calls = 0;
        $second = $cache->remember('55401', function () use (&$calls): CalculateResponse {
            $calls++;
            return $this->buildResponse('999.00'); // would-be different value to prove it isn't called
        });

        self::assertSame(0, $calls);
        self::assertSame('1.23', $second->taxTotal);
    }

    public function testKeyForReturnsExpectedShape(): void
    {
        self::assertSame('ost:rate:55401', RateCache::keyFor('55401'));
        self::assertSame('ost:rate:90210', RateCache::keyFor('90210'));
    }

    public function testNonArrayCachedValueIsTreatedAsMiss(): void
    {
        $store = new InMemoryCache();
        $store->store['ost:rate:55401'] = 'garbage-not-an-array';

        $cache = new RateCache($store, ttlSeconds: 60);
        $calls = 0;
        $response = $cache->remember('55401', function () use (&$calls): CalculateResponse {
            $calls++;
            return $this->buildResponse('2.50');
        });
        self::assertSame(1, $calls);
        self::assertSame('2.50', $response->taxTotal);
    }

    /**
     * Build a minimal CalculateResponse fixture.
     */
    private function buildResponse(string $taxTotal): CalculateResponse
    {
        return new CalculateResponse(
            subtotal: '100.00',
            taxTotal: $taxTotal,
            lines: [
                new CalculatedLine(
                    amount: '100.00',
                    category: 'general',
                    tax: $taxTotal,
                    ratePct: '7.875',
                    jurisdictions: [
                        new JurisdictionRate(name: 'Minnesota State Tax', type: 'state', ratePct: '6.875', tax: $taxTotal),
                    ],
                ),
            ],
            disclaimer: 'Tax calculations are provided as-is for convenience...',
        );
    }
}

/**
 * Minimal in-memory implementation of CacheRepository for unit tests.
 *
 * @internal
 */
final class InMemoryCache implements CacheRepository
{
    /** @var array<string, mixed> */
    public array $store = [];

    public function has($key): bool
    {
        return array_key_exists($key, $this->store);
    }

    public function get($key, $default = null): mixed
    {
        return $this->store[$key] ?? value($default);
    }

    public function pull($key, $default = null): mixed
    {
        $val = $this->get($key, $default);
        unset($this->store[$key]);
        return $val;
    }

    public function put($key, $value, $ttl = null): bool
    {
        $this->store[$key] = $value;
        return true;
    }

    public function add($key, $value, $ttl = null): bool
    {
        if (array_key_exists($key, $this->store)) {
            return false;
        }
        $this->store[$key] = $value;
        return true;
    }

    public function increment($key, $value = 1): int|bool
    {
        $current = (int) ($this->store[$key] ?? 0);
        $this->store[$key] = $current + (int) $value;
        return $this->store[$key];
    }

    public function decrement($key, $value = 1): int|bool
    {
        return $this->increment($key, -(int) $value);
    }

    public function forever($key, $value): bool
    {
        $this->store[$key] = $value;
        return true;
    }

    public function remember($key, $ttl, \Closure $callback): mixed
    {
        if (!array_key_exists($key, $this->store)) {
            $this->store[$key] = $callback();
        }
        return $this->store[$key];
    }

    public function sear($key, \Closure $callback): mixed
    {
        return $this->rememberForever($key, $callback);
    }

    public function rememberForever($key, \Closure $callback): mixed
    {
        if (!array_key_exists($key, $this->store)) {
            $this->store[$key] = $callback();
        }
        return $this->store[$key];
    }

    public function forget($key): bool
    {
        unset($this->store[$key]);
        return true;
    }

    public function getStore()
    {
        return null;
    }

    public function many(array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = $this->store[$k] ?? null;
        }
        return $out;
    }

    public function putMany(array $values, $ttl = null): bool
    {
        foreach ($values as $k => $v) {
            $this->store[$k] = $v;
        }
        return true;
    }

    public function clear(): bool
    {
        $this->store = [];
        return true;
    }

    public function delete($key): bool
    {
        return $this->forget($key);
    }

    public function deleteMultiple($keys): bool
    {
        foreach ($keys as $k) {
            $this->forget($k);
        }
        return true;
    }

    public function getMultiple($keys, $default = null): iterable
    {
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = $this->get($k, $default);
        }
        return $out;
    }

    public function setMultiple($values, $ttl = null): bool
    {
        foreach ($values as $k => $v) {
            $this->store[$k] = $v;
        }
        return true;
    }

    public function set($key, $value, $ttl = null): bool
    {
        return $this->put($key, $value, $ttl);
    }
}
