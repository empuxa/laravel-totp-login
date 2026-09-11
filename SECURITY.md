# Security Policy

## Credits
We appreciate the security research community and will acknowledge reporters who
responsibly disclose vulnerabilities (unless they prefer to remain anonymous).

## Reporting a Vulnerability
Please get in touch with me directly: https://marcoraddatz.com/contact
I'll have a look at the issue asap.


## Framework release blocker (2026-09-11)

Laravel 9–11 remain present in Composer constraints to avoid silently removing compatibility. They must not be advertised as security-supported by this release: the current [email-validation advisory](https://github.com/laravel/framework/security/advisories/GHSA-5vg9-5847-vvmq) and [signed-URL advisory](https://github.com/advisories/GHSA-crmm-hgp2-wgrp) identify affected legacy versions without a patched release in those major lines. A release claiming secure support for those lines is blocked until that support policy is explicitly resolved.

Patched minimums for current framework lines are Laravel 12.61.1 and 13.12.0. Known vulnerable transitive runtime versions are excluded by Composer conflicts. Audit results describe the resolved dependency set, not every version allowed by legacy compatibility constraints. Run `composer audit --locked` in each consuming application.

## Deliberately unchanged or unverified

- `superpin.bypassing_identifiers` can enable a configured superpin in production. This exception remains unchanged and must be reviewed by consuming applications.
- Real parallel MySQL/PostgreSQL verification is deferred. SQLite regression tests do not establish concurrent locking guarantees for those engines.
- Neutral responses are not constant-time: synchronous notification delivery and custom integrations can still reveal timing differences.
