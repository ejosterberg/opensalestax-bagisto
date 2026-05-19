# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.1] - 2026-05-19

### Changed

- **CP-8 Phase 5D: bumped `ejosterberg/opensalestax` constraint to `^0.2.0`.**
  Picks up the new `OpenSalesTax\Client::capabilities()` /
  `OpenSalesTax\Client::capabilitiesCached()` helpers for engine v0.59.0's
  `/v1/capabilities` endpoint. No merchant-visible behavior change in
  this release — the helper is available to package code but not yet
  wired into any feature path. Constraint bump only; Test Connection
  surface enrichment deferred to v-next.

## [0.2.0] - 2026-05-19

### Added

- **Per-state nexus filter (CP-3).** New `nexus_states` config option
  accepts a comma-separated list of US 2-letter state codes
  (e.g. `MN,WI,IA`) — also settable via the `OPENSALESTAX_NEXUS_STATES`
  env var. When set and non-empty, the cart-totals listener
  short-circuits the engine call for any cart shipping to a state not
  in the list. Bagisto's built-in tax tables (typically: no tax) take
  over for those carts. Unset / empty preserves v0.1 behavior (engine
  called for every US/USD cart). Missing / unresolvable destination
  state with the filter active is fail-closed (also short-circuit) —
  the safer default for a merchant who explicitly opted in.

  Address parsing: Bagisto's address typically exposes `state` as a
  2-letter US code; we also accept full names via `state_name` and
  normalize the 50-state list to 2-letter codes at the read site.

  Brings this connector in line with WooCommerce v0.5, Vendure v1.2,
  and Odoo v0.3, which already shipped this filter. Major win for
  merchants with limited nexus footprints — typical merchant only has
  1–3 nexus states and was previously paying engine RTT on every cart.

## [0.1.2] - 2026-05-19

### Added

- **Test Connection admin page (CP-4).** New admin route at
  `/admin/opensalestax/test-connection` (gated by Bagisto's `admin`
  auth middleware) that hits the configured engine's `/v1/health`
  endpoint and displays the response inline ("✓ Engine v0.59.0 is ok
  — database connected" on success, "✗ Engine base URL is not set"
  or "✗ HTTP 500" on failure). Surfaces typo'd engine URLs +
  unreachable engines at config time rather than at first checkout.
  Brings this connector in line with WooCom v0.5, Vendure v1.3, and
  Saleor v1.0 which already shipped this. Wired via:
  - `Support\EngineConnectionTester` — pure service object (testable
    in isolation; wraps the existing client factory + SDK `health()`
    call; never throws).
  - `Http\Controllers\Admin\TestConnectionController` — thin
    controller that delegates to the service and returns either a
    blade page or JSON.
  - `Http\admin-routes.php` — registered automatically by
    `OpenSalesTaxServiceProvider::boot()`.
  - `resources/views/admin/test-connection.blade.php` — self-contained
    single-page blade (no theme dependency, works on every Bagisto
    admin theme).
  - 5 unit tests exercising the service across null-client,
    happy-path, db-disconnected, HTTP-failure, and transport-error
    shapes.

## [0.1.1] - 2026-05-17

### Changed

- **Dual-licensed Apache-2.0 OR GPL-2.0-or-later.** Adds GPL-2.0-or-later as
  an alternative license alongside the existing Apache-2.0 grant, enabling
  downstream redistribution in GPL-only ecosystems (WordPress.org plugin
  directory, OCA AGPL-track repositories) without giving up Apache
  compatibility. License files reorganized: `LICENSE-APACHE.txt` (existing
  Apache text, moved from `LICENSE`), `LICENSE-GPL.txt` (new, GNU GPL v2
  text), `LICENSE` (new dual-declaration). SPDX headers updated across
  source files. `composer.json` `license` field switched from string to
  array form.

### Added

- **`.github/dependabot.yml`** — weekly checks for composer + GitHub Actions
  dependencies, with grouped dev-dep PRs. Brings this repo in line with
  the rest of the OpenSalesTax connector portfolio's supply-chain hygiene
  standard.

## [0.1.0] - 2026-05-15

### Changed

- Graduated `v0.1.0-alpha.1` to `v0.1.0` stable on the strength of the live cart-flow integration test on Proxmox VM 916 (`bagisto-test`, 10.32.161.62). Same code surface as the alpha — no diff between the two tags.

### Verified

- Integration test recipe in `docs/INTEGRATION-CHECK.md` Part 2 executed end-to-end against a clean Bagisto v2.3 install (Bagisto core 0.3.17, PHP 8.4.21, Laravel 12, MariaDB 10.11 in Docker).
- Engine: `http://10.32.161.126:8080` reporting version 0.57.0.
- Cart-totals event dispatch with synthetic US/MN/ZIP 55401 $100 cart wrote `tax_total = 9.025` (`base_tax_total = 9.025`) onto the cart object — matches the engine's authoritative response.
- Per-jurisdiction breakdown for ZIP 55401 (verified via SDK): Minneapolis 0.5%, Hennepin County 0.15%, Minnesota 6.875%, Hennepin County Transit 0.5%, Metro Area Transportation 0.75%, Metro Area Sales and Use Tax for Housing 0.25% — combined 9.025%.
- Engine round-trip 1227 ms cold; cached calls 1 ms (24h ZIP-keyed cache hit).
- Diagnostic log line `opensalestax: cart tax recomputed` observed at the expected shape with `cart_id`, `rtt_ms`, `line_count`, and `tax_total` fields.

## [0.1.0-alpha.1] - 2026-05-13

### Added

- Initial alpha release of the OpenSalesTax package for Bagisto v2.x on Laravel 11/12 and PHP 8.2+.
- `Providers\OpenSalesTaxServiceProvider` — auto-discovered Laravel service provider that registers the config + cache + URL validator + cart-totals listener.
- `Listeners\CartTotalsListener` — listens on Bagisto's `checkout.cart.collect.totals.after` event, builds an OST engine payload from the cart line items, and writes the resulting tax onto the cart's `tax_total` / `base_tax_total` columns.
- `Support\OpenSalesTaxClientFactory` — builds the SDK `OpenSalesTax\Client` from validated config; returns `null` if `base_url` is empty or fails URL validation (the listener treats that as "engine not configured" and yields to Bagisto's built-in tax).
- `Support\UrlValidator` — SSRF-defense URL validator that rejects RFC1918 / loopback / link-local / CGNAT / multicast hosts unless the merchant explicitly opts in via `OPENSALESTAX_ALLOW_PRIVATE_NETS=true` or `config('opensalestax.allow_private_nets', true)`.
- `Support\RateCache` — Laravel-cache-backed wrapper that memoizes engine responses by ZIP-5 for the configured TTL (default 24h).
- `Support\CartPayloadBuilder` — extracts gate inputs (currency, country, ZIP-5) and per-line OST `LineItem` array from a Bagisto cart object using duck-typed property access (no Bagisto Composer dep needed).
- USD-only / US-only / ZIP-required gates: any failure yields silently to Bagisto's built-in tax calc.
- Fail-soft default: engine errors fall back to Bagisto's built-in tax + log a warning. Fail-hard opt-in via `OPENSALESTAX_FAIL_HARD=true` (rethrows the engine exception so checkout surfaces it).
- `config/opensalestax.php` publishable Laravel config with all 7 settings env-var-backed.
- 30 unit tests covering: gates (currency/country/ZIP-5), cache hit/miss, URL validator (loopback / all RFC1918 ranges / link-local / CGNAT / multicast / public / opt-in / scheme allowlist), client factory (configured / empty / private-net-rejected), payload builder (single line / multiple lines / non-USD reject / non-US reject / no-ZIP reject), and the listener happy path / fail-soft / fail-hard branches.
- Continuous integration on PHP 8.2 / 8.3 via GitHub Actions: PHPUnit + PHPStan (level max) + PHP-CS-Fixer + `composer audit`.
- SonarQube quality gate green: 0 bugs / 0 vulnerabilities / 0 code smells / 0 security hotspots (security rating A, reliability rating A, maintainability rating A).

### Security

- TLS verification on by default for engine HTTP calls (opt-out via `OPENSALESTAX_TLS_VERIFY=false` only).
- SSRF defense built into the URL validator (see `Support\UrlValidator` + 11 dedicated unit tests).
- API key stored as env var, never logged; full request payloads never logged (only structured metadata: cart id, line count, HTTP status, RTT).
- See `docs/SECURITY-REVIEW.md` for the v0.1 threat model and mitigation status (12 threats reviewed, 0 open critical/high/medium).

### Notes

- Live storefront integration on Bagisto v2.x has not yet been performed in this repo; the v0.1.0-alpha.1 tag exists for the cart integration test the orchestrator project will run on VM 916. Graduation alpha → v0.1.0 stable happens after that test passes.

[Unreleased]: https://github.com/ejosterberg/opensalestax-bagisto/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/ejosterberg/opensalestax-bagisto/releases/tag/v0.1.0
[0.1.0-alpha.1]: https://github.com/ejosterberg/opensalestax-bagisto/releases/tag/v0.1.0-alpha.1
