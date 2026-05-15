# Handoff — opensalestax-bagisto

> What the next session should pick up.

## State at last commit

`v0.1.0` shipped 2026-05-15. Stable tag pushed; CHANGELOG, `specs/current-state.md`, and `docs/INTEGRATION-CHECK.md` "## Result" all updated with the VM-916 verification evidence (see those files for engine version, RTTs, jurisdiction breakdown, and observed log line).

`v0.1.0-alpha.1` and `v0.1.0` point at the same commit — the alpha→stable graduation was a documentation event, not a code event.

## Immediate next step

- Submit to Packagist for public `composer require ejosterberg/opensalestax-bagisto` discoverability (currently merchants install via the VCS-repo workaround documented in `docs/INTEGRATION-CHECK.md`).
- Phase 02 spec — pick from the deferred list below.

## Open decisions

None blocking. v0.2 candidates (per `specs/phase-01-alpha/plan.md` "Deferred to v0.2"):

1. Per-line tax breakdown into `cart_items.tax_amount`
2. Tax-exempt customer flow
3. Bagisto Marketplace per-vendor tax allocation
4. DNS-rebinding mitigation (IP pin post-resolve)
5. Admin UI configuration page
6. Shipping-line tax handling

## Files to read first when picking up this repo

1. `specs/constitution.md` — invariants
2. `specs/current-state.md` — what's shipped
3. `specs/handoff.md` — this file
4. `specs/phase-01-alpha/spec.md` — the v0.1 spec (frozen post-ship)
5. `docs/SECURITY-REVIEW.md` — latest threat-model snapshot

## Known caveats / red flags

- The `CartTotalsListener` types its Bagisto cart subject as `object` (duck-typed). We don't pull `bagisto/bagisto` into `composer require` because the full Bagisto application stack would be needed for CI. PHPStan ignores are scoped to that listener and documented inline.
- The cache stores a serialized payload, not the SDK's `CalculateResponse` object, because the SDK uses `readonly` properties that PHP's native serialization handles fine but tests want to control. `RateCache` rebuilds the response from the cached payload on hit.
- v0.1 does not write per-line tax onto `cart_items` rows — only the cart-level `tax_total` / `base_tax_total`. That's enough for the cart total to be correct, but order detail screens may not show per-jurisdiction breakdown. v0.2 candidate.
