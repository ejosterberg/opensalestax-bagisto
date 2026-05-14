# Security Policy

## Reporting a vulnerability

Email **ejosterberg@gmail.com** with subject line starting `[opensalestax-bagisto] security:`. Include the affected version, reproduction steps, and impact. Do not open a public GitHub issue for security reports.

Acknowledgement target: 7 days. For critical issues (tax-correctness anomalies, admin-config exploitation, SSRF), mark `[critical]` in the subject line and expect a faster turnaround.

## Supported versions

The latest minor on `main` is supported. Older releases are not back-patched.

## Scope

This policy covers the OpenSalesTax package for Bagisto (`ejosterberg/opensalestax-bagisto`). Vulnerabilities in upstream Bagisto, Laravel, the OpenSalesTax engine, or merchant infrastructure should be reported to their respective maintainers.

See `docs/SECURITY-REVIEW.md` for the v0.1 threat model.
