# Current state — opensalestax-bagisto

> Snapshot. Update whenever a phase ships.

## Last update

2026-05-15 — Phase 01-alpha graduated to `v0.1.0` stable on the strength of the VM 916 live cart-flow integration test.

## What's shipped

| Version | Tag | Date | Notes |
|---|---|---|---|
| 0.1.0-alpha.1 | `v0.1.0-alpha.1` | 2026-05-13 | Initial alpha. 32 unit tests, PHPStan level max clean, PHP-CS-Fixer clean, SonarQube quality gate green (0/0/0/0). |
| 0.1.0 | `v0.1.0` | 2026-05-15 | Stable. Same code as the alpha (tags point at the same commit `0263381`). Graduated after live cart-flow integration test on Proxmox VM 916 (`bagisto-test`, 10.32.161.62) wrote `tax_total = 9.025` for a $100 / ZIP 55401 cart via the `checkout.cart.collect.totals.after` listener against engine v0.57.0. See `docs/INTEGRATION-CHECK.md` "## Result" for the full evidence. |

## What's next

- Submit to Packagist for `composer require ejosterberg/opensalestax-bagisto` discoverability (currently installed via the VCS-repo workaround)
- Phase 02 candidates per `specs/handoff.md` (per-line tax breakdown, tax-exempt customers, marketplace per-vendor allocation, admin UI, etc.) — not yet spec'd

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
