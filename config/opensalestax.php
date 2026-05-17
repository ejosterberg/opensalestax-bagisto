<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

/*
 * OpenSalesTax for Bagisto â€” configuration.
 *
 * IMPORTANT â€” Calculation-only disclaimer:
 *
 *   Tax calculations are provided as-is for convenience. The merchant is
 *   solely responsible for tax-collection accuracy and remittance to the
 *   appropriate jurisdictions. Verify against your state Department of
 *   Revenue before remitting.
 *
 * The package is inert until `base_url` is set. With base_url empty,
 * Bagisto's built-in tax tables handle every cart.
 */

return [
    /*
     * Base URL of the merchant's OpenSalesTax engine (e.g. https://ost.example.com).
     * No trailing slash required â€” the SDK trims it.
     *
     * env:    OPENSALESTAX_BASE_URL
     * type:   string|null
     */
    'base_url' => env('OPENSALESTAX_BASE_URL'),

    /*
     * Optional bearer-token API key for the OST engine. Empty/null skips
     * authentication. Use env-var storage; never commit a real key to VCS.
     *
     * env:    OPENSALESTAX_API_KEY
     * type:   string|null
     */
    'api_key' => env('OPENSALESTAX_API_KEY'),

    /*
     * HTTP timeout for engine requests, in seconds.
     *
     * env:    OPENSALESTAX_TIMEOUT
     * type:   int
     * range:  1..120 (sub-second TTL not supported)
     */
    'timeout' => (int) env('OPENSALESTAX_TIMEOUT', 10),

    /*
     * Cache TTL for engine responses, in seconds. Keyed by destination ZIP-5
     * so a busy storefront makes one engine call per ZIP per TTL window.
     *
     * env:    OPENSALESTAX_CACHE_TTL
     * type:   int
     * default: 86400 (24 hours)
     */
    'cache_ttl' => (int) env('OPENSALESTAX_CACHE_TTL', 86400),

    /*
     * Fail-hard policy. When false (default), engine errors fall back silently
     * to Bagisto's built-in tax + log a warning. When true, engine errors are
     * rethrown so the checkout fails fast (use this only if you can't tolerate
     * stale tax during an outage).
     *
     * env:    OPENSALESTAX_FAIL_HARD
     * type:   bool
     */
    'fail_hard' => (bool) env('OPENSALESTAX_FAIL_HARD', false),

    /*
     * Allow `base_url` to resolve to private (RFC1918 / loopback / link-local
     * / CGNAT / multicast) hosts. Required when self-hosting OST on the same
     * LAN/VPC as Bagisto. Default-off raises the bar against SSRF if the
     * env / config file is ever compromised.
     *
     * env:    OPENSALESTAX_ALLOW_PRIVATE_NETS
     * type:   bool
     */
    'allow_private_nets' => (bool) env('OPENSALESTAX_ALLOW_PRIVATE_NETS', false),

    /*
     * TLS certificate verification for engine HTTPS requests. Leave at true
     * in production. Opt-out exists only for self-signed-cert merchants who
     * understand the trust implications.
     *
     * env:    OPENSALESTAX_TLS_VERIFY
     * type:   bool
     */
    'tls_verify' => (bool) env('OPENSALESTAX_TLS_VERIFY', true),
];
