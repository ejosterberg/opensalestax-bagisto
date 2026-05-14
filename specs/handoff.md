# Handoff — opensalestax-bagisto

> What the next session should pick up.

## State at last commit

`v0.1.0-alpha.1` shipped 2026-05-13. Public repo live; tag pushed; GitHub release created (prerelease).

## Immediate next step (NOT this connector's session)

The orchestrator project runs the live cart integration test on VM 916 (a pre-provisioned clean Bagisto v2.3 install). The test recipe is in `docs/INTEGRATION-CHECK.md`. When it passes, the orchestrator tags `v0.1.0` (graduates the alpha).

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
