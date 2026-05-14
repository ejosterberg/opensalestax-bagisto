# Current state — opensalestax-bagisto

> Snapshot. Update whenever a phase ships.

## Last update

2026-05-13 — Phase 01-alpha shipped as `v0.1.0-alpha.1`.

## What's shipped

| Version | Tag | Date | Notes |
|---|---|---|---|
| 0.1.0-alpha.1 | `v0.1.0-alpha.1` | 2026-05-13 | Initial alpha. 32 unit tests, PHPStan level max clean, PHP-CS-Fixer clean, SonarQube quality gate green (0/0/0/0). Awaiting live cart integration test on VM 916 to graduate to `v0.1.0`. |

## What's next

- Live cart integration test on VM 916 (orchestrator agent runs this)
- Graduate `v0.1.0-alpha.1` → `v0.1.0` if the integration test passes
- Submit to Packagist for `composer require ejosterberg/opensalestax-bagisto` discoverability

## Open phases

None — Phase 01-alpha shipped. Phase 02 (v0.2 features) hasn't been spec'd yet.

## Sibling-project map

| Repo | Role |
|---|---|
| `ejosterberg/opensalestax` | The engine (Python / FastAPI) — merchant runs an instance |
| `ejosterberg/opensalestax-php` | The PHP SDK this package depends on |
| `ejosterberg/opensalestax-magento` | Magento 2 module — sibling connector |
| `ejosterberg/opensalestax-woocommerce` | WooCom (WooCommerce) plugin — sibling connector |
| `ejosterberg/open-sales-tax-integrations` | Private orchestrator-hub — owns the cross-connector roadmap |
