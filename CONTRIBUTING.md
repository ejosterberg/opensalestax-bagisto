# Contributing to opensalestax-bagisto

Thanks for considering a contribution. The bar is "small-merchant production-quality" — please read the project constitution at [`specs/constitution.md`](specs/constitution.md) before opening a PR that changes behavior.

## Developer Certificate of Origin (DCO)

Every commit must carry a DCO sign-off:

```bash
git commit -s -m "your message"
```

The `-s` flag appends `Signed-off-by: Name <email>` asserting your right to contribute under the project license. See <https://developercertificate.org/>.

## No AI co-author trailers

Do not add `Co-Authored-By:` trailers attributing AI assistants to commits, PR bodies, or release notes. Human contributors take responsibility for their contributions.

## Branch model

Single `main` branch, semver tags. Topic branches off `main`, PR back to `main`. No long-lived release branches.

## License

By contributing, you agree your contribution is dual-licensed under your choice of Apache-2.0 OR GPL-2.0-or-later (see `LICENSE`).

## Quality gate

Before opening a PR, run `composer check` locally. It runs:

- `vendor/bin/phpunit` — unit tests
- `vendor/bin/phpstan analyse` — level max type analysis
- `vendor/bin/php-cs-fixer fix --dry-run --diff` — PSR-12 + risky rules
- `composer audit` — security advisories on dependencies

PRs that fail CI cannot merge.

## Style points

- `declare(strict_types=1);` at the top of every PHP file
- SPDX header `// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later` on every new PHP file
- PHPDoc on every public method
- `final` on classes that aren't designed for extension (the Laravel/Bagisto convention; differs from Magento)
- No `mixed` return types without an inline justification

## Reporting bugs

Open a GitHub issue with the affected Bagisto version, the package version, PHP version, and a reproduction. For security issues see `SECURITY.md` — don't open a public issue.
