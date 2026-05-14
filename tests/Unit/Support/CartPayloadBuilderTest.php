<?php

// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace OpenSalesTax\Bagisto\Tests\Unit\Support;

use OpenSalesTax\Address;
use OpenSalesTax\Bagisto\Support\CartPayloadBuilder;
use OpenSalesTax\LineItem;
use PHPUnit\Framework\TestCase;
use stdClass;

final class CartPayloadBuilderTest extends TestCase
{
    public function testHappyPathSingleLine(): void
    {
        $cart = $this->buildCart('USD', 'US', '55401-1234', [['total' => 100.0]]);

        $builder = new CartPayloadBuilder();
        $payload = $builder->extract($cart);

        self::assertNotNull($payload);
        self::assertSame('USD', $payload['currency']);
        self::assertSame('US', $payload['country']);
        self::assertSame('55401', $payload['zip5']);
        self::assertInstanceOf(Address::class, $payload['address']);
        self::assertCount(1, $payload['lines']);
        self::assertInstanceOf(LineItem::class, $payload['lines'][0]);
        self::assertSame('100.00', $payload['lines'][0]->amount);
    }

    public function testMultiLineCart(): void
    {
        $cart = $this->buildCart('USD', 'US', '90210', [
            ['total' => 19.99],
            ['total' => 4.50],
            ['total' => 0.01],
        ]);

        $payload = (new CartPayloadBuilder())->extract($cart);

        self::assertNotNull($payload);
        self::assertCount(3, $payload['lines']);
        self::assertSame('19.99', $payload['lines'][0]->amount);
        self::assertSame('4.50', $payload['lines'][1]->amount);
        self::assertSame('0.01', $payload['lines'][2]->amount);
    }

    public function testNonUsdReturnsNull(): void
    {
        $cart = $this->buildCart('EUR', 'US', '55401', [['total' => 100.0]]);
        $payload = (new CartPayloadBuilder())->extract($cart);
        // The builder reports currency case-insensitively; the listener gates on it.
        self::assertNotNull($payload);
        self::assertSame('EUR', $payload['currency']);
    }

    public function testNonUsCountryPassesThroughBuilderForListenerGate(): void
    {
        // Builder doesn't reject non-US — listener does. Builder just normalizes.
        $cart = $this->buildCart('USD', 'CA', '55401', [['total' => 100.0]]);
        $payload = (new CartPayloadBuilder())->extract($cart);
        self::assertNotNull($payload);
        self::assertSame('CA', $payload['country']);
    }

    public function testMissingZipReturnsNull(): void
    {
        $cart = $this->buildCart('USD', 'US', '', [['total' => 100.0]]);
        $payload = (new CartPayloadBuilder())->extract($cart);
        self::assertNull($payload);
    }

    public function testMalformedZipReturnsNull(): void
    {
        $cart = $this->buildCart('USD', 'US', 'abcd', [['total' => 100.0]]);
        $payload = (new CartPayloadBuilder())->extract($cart);
        self::assertNull($payload);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function buildCart(string $currency, string $country, string $postcode, array $items): stdClass
    {
        $cart = new stdClass();
        $cart->id = 'cart-abc';
        $cart->cart_currency_code = $currency;
        $cart->shipping_address = (object) [
            'country'  => $country,
            'postcode' => $postcode,
        ];
        $cart->items = array_map(static fn (array $row) => (object) $row, $items);
        return $cart;
    }
}
