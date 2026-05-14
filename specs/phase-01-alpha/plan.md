# Plan — opensalestax-bagisto v0.1.0-alpha.1

> Architecture + file layout for the v0.1.0-alpha.1 ship. Referenced by `tasks.md`.

## Architectural overview

A Laravel package that boots via `extra.laravel.providers` auto-discovery. Its `OpenSalesTaxServiceProvider`:

1. Merges + publishes `config/opensalestax.php`
2. Binds a singleton `OpenSalesTaxClientFactory` to the container
3. Registers `CartTotalsListener` as a listener on `checkout.cart.collect.totals.after`

When the event fires, the listener:

1. Resolves the factory from the container
2. Asks the factory for a `OpenSalesTax\Client` — `null` if not configured / private-net-blocked
3. Yields to Bagisto's built-in tax if the client is null, the currency isn't USD, the country isn't US, or the destination has no resolvable ZIP-5
4. Builds an OST request via `CartPayloadBuilder` (per-line `LineItem[]` + `Address` from the cart's shipping address)
5. Calls the rate cache; on miss, calls `Client::calculate(...)` and stores the result
6. Writes `$cart->tax_total` / `$cart->base_tax_total` to the engine's `tax_total`; on engine error and `fail_hard=false`, logs + leaves Bagisto's tax intact; on `fail_hard=true`, throws

## File layout

```
opensalestax-bagisto/
├── LICENSE                                          # Apache-2.0
├── README.md                                        # install/configure/verify
├── CHANGELOG.md                                     # Keep-a-Changelog
├── CONTRIBUTING.md                                  # DCO + style
├── SECURITY.md                                      # disclosure policy
├── composer.json                                    # ejosterberg/opensalestax-bagisto
├── phpstan.neon                                     # level max
├── phpunit.xml.dist                                 # PHPUnit 10
├── .php-cs-fixer.php                                # PSR-12 + risky
├── .gitattributes                                   # release-archive trimming
├── .gitignore
├── .editorconfig
├── sonar-project.properties
├── .github/
│   └── workflows/ci.yml                             # PHP 8.2 / 8.3 matrix + DCO check
├── config/
│   └── opensalestax.php                             # publishable Laravel config
├── src/
│   ├── Providers/
│   │   └── OpenSalesTaxServiceProvider.php          # the auto-discovered entry point
│   ├── Listeners/
│   │   └── CartTotalsListener.php                   # the event handler
│   ├── Support/
│   │   ├── OpenSalesTaxClientFactory.php            # builds the SDK Client
│   │   ├── UrlValidator.php                         # SSRF defense
│   │   ├── RateCache.php                            # Laravel-cache wrapper
│   │   └── CartPayloadBuilder.php                   # cart → OST request
│   └── Exceptions/
│       ├── OpenSalesTaxBagistoException.php         # base class
│       └── OpenSalesTaxConfigurationException.php   # bad config (fail-hard surfaces)
├── tests/
│   └── Unit/
│       ├── Support/
│       │   ├── UrlValidatorTest.php                 # 11 tests
│       │   ├── RateCacheTest.php                    # 4 tests
│       │   ├── CartPayloadBuilderTest.php           # 6 tests
│       │   └── OpenSalesTaxClientFactoryTest.php    # 4 tests
│       └── Listeners/
│           └── CartTotalsListenerTest.php           # 5+ tests
├── specs/
│   ├── constitution.md
│   ├── current-state.md
│   ├── handoff.md
│   └── phase-01-alpha/
│       ├── spec.md
│       ├── plan.md (this file)
│       └── tasks.md
└── docs/
    ├── SECURITY-REVIEW.md
    └── INTEGRATION-CHECK.md
```

## Module responsibilities

### `Providers\OpenSalesTaxServiceProvider`

- Merge default config so missing keys don't throw
- Publish `config/opensalestax.php` to the consumer app
- Bind `OpenSalesTaxClientFactory` as a singleton
- Register the listener on the Bagisto event in `boot()` (using Laravel's `Event` facade or `$events` dispatcher)

### `Listeners\CartTotalsListener`

- Signature: `public function handle(object $payload): void`
- Bagisto's event dispatches the cart object as `$payload` (event name `checkout.cart.collect.totals.after`)
- Gate cascade: client null → return; currency !== USD → return; country !== US → return; ZIP-5 empty → return
- Build payload; call cache → engine
- On success: `$cart->tax_total = $result->taxTotal; $cart->base_tax_total = $result->taxTotal; $cart->save();`
- On engine exception: if `fail_hard` rethrow; else log warning + leave Bagisto's totals untouched

### `Support\OpenSalesTaxClientFactory`

- Constructor receives config repository
- `make()`: read `base_url`; if empty → return null
- Validate URL via `UrlValidator`; on failure → log + return null
- Build SDK `OpenSalesTax\Client(baseUrl, apiKey, timeout)`; pass a Guzzle client with TLS verify config

### `Support\UrlValidator`

- Constructor takes `bool $allowPrivateNets, callable $hostResolver = null`
- `validate(string $url): void` — throws `InvalidArgumentException` on rejection
- Resolver default uses `gethostbynamel`/`dns_get_record`; tests inject a deterministic mock
- Rules (in order): empty → throw; parse → throw if no scheme/host; scheme must be http|https; resolve host (skip if `allowPrivateNets`); reject if any resolved IP is RFC1918, loopback, link-local, CGNAT, multicast, or invalid

### `Support\RateCache`

- Constructor receives Laravel `CacheRepository` + TTL seconds
- `remember(string $zip5, callable $resolver): CalculateResponse` — keyed by `ost:rate:{zip5}`
- Cache stores a serializable representation (the SDK's `CalculateResponse` is a readonly object — store its raw payload via `json_encode(get_object_vars())` and rebuild on get)
- On cache miss: call resolver, store, return

### `Support\CartPayloadBuilder`

- `extract(object $cart): array{currency: string, country: string, zip5: string, lines: LineItem[]}|null`
- Reads `cart_currency_code`, the shipping address's `country` + `postcode`, and each cart item's `total` + `quantity`
- Returns null when any required field is missing (caller treats null as "yield to Bagisto")
- ZIP-5 extraction: `substr(preg_replace('/[^0-9]/', '', $postcode), 0, 5)` — `preg_match('/^\d{5}$/', ...)` for final acceptance

### `Exceptions\*`

- `OpenSalesTaxBagistoException` — `RuntimeException` subclass; package-scoped marker
- `OpenSalesTaxConfigurationException` — thrown by factory when config is malformed AND `fail_hard=true`

## Security threat model — see `docs/SECURITY-REVIEW.md`

Threats covered (full text in the review doc):

1. SSRF via admin-controlled `base_url` — mitigated by `UrlValidator`
2. Plain-text API key in env — by design (env vars are the Laravel convention; rotation is the merchant's responsibility)
3. TLS-verify off shipped as default — NOT possible; verify-on is the default, opt-out is explicit
4. Engine-response trust — engine is merchant-self-hosted, same trust boundary as the rest of their infra
5. Cache poisoning by ZIP-5 collision — mitigated by ZIP-5 normalization (5-digit-only regex)
6. Information leakage in error log — only structured metadata logged, never customer PII or payload
7. Code injection via cart attributes — all cart-side accessors are typed (cast to string/float)
8. CSRF on settings page — N/A (settings are config-file / env-var, not admin POST)
9. Capability bypass — N/A (no admin endpoints registered by this package)
10. Dependency CVE — `composer audit` clean at ship time; CI re-checks
11. Untrusted JSON in engine response — SDK's typed response objects + `JSON_THROW_ON_ERROR` enforcement
12. DNS rebinding — out of scope for v0.1 (defense would require IP pinning post-resolve, documented as a v0.2 candidate)

## Performance posture

- Engine round-trip dominates checkout time; cache TTL (24h default) keeps the per-cart cost to one cache hit
- Cart with N items → 1 engine call → N tax-line breakdown; we store the cart-level `tax_total` (not per-line; per-line is a v0.2 enhancement)
- Laravel cache backend is whatever the merchant configured (Redis/Memcached/File); we don't require a specific driver

## Deferred to v0.2 (documented but not built)

- Per-line tax breakdown into `cart_items.tax_amount`
- Tax-exempt customer support
- Bagisto Marketplace per-vendor tax allocation
- DNS-rebinding mitigation (IP pin post-resolve)
- Shipping-line tax handling (varies by US state)
- Admin UI configuration page (current path is env / config-file only)
