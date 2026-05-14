# Constitution — opensalestax-bagisto

> Non-negotiable principles for this connector. Mirrors and scopes the org-wide constitution at <https://github.com/ejosterberg/open-sales-tax-integrations> §1-§15. Revise via PR with `[constitution]` in the title.

## §1. Mission

Ship a free, self-hostable [Bagisto](https://bagisto.com/) package that swaps Bagisto's flat tax-table calculation for the [OpenSalesTax engine](https://github.com/ejosterberg/opensalestax) on US-destination, USD checkouts. Bagisto is popular in India / South Asia and has an underserved US-tax integration story — currently flat rates only.

## §2. License — Apache 2.0

The Bagisto core is MIT-licensed; we keep the same Apache-2.0 license as the OST engine + the rest of the connector portfolio. DCO sign-off + SPDX header on every source file.

## §3. Stack

- **PHP 8.2+**
- **Laravel 11.x / 12.x** (the Bagisto v2 baseline)
- **Bagisto 2.x** (Bagisto v2.3 was the latest stable when this connector shipped)
- Composer / Packagist for distribution; package name `ejosterberg/opensalestax-bagisto`

## §4. The dependency arrow

```
opensalestax-bagisto  →  ejosterberg/opensalestax (PHP SDK, ^0.1)  →  OST engine /v1 HTTP API
```

This package does not import the engine's Python internals, does not reach around the SDK, and does not bundle the engine. The merchant runs an OST engine and points this package at it.

## §5. Calculation only

Never file. Never auto-remit. The merchant remits — the constitution-mandated disclaimer appears in the README, in `config/opensalestax.php` (as a comment), and wherever a customer or admin sees the tax line.

## §6. USD-only / US-only

If the cart currency isn't USD or the shipping country isn't US, the listener yields silently to Bagisto's built-in tax tables. No engine call, no error. This is the engine's documented scope (engine constitution §5).

## §7. Fail-soft default

Engine errors (timeout, 5xx, malformed body) by default fall through to Bagisto's built-in tax + a structured log line. The `OPENSALESTAX_FAIL_HARD=true` opt-in surfaces engine failures to checkout (rethrows so the cart can't complete with stale or absent tax).

## §8. SSRF defense

The base URL is admin-controlled (env var or `config/opensalestax.php`), so without defense an attacker who controls the env / config can target internal services (AWS metadata, Redis, intranet). The `UrlValidator` rejects:

- Non-http(s) schemes
- RFC1918 hosts (10/8, 172.16/12, 192.168/16)
- Loopback (127/8, ::1)
- Link-local (169.254/16, fe80::/10) — including 169.254.169.254 (cloud metadata)
- CGNAT (100.64/10, RFC 6598)
- Multicast (224/4)
- Unresolvable hosts

Opt-out (`OPENSALESTAX_ALLOW_PRIVATE_NETS=true`) is required for the **dominant** Bagisto self-hosting pattern (OST and Bagisto on the same LAN VPC). Default-strict, explicit opt-in.

## §9. TLS-verify on by default

`OPENSALESTAX_TLS_VERIFY=true` is the default. Opt-out exists for self-signed-cert merchants but must be opt-in.

## §10. No secrets in code or logs

The API key flows via env var only (`OPENSALESTAX_API_KEY`). It is never persisted to the database, never logged, never echoed in error responses. Cart-level logging captures structured metadata only: cart id, line count, HTTP status, RTT in milliseconds.

## §11. Disclaimer language

Every place the merchant sees this package — README, config file comments, integration check docs — carries:

> Tax calculations are provided as-is for convenience. The merchant is solely responsible for tax-collection accuracy and remittance to the appropriate jurisdictions. Verify against your state Department of Revenue before remitting.

## §12. DCO sign-off mandatory

Every commit `git commit -s`. CI enforces. No AI co-author trailers.

## §13. Spec-driven development

Each non-trivial change ships with `spec.md` / `plan.md` / `tasks.md` in `specs/`. The constitution is immutable except via `[constitution]` PRs. `current-state.md` and `handoff.md` track the latest cut.

## §14. What's explicitly OUT of v0.1 scope

- Filing or remittance (NEVER — engine constitution §13)
- Non-USD currencies (NEVER — engine constitution §5)
- Bagisto Marketplace (multi-vendor) tax allocation per vendor — v0.2 candidate
- Bagisto multi-channel tax variation — v0.2 candidate
- Customer-group / tax-exempt customer flows — v0.2 candidate
- Subscription / recurring-billing tax recalculation — v0.2 candidate (depends on whether Bagisto's subscription module is widely used)
- Refund / return tax integration — v0.2 candidate

## §15. Promotion criteria (alpha → stable)

`v0.1.0-alpha.N` graduates to `v0.1.0` when the live cart integration test on a clean Bagisto v2.3 install passes — the orchestrator hub runs that test on VM 916.
