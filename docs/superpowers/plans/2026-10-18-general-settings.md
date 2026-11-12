# General settings implementation plan

**Spec:** `docs/superpowers/specs/2026-10-18-general-settings-design.md` (approved 2026-10-18). **Execution:** native.

## Global constraints

- Values in the settings store under `general.*`; defaults keep today's behaviour.
- `settings.manage` for Branding, Email, Maintenance, Social; new `settings.advanced` for Security and Custom code.
- SMTP password: `Crypt::encryptString`, never in props, audit log or responses; blank keeps it.
- Every save audited (`settings.general_updated`), secrets logged as "changed".
- Shared files changed through recorded pairs so HEAD stays self-consistent; each commit runs alone.

## Tasks

1. **Foundation:** `App\Core\Settings\GeneralSettings` (typed getters, defaults, `props()`), permission
   `settings.advanced`, `GeneralSettingsController` (edit + one update action per section), routes, menu entry,
   page `admin/settings/general.tsx` with tabs. Test: page renders per permission; customers forbidden.
2. **Branding:** uploads (PNG/JPEG/WebP/ICO, sizes, SVG refused, old file deleted), shared `branding` prop,
   admin logo/icon/name/accent in the sidebar, favicon in the root view, login logo and message.
3. **Email:** `MailSettings` applies SMTP config on boot and before each queued job; encrypted password;
   test email (rate-limited, masked errors).
4. **Maintenance:** storefront middleware (503 + Retry-After), staff and allowed IPs pass, excluded routes,
   staff bar.
5. **Security:** 2FA requirement middleware for admin routes with self-lockout refusal; session lifetime; login
   limiter attempts; password minimum length.
6. **Social & scripts:** `storefront.social` prop; head/body snippets injected with nonce on storefront pages
   only (checkout opt-in); allowed hosts added to CSP.

Each task: failing tests first, then code; pint, phpstan, types, lint, build; commit with pairs.
