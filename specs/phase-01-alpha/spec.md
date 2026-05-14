# Spec — opensalestax-bagisto v0.1.0-alpha.1

> **Status:** Shipped 2026-05-13.
> **Public repo target:** `ejosterberg/opensalestax-bagisto`

## Goal

Ship a Bagisto v2.x package that calculates US sales tax via OpenSalesTax for Bagisto storefronts. Installable via Composer (`composer require ejosterberg/opensalestax-bagisto`), auto-discoverable by Laravel, passes a 30+ unit test suite, and runs SonarQube-clean.

## User story

A Bagisto shop admin: `composer require ejosterberg/opensalestax-bagisto`, publishes the config (`php artisan vendor:publish --provider="OpenSalesTax\Bagisto\Providers\OpenSalesTaxServiceProvider" --tag=config`), sets `OPENSALESTAX_BASE_URL` in `.env`, restarts the queue worker, and tax now calculates correctly at cart and checkout for any US shipping address with a recognized ZIP-5.

## In scope (v0.1 alpha)

- Bagisto package installable via Composer (`composer require ejosterberg/opensalestax-bagisto`)
- Service provider that auto-registers via Laravel package discovery
- Listener on `checkout.cart.collect.totals.after` event — Bagisto's standard post-totals hook
- Publishable Laravel config with all 7 settings env-var-backed
- ZIP-5 cache via Laravel's cache layer (24h TTL default, configurable)
- SSRF-defense URL validator (RFC1918 / loopback / link-local / CGNAT / multicast all rejected; explicit `OPENSALESTAX_ALLOW_PRIVATE_NETS=true` opt-in)
- USD-only / US-only / ZIP-required gates
- Fail-soft default; fail-hard opt-in
- 30+ unit tests via PHPUnit
- PHPStan level max, PHP-CS-Fixer (PSR-12 + risky) clean, SonarQube quality gate green
- README with calculation-only disclaimer above the fold
- `docs/SECURITY-REVIEW.md` with the v0.1 threat model
- `docs/INTEGRATION-CHECK.md` with the manual cart smoke-test recipe
- CI on GitHub Actions for PHP 8.2 + 8.3; DCO sign-off check on PRs

## Out of scope (defer to v0.2)

- Live Bagisto storefront integration test (handled by the orchestrator project on VM 916)
- Bagisto Marketplace (multi-vendor) tax allocation
- Bagisto multi-channel tax variations
- Customer-group / tax-exempt customer flows
- Recurring-billing / subscription tax recalc
- Refund / return tax integration
- Shipping-line tax handling (most US states tax shipping; engine support varies by state)

## Out of scope (NEVER)

- Tax filing / remittance (engine constitution §13)
- Non-USD currencies (engine constitution §5)
- Address validation
- Reverse-engineering of Avalara / TaxJar / Stripe Tax integration code

## Resolved open questions

| Q | Answer | Rationale |
|---|---|---|
| Bagisto version target | v2.x only | Bagisto has fully transitioned to v2; v1 is deprecated upstream. v2.0+ is what new merchants install. |
| Extension point | Event listener on `checkout.cart.collect.totals.after` | Non-invasive (no repository binding override, no plugin chain to manage). Mirrors the Bagisto-recommended pattern for tax-provider packages. The downside — we don't intercept rate lookup at the line item, we recompute the cart total — is acceptable for v0.1 because the engine returns per-line breakdown anyway. |
| Tax-model touchpoint | Write to `Cart::tax_total` and `Cart::base_tax_total` columns inside the listener | The post-collect hook is the canonical place to overwrite Bagisto's built-in tax. |
| PHP version | 8.2 + 8.3 | Matches the SDK (`^8.2`) and modern Laravel 11/12 baseline. |
| Composer + Laravel range | `^11.0|^12.0` for Laravel facets, no hard Bagisto Composer dep | Avoid pulling `bagisto/bagisto` into requires (forces full Bagisto application install in CI). The listener subject is duck-typed via property/method-exists guards. |
| Test framework | PHPUnit 10 | Bagisto's own test suite is PHPUnit-based. Pest is optional; PHPUnit keeps parity with Magento / Woo connectors. |
| Marketplace multi-vendor | Deferred to v0.2 | v0.1 calculates against single ship-to address. Marketplace tax-per-vendor is non-trivial. |
| Currency gate | Hard gate — `cart_currency_code !== 'USD'` yields silently to Bagisto | Engine constitution §5. |

## Success criteria for v0.1.0-alpha.1

1. `composer install` works in a fresh checkout
2. PHPUnit suite passes with 30+ tests
3. PHPStan level max clean
4. PHP-CS-Fixer (PSR-12 + risky) clean
5. SonarQube quality gate green: 0 bugs / 0 vulnerabilities / 0 code smells / 0 security hotspots
6. `composer audit` reports no advisories at HIGH or above
7. Tagged `v0.1.0-alpha.1` and pushed to `ejosterberg/opensalestax-bagisto`
8. GitHub Release exists for the tag with notes pulled from CHANGELOG
9. Repo is public from day one

## Success criteria for v0.1.0 stable (orchestrator project)

10. Manual cart integration on VM 916 (clean Bagisto v2.3 install) — admin sets `OPENSALESTAX_BASE_URL`, adds an item to cart, sets a Minneapolis ZIP shipping address, and observes engine-driven tax in the cart total. Recorded in `docs/INTEGRATION-CHECK.md` post-test.
