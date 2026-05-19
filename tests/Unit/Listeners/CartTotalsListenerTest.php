<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

namespace OpenSalesTax\Bagisto\Tests\Unit\Listeners;

use GuzzleHttp\Psr7\Response;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use OpenSalesTax\Bagisto\Exceptions\OpenSalesTaxBagistoException;
use OpenSalesTax\Bagisto\Listeners\CartTotalsListener;
use OpenSalesTax\Bagisto\Support\CartPayloadBuilder;
use OpenSalesTax\Bagisto\Support\OpenSalesTaxClientFactoryInterface;
use OpenSalesTax\Bagisto\Support\RateCache;
use OpenSalesTax\Bagisto\Tests\Unit\Support\InMemoryCache;
use OpenSalesTax\Client;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface as Psr18ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\NullLogger;
use stdClass;

final class CartTotalsListenerTest extends TestCase
{
    public function testUnconfiguredFactoryYieldsSilently(): void
    {
        $listener = $this->buildListener(client: null, failHard: false);
        $cart = $this->buildCart('USD', 'US', '55401', 100.0);
        $listener->handle($cart);
        self::assertObjectNotHasProperty('tax_total', $cart);
    }

    public function testNonUsdCurrencyYieldsSilently(): void
    {
        $listener = $this->buildListener(client: $this->workingClient('7.88'), failHard: false);
        $cart = $this->buildCart('EUR', 'US', '55401', 100.0);
        $listener->handle($cart);
        self::assertObjectNotHasProperty('tax_total', $cart);
    }

    public function testNonUsCountryYieldsSilently(): void
    {
        $listener = $this->buildListener(client: $this->workingClient('7.88'), failHard: false);
        $cart = $this->buildCart('USD', 'CA', 'M5V 2T6', 100.0);
        $listener->handle($cart);
        self::assertObjectNotHasProperty('tax_total', $cart);
    }

    public function testMissingZipYieldsSilently(): void
    {
        $listener = $this->buildListener(client: $this->workingClient('7.88'), failHard: false);
        $cart = $this->buildCart('USD', 'US', '', 100.0);
        $listener->handle($cart);
        self::assertObjectNotHasProperty('tax_total', $cart);
    }

    public function testHappyPathWritesEngineTaxOntoCart(): void
    {
        $listener = $this->buildListener(client: $this->workingClient('7.88'), failHard: false);
        $cart = $this->buildCart('USD', 'US', '55401', 100.0);
        $listener->handle($cart);

        self::assertSame(7.88, $cart->tax_total);
        self::assertSame(7.88, $cart->base_tax_total);
    }

    public function testEngineErrorFailSoftLeavesCartUntouched(): void
    {
        $listener = $this->buildListener(client: $this->explodingClient(), failHard: false);
        $cart = $this->buildCart('USD', 'US', '55401', 100.0);
        $listener->handle($cart);
        self::assertObjectNotHasProperty('tax_total', $cart);
    }

    public function testEngineErrorFailHardThrows(): void
    {
        $listener = $this->buildListener(client: $this->explodingClient(), failHard: true);
        $cart = $this->buildCart('USD', 'US', '55401', 100.0);
        $this->expectException(OpenSalesTaxBagistoException::class);
        $listener->handle($cart);
    }

    // --- CP-3 per-state nexus filter (v0.2.0) -------------------------------

    public function testNexusFilterDisabledByDefault(): void
    {
        // No `nexus_states` config => engine called normally.
        $listener = $this->buildListener(client: $this->workingClient('7.88'), failHard: false);
        $cart = $this->buildCart('USD', 'US', '55401', 100.0, state: 'MN');
        $listener->handle($cart);
        self::assertSame(7.88, $cart->tax_total);
    }

    public function testNexusFilterEmptyStringPreservesV01Behavior(): void
    {
        $listener = $this->buildListener(
            client: $this->workingClient('7.88'),
            failHard: false,
            nexusStates: '',
        );
        $cart = $this->buildCart('USD', 'US', '55401', 100.0, state: 'MN');
        $listener->handle($cart);
        self::assertSame(7.88, $cart->tax_total);
    }

    public function testNexusFilterAllowsDestinationStateInList(): void
    {
        $listener = $this->buildListener(
            client: $this->workingClient('7.88'),
            failHard: false,
            nexusStates: 'MN,WI,IA',
        );
        $cart = $this->buildCart('USD', 'US', '55401', 100.0, state: 'MN');
        $listener->handle($cart);
        self::assertSame(7.88, $cart->tax_total);
    }

    public function testNexusFilterShortCircuitsForOutOfStateDestination(): void
    {
        $listener = $this->buildListener(
            client: $this->explodingClient(), // engine must NOT be called
            failHard: false,
            nexusStates: 'MN,WI,IA',
        );
        $cart = $this->buildCart('USD', 'US', '94016', 100.0, state: 'CA');
        $listener->handle($cart);
        self::assertObjectNotHasProperty('tax_total', $cart);
    }

    public function testNexusFilterFailClosedOnUnresolvableState(): void
    {
        $listener = $this->buildListener(
            client: $this->explodingClient(), // engine must NOT be called
            failHard: false,
            nexusStates: 'MN,WI,IA',
        );
        // No state on the address — fail-closed when filter is active.
        $cart = $this->buildCart('USD', 'US', '55401', 100.0, state: null);
        $listener->handle($cart);
        self::assertObjectNotHasProperty('tax_total', $cart);
    }

    public function testNexusFilterAcceptsFullStateName(): void
    {
        $listener = $this->buildListener(
            client: $this->workingClient('7.88'),
            failHard: false,
            nexusStates: 'MN',
        );
        // Bagisto's address might store full name (e.g. via state_name) — accept it.
        $cart = $this->buildCart('USD', 'US', '55401', 100.0, state: 'Minnesota');
        $listener->handle($cart);
        self::assertSame(7.88, $cart->tax_total);
    }

    public function testNexusFilterNormalizesLowercaseInput(): void
    {
        $listener = $this->buildListener(
            client: $this->workingClient('7.88'),
            failHard: false,
            nexusStates: 'mn, wi, ia',
        );
        $cart = $this->buildCart('USD', 'US', '55401', 100.0, state: 'mn');
        $listener->handle($cart);
        self::assertSame(7.88, $cart->tax_total);
    }

    // --- end CP-3 -----------------------------------------------------------

    private function buildListener(?Client $client, bool $failHard, string $nexusStates = ''): CartTotalsListener
    {
        $config = new ConfigRepository(['opensalestax' => [
            'fail_hard'    => $failHard,
            'cache_ttl'    => 60,
            'nexus_states' => $nexusStates,
        ]]);

        return new CartTotalsListener(
            new FixedClientFactory($client),
            new CartPayloadBuilder(),
            new RateCache($this->fakeCache(), 60),
            $config,
            new NullLogger(),
        );
    }

    private function buildCart(string $currency, string $country, string $postcode, float $total, ?string $state = null): stdClass
    {
        $cart = new stdClass();
        $cart->id = 'cart-test-1';
        $cart->cart_currency_code = $currency;
        $address = [
            'country'  => $country,
            'postcode' => $postcode,
        ];
        if ($state !== null) {
            $address['state'] = $state;
        }
        $cart->shipping_address = (object) $address;
        $item = new stdClass();
        $item->total = $total;
        $cart->items = [$item];
        return $cart;
    }

    /**
     * Build a real OpenSalesTax\Client backed by a canned-response PSR-18 client.
     */
    private function workingClient(string $taxTotal): Client
    {
        $body = json_encode([
            'subtotal'   => '100.00',
            'tax_total'  => $taxTotal,
            'lines'      => [
                [
                    'amount'        => '100.00',
                    'category'      => 'general',
                    'tax'           => $taxTotal,
                    'rate_pct'      => '7.875',
                    'jurisdictions' => [
                        ['name' => 'Minnesota State', 'type' => 'state', 'rate_pct' => '6.875', 'tax' => $taxTotal],
                    ],
                ],
            ],
            'disclaimer' => 'Tax calculations are provided as-is...',
        ]);

        $http = new CannedPsr18Client(new Response(200, ['Content-Type' => 'application/json'], (string) $body));
        return new Client('http://test.invalid', null, 1.0, $http);
    }

    private function explodingClient(): Client
    {
        $http = new ExplodingPsr18Client();
        return new Client('http://test.invalid', null, 1.0, $http);
    }

    private function fakeCache(): CacheRepository
    {
        return new InMemoryCache();
    }
}

/**
 * Returns a preset Client (or null) for the listener to consume.
 *
 * @internal
 */
final class FixedClientFactory implements OpenSalesTaxClientFactoryInterface
{
    public function __construct(private readonly ?Client $client)
    {
    }

    public function make(): ?Client
    {
        return $this->client;
    }
}

/**
 * PSR-18 client that returns a canned response on every request.
 *
 * @internal
 */
final class CannedPsr18Client implements Psr18ClientInterface
{
    public function __construct(private readonly ResponseInterface $response)
    {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->response;
    }
}

/**
 * PSR-18 client that always throws a PSR-18 transport exception. Drives the
 * SDK to throw OpenSalesTaxNetworkException, which the listener handles.
 *
 * @internal
 */
final class ExplodingPsr18Client implements Psr18ClientInterface
{
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        throw new class ('connection refused') extends \RuntimeException implements ClientExceptionInterface {
        };
    }
}
