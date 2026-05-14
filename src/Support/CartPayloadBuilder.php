<?php

// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace OpenSalesTax\Bagisto\Support;

use OpenSalesTax\Address;
use OpenSalesTax\LineItem;

/**
 * Build an OST engine request payload from a Bagisto cart.
 *
 * The cart is typed as `object` because we deliberately don't depend on
 * `bagisto/bagisto` (full Bagisto Composer requires would pull the entire
 * Laravel application stack). Every accessor is guarded with property_exists
 * or method_exists so a misshapen cart can't blow up the listener.
 *
 * The builder returns null whenever a required gate input is missing —
 * the listener treats null as "yield to Bagisto's built-in tax".
 */
final class CartPayloadBuilder
{
    /**
     * @return array{currency: string, country: string, zip5: string, address: Address, lines: LineItem[]}|null
     */
    public function extract(object $cart): ?array
    {
        $currency = self::stringProp($cart, 'cart_currency_code');
        $shipping = self::firstAvailable($cart, ['shipping_address', 'billing_address']);
        if ($currency === '' || $shipping === null) {
            return null;
        }

        $country = self::stringProp($shipping, 'country');
        $zip5 = self::extractZip5(self::stringProp($shipping, 'postcode'));
        $lines = self::buildLineItems(self::cartItems($cart));
        if ($zip5 === null || $lines === null) {
            return null;
        }

        return [
            'currency' => strtoupper($currency),
            'country'  => strtoupper($country),
            'zip5'     => $zip5,
            'address'  => new Address(zip5: $zip5),
            'lines'    => $lines,
        ];
    }

    /**
     * Convert a list of cart-item objects into typed LineItems.
     * Returns null if the list is empty OR any line is unpriceable.
     *
     * @param object[] $items
     * @return LineItem[]|null
     */
    private static function buildLineItems(array $items): ?array
    {
        if ($items === []) {
            return null;
        }
        $lines = [];
        foreach ($items as $item) {
            $amount = self::lineAmount($item);
            if ($amount === null) {
                return null; // bail rather than under-tax a line we can't price
            }
            $lines[] = new LineItem(amount: $amount, category: 'general');
        }
        return $lines;
    }

    /**
     * Best-effort read of a string property/method off a duck-typed object.
     */
    private static function stringProp(object $subject, string $name): string
    {
        $value = self::readValue($subject, $name);
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * Try a list of property names; return the first non-null object value.
     *
     * @param string[] $names
     */
    private static function firstAvailable(object $subject, array $names): ?object
    {
        foreach ($names as $name) {
            $value = self::readValue($subject, $name);
            if (is_object($value)) {
                return $value;
            }
        }
        return null;
    }

    /**
     * Read a property by direct access OR conventional Laravel getter
     * (`getFooBar` for `foo_bar`). Returns null if neither exists.
     */
    private static function readValue(object $subject, string $name): mixed
    {
        if (property_exists($subject, $name)) {
            return $subject->{$name};
        }
        $getter = 'get' . str_replace('_', '', ucwords($name, '_'));
        if (method_exists($subject, $getter)) {
            return $subject->{$getter}();
        }
        return null;
    }

    /**
     * @return object[]
     */
    private static function cartItems(object $cart): array
    {
        $raw = self::readValue($cart, 'items');
        if (!is_iterable($raw)) {
            return [];
        }
        return self::iterableToObjectList($raw);
    }

    /**
     * @param iterable<int|string, mixed> $items
     * @return object[]
     */
    private static function iterableToObjectList(iterable $items): array
    {
        $out = [];
        foreach ($items as $row) {
            if (is_object($row)) {
                $out[] = $row;
            }
        }
        return $out;
    }

    /**
     * Best-effort line-item amount extraction (Bagisto's CartItem typically
     * exposes `total` as a float in the cart's currency). The SDK expects a
     * decimal string with no sign; non-numeric values yield null so the
     * caller bails.
     */
    private static function lineAmount(object $item): ?string
    {
        foreach (['total', 'base_total'] as $candidate) {
            $raw = self::stringProp($item, $candidate);
            if ($raw === '' || !is_numeric($raw)) {
                continue;
            }
            $value = (float) $raw;
            if ($value >= 0.0) {
                return number_format($value, 2, '.', '');
            }
        }
        return null;
    }

    /**
     * Normalize and validate a ZIP-5. Returns null for anything that isn't
     * exactly 5 digits after stripping non-digits.
     */
    private static function extractZip5(string $postcode): ?string
    {
        if ($postcode === '') {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $postcode) ?? '';
        $first5 = substr($digits, 0, 5);
        return preg_match('/^\d{5}$/', $first5) === 1 ? $first5 : null;
    }
}
