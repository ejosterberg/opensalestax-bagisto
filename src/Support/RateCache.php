<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

namespace OpenSalesTax\Bagisto\Support;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use OpenSalesTax\Responses\CalculateResponse;

/**
 * Laravel-cache-backed wrapper around the OST engine's calculate response.
 *
 * The full response is cached as a normalized array (the engine's raw
 * payload shape) keyed by destination ZIP-5. On hit the array is rebuilt
 * into a typed `CalculateResponse` via the SDK's `fromArray` factory.
 *
 * We store the raw payload (not the readonly object) for two reasons:
 *  - cache drivers that serialize via PHP serialize() handle readonly objects
 *    fine on modern PHP, but the array form is portable across drivers
 *    (json-encoded redis, file, memcached) and survives SDK refactors
 *  - testability â€” tests can hand-craft cache fixtures
 *
 * Cache key shape: `ost:rate:{zip5}`. The TTL defaults to 24h.
 */
final class RateCache
{
    public function __construct(
        private readonly CacheRepository $cache,
        private readonly int $ttlSeconds,
    ) {
    }

    /**
     * Fetch from cache or compute via $resolver. Stores the response payload
     * on miss so subsequent calls within the TTL window hit the cache.
     *
     * @param callable():CalculateResponse $resolver
     */
    public function remember(string $zip5, callable $resolver): CalculateResponse
    {
        $key = self::keyFor($zip5);
        /** @var array<string, mixed>|null $cached */
        $cached = $this->cache->get($key);
        if (is_array($cached)) {
            return CalculateResponse::fromArray($cached);
        }

        $fresh = $resolver();
        $this->cache->put($key, self::responseToArray($fresh), $this->ttlSeconds);
        return $fresh;
    }

    /**
     * Compute the cache key for a destination ZIP-5.
     */
    public static function keyFor(string $zip5): string
    {
        return 'ost:rate:' . $zip5;
    }

    /**
     * @return array<string, mixed>
     */
    private static function responseToArray(CalculateResponse $response): array
    {
        $lines = [];
        foreach ($response->lines as $line) {
            $jurisdictions = [];
            foreach ($line->jurisdictions as $j) {
                $jur = [
                    'name'     => $j->name,
                    'type'     => $j->type,
                    'rate_pct' => $j->ratePct,
                ];
                if ($j->tax !== null) {
                    $jur['tax'] = $j->tax;
                }
                $jurisdictions[] = $jur;
            }
            $lineArr = [
                'amount'        => $line->amount,
                'category'      => $line->category,
                'tax'           => $line->tax,
                'rate_pct'      => $line->ratePct,
                'jurisdictions' => $jurisdictions,
            ];
            if ($line->note !== null) {
                $lineArr['note'] = $line->note;
            }
            $lines[] = $lineArr;
        }
        return [
            'subtotal'   => $response->subtotal,
            'tax_total'  => $response->taxTotal,
            'lines'      => $lines,
            'disclaimer' => $response->disclaimer,
        ];
    }
}
