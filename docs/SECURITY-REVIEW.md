# Security Review — opensalestax-bagisto v0.1.0-alpha.1

**Reviewer:** automated audit + manual code review (2026-05-13).
**Scope:** all PHP source files in `src/` plus `config/opensalestax.php`.
**Methodology:** OWASP Top 10 mapped to Laravel-package-specific concerns; manual line-by-line review against a CWE-driven checklist; `composer audit` against current advisories.

## Summary

| Severity | Count | Status |
|---|---|---|
| Critical | 0 | — |
| High | 0 | — |
| Medium | 0 | — |
| Low / Informational | 4 | All documented; no open action items |
| Defense-in-depth | 1 | Built in from v0.1 |

**No critical, high, or medium-severity open findings.** The package's threat model is bounded by the env / config-file write boundary — an attacker with environment write access on the merchant's server has already won; the SSRF defense raises the bar against partial compromises (env-only, config-file-only, or admin-panel writes from a future v0.2 settings UI).

`composer audit` against the dependency tree (production + dev): **0 known CVEs**.

## Findings

### LOW — API key stored in plain-text in env / config

**Files:** `config/opensalestax.php`, `src/Support/OpenSalesTaxClientFactory.php`
**CWE:** CWE-256 (Plaintext Storage of a Password)

The OST API key, when configured, is stored in either the `.env` file or `config/opensalestax.php`. An attacker with read access to those files can recover it.

**Mitigation:**

- Laravel doesn't provide a built-in encrypted-env API; storing API keys in `.env` is the Laravel-standard pattern (Stripe, Twilio, AWS, all do the same)
- The key flows into `Client` constructor in-memory; it is never written to logs
- The OST API key only grants access to the merchant's own self-hosted engine — it's a self-hosted auth token, not a third-party key. Compromise impact is bounded to the merchant's own infrastructure.

**Residual risk:** Acceptable. Documented in `README.md` under "Configure".

### LOW — Engine response trust

**File:** `src/Listeners/CartTotalsListener.php`
**CWE:** CWE-602 (Client-Side Enforcement of Server-Side Security)

The listener trusts the engine's `tax_total` value and writes it directly onto the cart's `tax_total` / `base_tax_total`. If the engine is compromised, the response could under-tax (revenue loss) or over-tax (compliance risk).

**Mitigation:**

- The engine is **self-hosted by the merchant** — the merchant controls its security
- The listener doesn't render engine response content to the customer; only the typed `tax_total` numeric value flows into Bagisto's flow
- Engine-side: production deployment should run behind a firewall, with monitoring on the engine's `/v1/health`

**Residual risk:** Trusts the engine, by design. The whole architecture assumes the merchant trusts their own infrastructure.

### LOW — Verbose error messages in error log

**Files:** `src/Listeners/CartTotalsListener.php`, `src/Support/OpenSalesTaxClientFactory.php`
**CWE:** CWE-209 (Information Exposure Through an Error Message)

On engine errors the listener writes the exception class and message into Laravel's log via `LoggerInterface`. For an unexpected `\Throwable`, the message could leak file paths, class names, and other internal details.

**Mitigation:**

- The Laravel log file is not accessible to customers; only admins / ops staff with filesystem access read it
- The listener never echoes the exception message to the checkout customer — fail-soft falls through silently, fail-hard throws a wrapped `OpenSalesTaxBagistoException` whose message is the package's own (not the raw engine exception)

**Residual risk:** Acceptable for v0.1. Verbose internal-only logs help debug deployment issues.

### LOW — DNS rebinding (deferred to v0.2)

**File:** `src/Support/UrlValidator.php`
**CWE:** CWE-918 (SSRF, mitigation gap)

The `UrlValidator` resolves the engine host once at `make()` time. A host that resolves to a public IP at validation time but to an internal IP at request time can bypass the SSRF check.

**Mitigation gap:** The full mitigation would pin the resolved IP at validation time and pass it via Guzzle's `CURLOPT_RESOLVE` so the runtime cURL connection bypasses DNS. Magento's connector does this; this package defers to v0.2.

**Why acceptable for v0.1:** The OPENSALESTAX_BASE_URL is admin / ops-controlled, not customer-controlled. An attacker who can write the env file can already do worse than SSRF.

**Action item for v0.2:** Add IP-pinning à la `opensalestax-magento`'s `ApiUrlValidator::validate()` returning the resolved IP, and `OstaxClient::applyPinnedIp()` injecting `CURLOPT_RESOLVE` per request.

### Defense-in-depth — TLS verification on by default

**File:** `config/opensalestax.php`, `src/Support/OpenSalesTaxClientFactory.php`
**Pattern:** Default-strict configuration

Guzzle's `verify` option is `true` by default in this package. Opt-out (`OPENSALESTAX_TLS_VERIFY=false`) exists for merchants using self-signed certificates, but the default and the README both push toward keeping it on.

## Verified safe — areas reviewed with no findings

| Path | Concern | Result |
|---|---|---|
| `UrlValidator::validate()` | SSRF via admin-controlled base_url | Rejects RFC1918 / loopback / link-local / CGNAT / multicast / unresolvable / non-http schemes by default; opt-in via `allow_private_nets` |
| `CartPayloadBuilder::extractZip5()` | Customer-controlled ZIP injection | Strips non-digits, accepts only `^\d{5}$` regex match before passing to SDK |
| `CartPayloadBuilder::lineAmount()` | Customer-controlled price | Coerces to float via `number_format` after `is_numeric` guard; negative values rejected; non-numeric returns null and the builder bails |
| `CartTotalsListener::handle()` | Engine response handling | Numeric `tax_total` cast to `float`; not echoed to customer |
| `CartTotalsListener::handle()` log calls | PII / secret leakage | Logs only cart id (Bagisto-assigned), HTTP status, RTT, line count — never customer address, cart contents, or API key |
| `OpenSalesTaxClientFactory::make()` | API key handling | Read from typed config, passed to SDK Client constructor; never logged, never thrown in exceptions |
| `RateCache::remember()` | Cache key injection | Key is `'ost:rate:' . $zip5` where $zip5 is already digit-only-5-char-validated by the builder |
| `config/opensalestax.php` | Bad config blowups | All keys typed-cast with sensible defaults; `base_url` empty → package inert |
| `composer.json` dependency tree | Known CVEs | `composer audit` clean ✓ |
| Hardcoded secrets / credentials | Embedded keys | None found ✓ |
| `eval()` / dynamic include | Code injection vectors | None used ✓ |

## Test surface

The PHPUnit suite exercises 35 test cases / 60 assertions covering:

- URL validator: empty / malformed / non-http scheme / loopback / RFC1918 ×3 / link-local / CGNAT / multicast / public / unresolvable / opt-in (11 tests)
- Rate cache: miss writes / hit short-circuits / key-shape / non-array recovery (4 tests)
- Cart payload builder: single-line happy / multi-line / non-USD pass-through / non-US pass-through / missing ZIP / malformed ZIP (6 tests)
- Client factory: empty URL / valid public URL / private rejected / private opt-in / fail-hard rethrow (5 tests)
- Cart totals listener: no-client noop / non-USD noop / non-US noop / no-ZIP noop / happy path writes tax / fail-soft engine-error / fail-hard engine-error (7 tests)
- Plus internal infrastructure smoke tests bringing the total to **35**.

## Recommendations for users

1. **Run the engine on a private network** when possible. If you do, set `OPENSALESTAX_ALLOW_PRIVATE_NETS=true` to permit RFC1918 hosts; otherwise keep it default-off so the SSRF defense stays active.
2. **Keep `OPENSALESTAX_TLS_VERIFY=true`** for any HTTPS engine endpoint. The opt-out exists only for self-signed-cert merchants who understand the trust implications.
3. **Store `OPENSALESTAX_API_KEY` in `.env`**, never in `config/opensalestax.php` committed to VCS.
4. **Pin the engine version** you've tested with. The engine-side state-bleed bug fixed in v0.22 was a calculation-correctness issue, not a security issue — but engine bugs are real and worth tracking.
5. **Monitor `storage/logs/laravel.log`** for `opensalestax:` entries. Repeated `engine /v1/calculate failed` lines are an early indicator of an engine outage that fail-soft mode is silently absorbing.

## Reporting

Security issues: email **ejosterberg@gmail.com** directly. Don't open public GitHub issues for vulnerabilities.

Once a fix lands, the disclosure will be coordinated via:
- A CVE if the issue is widely-exploitable
- A GitHub Security Advisory on the repo
- A note in `CHANGELOG.md` with the fix version

## Re-review schedule

- **v0.2.0** — re-review when DNS-rebinding mitigation lands or when a settings-UI admin endpoint ships (introduces a new attack surface)
- **Quarterly** — `composer audit` + a quick pass on any new code paths
- **On every contributor PR** — manual review of any security-touching change
