# Tasks — opensalestax-bagisto v0.1.0-alpha.1

Ordered execution list for the v0.1.0-alpha.1 ship. Cross items off in commit trailers.

## 1. Repo bootstrap

- [x] Create `opensalestax-bagisto/` working tree; `git init -b main`
- [x] Add `LICENSE` (Apache-2.0)
- [x] Add `.gitignore`, `.gitattributes`, `.editorconfig`
- [x] Add `composer.json` with package metadata + Laravel auto-discovery `extra.laravel.providers`
- [x] Add `phpstan.neon` (level max)
- [x] Add `phpunit.xml.dist` (PHPUnit 10)
- [x] Add `.php-cs-fixer.php` (PSR-12 + risky)
- [x] Add `sonar-project.properties`
- [x] Add `.github/workflows/ci.yml` (PHP 8.2/8.3 matrix + DCO check)
- [x] Add `README.md` / `CHANGELOG.md` / `CONTRIBUTING.md` / `SECURITY.md`

## 2. Spec docs

- [x] `specs/constitution.md`
- [x] `specs/phase-01-alpha/spec.md`
- [x] `specs/phase-01-alpha/plan.md` (this file's sibling)
- [x] `specs/phase-01-alpha/tasks.md` (this file)
- [x] `specs/current-state.md`
- [x] `specs/handoff.md`

## 3. Source

- [x] `src/Exceptions/OpenSalesTaxBagistoException.php`
- [x] `src/Exceptions/OpenSalesTaxConfigurationException.php`
- [x] `src/Support/UrlValidator.php`
- [x] `src/Support/RateCache.php`
- [x] `src/Support/CartPayloadBuilder.php`
- [x] `src/Support/OpenSalesTaxClientFactory.php`
- [x] `src/Listeners/CartTotalsListener.php`
- [x] `src/Providers/OpenSalesTaxServiceProvider.php`
- [x] `config/opensalestax.php`

## 4. Tests (target: 30+, achieved: 32)

- [x] `tests/Unit/Support/UrlValidatorTest.php` — 11 tests covering loopback / all RFC1918 / link-local / CGNAT / multicast / public / opt-in / scheme allowlist / unresolvable / empty / parse failure
- [x] `tests/Unit/Support/RateCacheTest.php` — 4 tests (miss writes / hit short-circuits / non-array stored value rebuilds / ttl is honored)
- [x] `tests/Unit/Support/CartPayloadBuilderTest.php` — 6 tests (single-line / multi-line / non-USD / non-US / missing-ZIP / malformed-ZIP)
- [x] `tests/Unit/Support/OpenSalesTaxClientFactoryTest.php` — 4 tests (empty base url returns null / good url builds client / private-net rejected without opt-in / private-net allowed with opt-in)
- [x] `tests/Unit/Listeners/CartTotalsListenerTest.php` — 7 tests (no client noop / non-USD noop / non-US noop / no-ZIP noop / happy path writes tax / engine error + fail-soft / engine error + fail-hard)

## 5. Quality gates

- [x] `composer install`
- [x] `vendor/bin/phpunit` — green
- [x] `vendor/bin/phpstan analyse` — green
- [x] `vendor/bin/php-cs-fixer fix --dry-run` — green
- [x] `composer audit` — no advisories at HIGH+
- [x] SonarQube scan — 0 / 0 / 0 / 0 across BUGS / VULNERABILITIES / CODE SMELLS / HOTSPOTS

## 6. Docs

- [x] `docs/SECURITY-REVIEW.md` — 12-threat model with mitigation status
- [x] `docs/INTEGRATION-CHECK.md` — live-engine smoke test recipe + result + the cart-flow recipe the orchestrator runs on VM 916

## 7. Ship

- [x] First commit with DCO (`chore: bootstrap`)
- [x] `gh repo create ejosterberg/opensalestax-bagisto --public --source . --push`
- [x] `git tag -a v0.1.0-alpha.1`; push tag
- [x] `gh release create v0.1.0-alpha.1 --prerelease`
- [x] Set GitHub topics (`bagisto`, `laravel`, `sales-tax`, `opensalestax`, `tax-calculation`)

## 8. Hand off

- [x] Return summary to orchestrator agent (public repo URL, tag, release URL, SonarQube metrics, test counts, version targeted, extension point chosen, red flags)
- [ ] Orchestrator's separate task: live cart integration on VM 916 (out of this session's scope)
