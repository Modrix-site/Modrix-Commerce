# Security Policy

## Supported versions

| Version         | Supported                         |
| --------------- | --------------------------------- |
| 1.0.x (and RCs) | Yes: security fixes               |
| < 1.0           | No: upgrade to the latest release |

## Reporting a vulnerability

Please **do not open a public issue**. Report it privately via the repository's security advisory form ("Report a vulnerability") or by email to the maintainers listed in the repository. Include:

- the affected version and component (core, a bundled module or theme, the marketplace server or client);
- steps to reproduce, and the impact you observed;
- whether the issue is already public.

You will get an acknowledgement within 3 working days and an assessment within 10. We coordinate a fix and a disclosure date with you, and credit you in the release notes unless you prefer otherwise.

## Incident response

When a vulnerability is confirmed:

1. **Assess** severity (CVSS) and exposure: core, an extension, or the marketplace infrastructure.
2. **Fix** on a private branch with a regression test, and release a patch version for every supported line.
3. **Disclose** with a GitHub security advisory and a CHANGELOG entry ("Security" section) explaining the impact and the required action. Notify marketplace customers by email when an extension they licensed is affected.
4. **Review** after the release: root cause, and whether a test, check or doc must change.

Operators who suspect a compromise of their store should:

- put the store in maintenance mode (`php artisan down`);
- rotate `APP_KEY` only together with re-entering encrypted credentials, rotate database, mail and Stripe credentials, and revoke sessions;
- review the audit log (Admin → Audit log) and `storage/logs`;
- restore from a backup taken before the incident (`docs/operations/backup-and-restore.md`).

## Extension takedown (official marketplace)

When a marketplace release is found to be malicious or dangerously vulnerable:

1. **Stop distribution:** the owner withdraws the release (Admin → Marketplace products → Withdraw). Stores are no longer offered it, and download tokens for it cannot be issued.
2. **Ship a fix:** publish a fixed version through the normal upload → review → sign flow. Stores see it as an update.
3. **Revoke if needed:** licenses of abusive accounts are revoked (Admin → Licenses), which stops activations and downloads.
4. **Notify:** publish a security advisory on the product (Admin → Marketplace products → Publish advisory) naming the affected versions and the fixed version. Stores receive it inside their signed license token on the next check (daily, or "Check now"), and stores running an affected version see it on the Marketplace page and in the health check. Also email affected license holders.
5. **Compromised signing key:** generate a new key (`php artisan ecomodular:marketplace:keygen`), re-sign the current releases, and ship a core release that removes the old public key from the trusted keys. Stores on that release then refuse anything signed with the old key.

Stores never disable extensions automatically, so merchants keep control of their storefront: the advisory, the health warning and the update notice are the channels for action. Advisories are signed, so a forged one is refused.

## Security model (summary)

- **Authorization is server-side.** Routes declare permissions (`can:<permission>`). The React UI hides only what the server already forbids.
- **Privilege escalation guards.** Nobody can grant permissions they don't hold. Only owners can grant the owner role. Nobody can edit their own roles. The last active owner can't be removed or disabled.
- **Step-up authentication.** User, role, payment, extension and marketplace actions that change code, money or access require a recent password confirmation (`AUTH_PASSWORD_TIMEOUT`).
- **Installer.** The web installer requires an expiring setup token stored on the server's filesystem, can be disabled (`ECOMODULAR_WEB_INSTALLER=false`), and returns 404 once installation is complete.
- **Audit log.** Security-relevant actions are recorded append-only. Metadata keys that look like secrets are redacted before storage.
- **Browser protections.** A nonce-based Content-Security-Policy, frame denial, `nosniff`, a strict referrer policy and HSTS on HTTPS (ADR-036).
- **Rate limits.** Login, two-factor, passkeys, password reset, registration, checkout, the license API and webhooks are rate limited.
- **Secrets.** Never commit `.env`. Gateway credentials are stored encrypted and are write-only in the admin. Docker Compose credentials are development-only placeholders.
- **Payments.** Card data is never stored. Webhooks are signature-verified and processed idempotently. A browser redirect never marks an order paid.
- **Extensions.** Modules run trusted PHP code inside the application (ADR-029): a valid license or signature does not make code safe. Extensions are never auto-enabled, and enabling executable code requires explicit administrator confirmation. Themes are data-only and cannot execute code (ADR-031). Marketplace packages are verified (signature, checksum, archive scan) before any file changes (ADR-034).
- **Backups.** Archives can be AES-256 encrypted, and restores verify every checksum before changing anything (ADR-037).

## Hardening checklist

See [docs/installation/production.md](docs/installation/production.md#post-install-hardening-checklist). Latest security review: [docs/security/phase-7-review.md](docs/security/phase-7-review.md).
