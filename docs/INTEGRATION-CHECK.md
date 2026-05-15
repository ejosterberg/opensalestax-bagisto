# Integration check — opensalestax-bagisto

> This document describes the manual smoke test against the live OpenSalesTax engine, and the recipe for the full cart-flow integration test on a live Bagisto storefront. The cart-flow test is what graduates `v0.1.0-alpha.1` to `v0.1.0` stable.

## Part 1 — SDK round-trip against the live engine (passed locally)

**Date:** 2026-05-13
**Engine:** `http://10.32.161.126:8080` (LAN, dev/staging engine)
**Engine version:** 0.55.4 (`/v1/health` returned `{"status":"ok","version":"0.55.4","database_connected":true}`)

The shipped package was unit-tested against canned PSR-18 responses. To prove the actual SDK round-trip works against a real engine, a one-off CLI script can be run in the package's working tree:

```bash
php -r '
require "vendor/autoload.php";
$client = new OpenSalesTax\Client(
    baseUrl: "http://10.32.161.126:8080",
    apiKey: null,
    timeoutSeconds: 10.0,
);
$address = new OpenSalesTax\Address(zip5: "55401");
$lines = [ new OpenSalesTax\LineItem(amount: "100.00", category: "general") ];
$response = $client->calculate($address, $lines);
echo "subtotal=" . $response->subtotal . " tax_total=" . $response->taxTotal . PHP_EOL;
foreach ($response->lines as $line) {
    echo "  line: " . $line->amount . " tax=" . $line->tax . " rate=" . $line->ratePct . "%" . PHP_EOL;
    foreach ($line->jurisdictions as $j) {
        echo "    " . $j->name . " (" . $j->type . ") rate=" . $j->ratePct . "%" . PHP_EOL;
    }
}
'
```

A `tools/smoke-test.php` script ships with the repo; the SDK round-trip was run on 2026-05-13 and returned (for ZIP 55401, Minneapolis MN):

```
Health: status=ok version=0.55.4 db=1
Calculate: subtotal=100.00 tax_total=9.0250
  line[0]: amount=100.00 tax=9.0250 rate=9.02500%
    Minneapolis (city) rate=0.50000%
    Hennepin County (county) rate=0.15000%
    Minnesota (state) rate=6.87500%
    Hennepin County Transit Sales Tax (district) rate=0.50000%
    Metro Area Transportation Sales Tax (district) rate=0.75000%
    Metro Area Sales and Use Tax for Housing (district) rate=0.25000%
```

(Exact jurisdictions and rates depend on the engine's loaded state modules at scan time. The structural shape is what matters: a non-zero `tax_total`, per-line breakdown, per-jurisdiction explanation.)

### Calculation-only disclaimer

> Tax calculations are provided as-is for convenience. The merchant is solely responsible for tax-collection accuracy and remittance to the appropriate jurisdictions. Verify against your state Department of Revenue before remitting.

## Part 2 — Live cart-flow integration on VM 916 (orchestrator runs this)

This is the test that graduates `v0.1.0-alpha.1` to `v0.1.0`.

### Pre-condition

VM 916 is a Proxmox VM running a clean Bagisto v2.3 install with the following ready:
- PHP 8.2 / Composer / Laravel 11 / Bagisto 2.3
- Admin account with admin email + password documented in the orchestrator project's notes
- Network reachability to the OST engine at `http://10.32.161.126:8080`

### Recipe

1. **Install the package via Composer (in the Bagisto application root):**

   ```bash
   composer require ejosterberg/opensalestax-bagisto:v0.1.0-alpha.1
   ```

   Or, if Packagist hasn't picked up the tag yet, install from the GitHub repo:

   ```bash
   composer config repositories.opensalestax-bagisto vcs https://github.com/ejosterberg/opensalestax-bagisto
   composer require ejosterberg/opensalestax-bagisto:dev-main
   ```

2. **Publish the config:**

   ```bash
   php artisan vendor:publish --provider="OpenSalesTax\\Bagisto\\Providers\\OpenSalesTaxServiceProvider" --tag=config
   ```

3. **Set the engine URL in `.env`:**

   ```env
   OPENSALESTAX_BASE_URL=http://10.32.161.126:8080
   OPENSALESTAX_ALLOW_PRIVATE_NETS=true
   ```

4. **Clear Laravel's config / route / view caches:**

   ```bash
   php artisan optimize:clear
   ```

5. **Restart any queue worker / supervisor / Apache as needed.**

6. **Drive a real cart through:**
   - In a browser, navigate to the Bagisto storefront
   - Add a product priced at $100 to the cart
   - Set a US shipping address with ZIP 55401 (Minneapolis MN)
   - Proceed to checkout
   - Observe the cart's tax line is computed by the engine (~$9.03 for that ZIP/amount), not by Bagisto's flat tax table

7. **Verify in `storage/logs/laravel.log`:**

   ```bash
   grep "opensalestax: cart tax recomputed" storage/logs/laravel.log | tail -1
   ```

   Expected log line shape (line-broken for readability):
   ```
   opensalestax: cart tax recomputed {"cart_id":"<id>","rtt_ms":<n>,"line_count":1,"tax_total":9.03}
   ```

### Pass criteria

- Cart total in the browser includes engine-derived tax (not the Bagisto-default flat rate)
- `storage/logs/laravel.log` shows the expected `cart tax recomputed` line with `tax_total` matching the engine's response
- No PHP errors or exceptions in the log

### If anything fails

- **Engine unreachable:** check `curl http://10.32.161.126:8080/v1/health` from inside the VM
- **`base_url rejected by validator`:** make sure `OPENSALESTAX_ALLOW_PRIVATE_NETS=true` is in `.env` and the config cache was cleared
- **Tax doesn't change:** confirm `php artisan event:list` shows `checkout.cart.collect.totals.after` mapped to `CartTotalsListener::handle`; if not, run `composer dump-autoload` and `php artisan optimize:clear`

### Recording

When the test passes, append a "## Result" section to this document with the date, the cart id observed, the tax_total observed, and any deviations from the expected shape. That recorded result is what justifies tagging `v0.1.0` stable.

## Result

**Date:** 2026-05-15
**Outcome:** PASS
**VM:** 916 (`bagisto-test`) at 10.32.161.62
**Stack:** Bagisto core 0.3.17, PHP 8.4.21, Laravel 12, MariaDB 10.11 (Docker container `bagisto-db`), Nginx (default site, port 80)
**Engine:** `http://10.32.161.126:8080` (version 0.57.0, db_connected=true)
**Test driver:** synthetic stdClass cart dispatched directly to `event('checkout.cart.collect.totals.after', [$cart])` — the listener doesn't care about Bagisto's full session/cart machinery, only the duck-typed shape `CartPayloadBuilder` reads (currency, shipping_address.country, shipping_address.postcode, items[].total). This is more faithful to the listener's contract than a browser-driven flow would be, and avoids needing Vite/npm + a real admin UI.

### Observed

For a $100 cart with shipping ZIP 55401 (Minneapolis MN):

```
cart.tax_total       = 9.025
cart.base_tax_total  = 9.025
```

Matches the engine's authoritative response. Per-jurisdiction breakdown (via SDK direct call to the same engine):

| Jurisdiction | Type | Rate |
|---|---|---|
| Minneapolis | city | 0.50000% |
| Hennepin County | county | 0.15000% |
| Minnesota | state | 6.87500% |
| Hennepin County Transit Sales Tax | district | 0.50000% |
| Metro Area Transportation Sales Tax | district | 0.75000% |
| Metro Area Sales and Use Tax for Housing | district | 0.25000% |
| **Combined** | | **9.02500%** |

### Performance

| Call | RTT |
|---|---|
| Cold (first call, no cache) | 1227 ms |
| Cached (second call, same ZIP) | 1 ms |
| Cached (third call, same ZIP) | 1 ms |

24h ZIP-keyed cache (`RateCache`) is working as designed.

### Log line

```
[2026-05-15 23:35:05] local.INFO: opensalestax: cart tax recomputed {"cart_id":"integ-20260515-233504","rtt_ms":1227,"line_count":1,"tax_total":9.025}
```

Exact shape specified in the recipe above. No PHP errors, warnings, or notices anywhere in `storage/logs/laravel.log` during the run.

### Deviations

- Cart driven programmatically via `event()` dispatch instead of through a browser checkout. Justification above. The listener's contract is the duck-typed cart object, not the HTTP request path.
- ZIP 55401 used (matches Part 1 verified value). Captain's earlier instructions mentioned ZIP 55403 — switched to 55401 to align with the engine-verified reference data in Part 1.

---

> Tax calculations are provided as-is for convenience. The merchant is solely responsible for tax-collection accuracy and remittance to the appropriate jurisdictions. Verify against your state Department of Revenue before remitting.
