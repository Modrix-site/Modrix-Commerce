# Security Policy

## Supported versions

EcoModular is pre-1.0. Only the latest `main` branch receives security fixes until the first stable release. After 1.0, the latest minor release and the previous minor release will receive fixes.

## Reporting a vulnerability

Report vulnerabilities privately through GitHub's "Report a vulnerability" (private security advisory) on the project repository. Don't open a public issue, discussion or pull request.

Please include the affected version or commit, reproduction steps and the impact. We aim to acknowledge reports within 3 business days and to publish a fix or mitigation for confirmed high or critical issues within 30 days.

## Security model (summary)

- **Authorization is server-side.** Routes declare permissions (`can:<permission>`). The React UI hides only what the server already forbids.
- **Privilege escalation guards.** Nobody can grant permissions they don't hold. Only owners can grant the owner role. Nobody can edit their own roles. The last active owner can't be removed or disabled.
- **Step-up authentication.** User and role management requires a recent password confirmation (`AUTH_PASSWORD_TIMEOUT`).
- **Installer.** The web installer requires a setup token stored on the server's filesystem, and it returns 404 once installation is complete.
- **Audit log.** Security-relevant actions are recorded append-only. Metadata keys that look like secrets are redacted before storage.
- **Secrets.** Never commit `.env`. Docker Compose credentials are development-only placeholders.
- **Payments (Phase 3).** Card data is never stored. Webhooks must be signature-verified and processed idempotently. A browser redirect never marks an order paid.
- **Extensions (Phase 4+).** A valid license or signature does not make PHP code safe. Extensions are never auto-enabled. Enabling executable code requires explicit administrator confirmation, and installed PHP extensions run with the application's server privileges.

## Hardening checklist

See [docs/installation/production.md](docs/installation/production.md#post-install-hardening-checklist).
