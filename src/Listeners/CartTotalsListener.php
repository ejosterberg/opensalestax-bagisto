<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

namespace OpenSalesTax\Bagisto\Listeners;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use OpenSalesTax\Bagisto\Exceptions\OpenSalesTaxBagistoException;
use OpenSalesTax\Bagisto\Support\CartPayloadBuilder;
use OpenSalesTax\Bagisto\Support\OpenSalesTaxClientFactoryInterface;
use OpenSalesTax\Bagisto\Support\RateCache;
use OpenSalesTax\Client;
use OpenSalesTax\Exceptions\OpenSalesTaxException;
use OpenSalesTax\Responses\CalculateResponse;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Listener on `checkout.cart.collect.totals.after`.
 *
 * Bagisto dispatches the cart object after its built-in totals collector
 * has run. We check the gates (engine configured, USD, US, valid ZIP-5),
 * call the engine via the cached rate path, and overwrite the cart's
 * `tax_total` / `base_tax_total` with the engine's result.
 *
 * On any gate failure we silently yield to Bagisto. On an engine error we
 * either log + leave totals alone (fail-soft default) or rethrow a wrapped
 * exception that surfaces to checkout (`fail_hard` mode).
 */
final class CartTotalsListener
{
    public function __construct(
        private readonly OpenSalesTaxClientFactoryInterface $clientFactory,
        private readonly CartPayloadBuilder $builder,
        private readonly RateCache $cache,
        private readonly ConfigRepository $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(object $cart): void
    {
        $client = $this->clientFactory->make();
        $payload = $client === null ? null : $this->builder->extract($cart);
        if (!$this->gatesPass($client, $payload)) {
            return;
        }

        /** @var Client $client */
        /** @var array{currency: string, country: string, state: string|null, zip5: string, address: \OpenSalesTax\Address, lines: \OpenSalesTax\LineItem[]} $payload */
        $cartId = $this->resolveCartId($cart);

        // Per-state nexus filter (CP-3, v0.2.0). When configured, short-circuit
        // the engine call for any cart shipping to a state outside the merchant's
        // nexus list. Fail-closed on unresolvable state. See config/opensalestax.php.
        if ($this->shouldSkipForNexus($payload['state'])) {
            $this->logger->debug('opensalestax: nexus-filter skipped engine call', [
                'cart_id' => $cartId,
                'state'   => $payload['state'] ?? '(unresolvable)',
            ]);
            return;
        }

        $start = microtime(true);

        $response = $this->callEngine($client, $payload, $cartId, $start);
        if ($response === null) {
            return; // fail-soft path already logged + (if fail-hard) threw
        }

        $this->applyTaxTotal($cart, $response);
        $this->logger->info('opensalestax: cart tax recomputed', [
            'cart_id'    => $cartId,
            'rtt_ms'     => (int) round((microtime(true) - $start) * 1000),
            'line_count' => count($payload['lines']),
            'tax_total'  => (float) $response->taxTotal,
        ]);
    }

    /**
     * @param array{currency: string, country: string, zip5: string, address: \OpenSalesTax\Address, lines: \OpenSalesTax\LineItem[]}|null $payload
     */
    private function gatesPass(?Client $client, ?array $payload): bool
    {
        if ($client === null || $payload === null) {
            return false;
        }
        // Engine constitution Â§5: USD + US only.
        return $payload['currency'] === 'USD' && $payload['country'] === 'US';
    }

    /**
     * Call the engine via the rate cache. Returns the response on success,
     * null on a handled error (fail-soft); throws when fail-hard.
     *
     * @param array{address: \OpenSalesTax\Address, lines: \OpenSalesTax\LineItem[], zip5: string, currency: string, country: string} $payload
     */
    private function callEngine(Client $client, array $payload, string $cartId, float $start): ?CalculateResponse
    {
        try {
            return $this->cache->remember(
                $payload['zip5'],
                static fn () => $client->calculate($payload['address'], $payload['lines']),
            );
        } catch (OpenSalesTaxException $e) {
            $this->logger->warning('opensalestax: engine /v1/calculate failed', [
                'cart_id' => $cartId,
                'rtt_ms'  => (int) round((microtime(true) - $start) * 1000),
                'reason'  => $e->getMessage(),
            ]);
            $this->maybeRethrow('OpenSalesTax engine call failed: ' . $e->getMessage(), $e);
            return null;
        } catch (Throwable $e) {
            $this->logger->error('opensalestax: unexpected error in cart totals listener', [
                'cart_id' => $cartId,
                'reason'  => $e->getMessage(),
            ]);
            $this->maybeRethrow('OpenSalesTax listener failure: ' . $e->getMessage(), $e);
            return null;
        }
    }

    /**
     * Returns true when the per-state nexus filter is enabled AND the
     * destination state is NOT in the allowlist (or is unresolvable).
     */
    private function shouldSkipForNexus(?string $state): bool
    {
        $allowlist = $this->nexusAllowlist();
        if ($allowlist === []) {
            return false; // filter disabled
        }
        if ($state === null) {
            return true; // fail-closed when filter is on
        }
        return !in_array(strtoupper($state), $allowlist, true);
    }

    /**
     * Parse `opensalestax.nexus_states` into an array of upper-case 2-letter codes.
     *
     * @return list<string>
     */
    private function nexusAllowlist(): array
    {
        $rawValue = $this->config->get('opensalestax.nexus_states', '');
        $raw = is_string($rawValue) ? $rawValue : '';
        if (trim($raw) === '') {
            return [];
        }
        $parts = preg_split('/[\s,]+/', strtoupper($raw)) ?: [];
        $out = [];
        foreach ($parts as $part) {
            if (preg_match('/^[A-Z]{2}$/', $part) === 1 && !in_array($part, $out, true)) {
                $out[] = $part;
            }
        }
        return $out;
    }

    /**
     * Throw a wrapped exception only when fail_hard=true. Otherwise no-op.
     */
    private function maybeRethrow(string $message, Throwable $previous): void
    {
        if ((bool) $this->config->get('opensalestax.fail_hard', false)) {
            throw new OpenSalesTaxBagistoException($message, 0, $previous);
        }
    }

    private function applyTaxTotal(object $cart, CalculateResponse $response): void
    {
        $taxTotal = (float) $response->taxTotal;
        $cart->tax_total = $taxTotal;
        $cart->base_tax_total = $taxTotal;
        if (method_exists($cart, 'save')) {
            $cart->save();
        }
    }

    private function resolveCartId(object $cart): string
    {
        if (property_exists($cart, 'id') && is_scalar($cart->id)) {
            return (string) $cart->id;
        }
        if (method_exists($cart, 'getId')) {
            $value = $cart->getId();
            if (is_scalar($value)) {
                return (string) $value;
            }
        }
        return 'unknown';
    }
}
