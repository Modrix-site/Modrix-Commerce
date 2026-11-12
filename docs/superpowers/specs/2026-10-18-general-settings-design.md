# General settings: design

**Status:** draft for review · **Date:** 2026-10-18

## Goal

One **Settings → General** page in the admin for what the store owner configures once and rarely touches:
admin branding (logos), outgoing email (SMTP), maintenance mode, security, and social links with custom
scripts. Existing settings (store, languages, documents) stay where they are.

## Page and permissions

- Route `admin/settings/general`, tabs: **Branding**, **Email**, **Maintenance**, **Security**, **Social & scripts**.
  Each tab saves on its own (one form per tab), with the usual toast and audit log entry.
- `settings.manage` (existing) edits Branding, Email, Maintenance and Social links.
- New permission **`settings.advanced`** ("Security and custom code", owner and administrator roles by default)
  edits **Security** and **Custom scripts**, because a script can read anything a shopper types and security
  settings can lock staff out. Staff without it see those sections read-only with a note.
- Every save is audited (`settings.general_updated` with changed keys and before/after values); secrets are
  never written to the audit log (logged as "changed").

## 1. Branding

| Setting              | Notes                                                                                              |
| -------------------- | -------------------------------------------------------------------------------------------------- |
| Panel name           | Shown next to the admin logo (default: store name).                                                |
| Admin logo           | Sidebar logo, light background. PNG/JPEG/WebP up to 1 MB.                                          |
| Admin logo (dark)    | Optional, used in dark mode; falls back to the light one.                                          |
| Admin icon           | Square mark for the collapsed sidebar; falls back to the current icon.                             |
| Favicon              | PNG/ICO up to 256 KB; used by admin and storefront (a theme's own favicon wins on the storefront). |
| Accent colour        | Sidebar brand colour (`--sidebar-brand`); hex only.                                                |
| Login logo + message | Shown on the sign-in page (e.g. "Staff sign-in only").                                             |

SVG is not accepted (it can carry scripts). Files go to the media disk under `branding/`; replacing or removing
a file deletes the old one. Branding is shared to every Inertia page as `branding` (URLs only).

## 2. Email (SMTP)

| Setting                 | Notes                                                                                                                                                                                           |
| ----------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Mail delivery           | `Use server default (.env)` or `SMTP`.                                                                                                                                                          |
| Host, port, encryption  | Encryption `tls`, `ssl` or none; port 1–65535.                                                                                                                                                  |
| Username                | Plain text.                                                                                                                                                                                     |
| Password                | Encrypted with the app key (`Crypt::encryptString`). Never sent back to the browser: the form shows "A password is saved"; leaving the field empty keeps it; "Remove password" clears it.       |
| From address, from name | Used by every store email. Reply-to optional.                                                                                                                                                   |
| Send a test email       | Sends synchronously to an address the admin types (default: their own) using the values just saved; shows success or the mail server's error (credentials masked). Rate-limited (5 per minute). |

Runtime: a `MailSettings` service applies the saved values to `mail.default`, `mail.mailers.smtp.*` and
`mail.from` when the app boots and again before each queued job (queue workers are long-running), and purges the
mailer after a save. With "Use server default" nothing is overridden. If the stored password cannot be
decrypted (app key changed), mail falls back to the server default and the page says the password must be
re-entered.

## 3. Maintenance mode

| Setting              | Notes                                                                 |
| -------------------- | --------------------------------------------------------------------- |
| Storefront closed    | On/off.                                                               |
| Headline, message    | Shown on a simple branded page (503 with `Retry-After`).              |
| Reopens at           | Optional date and time, shown on the page and used for `Retry-After`. |
| Allowed IP addresses | Optional list; these visitors see the store normally.                 |

Only the storefront closes. Staff signed in to the admin see the store with a "Maintenance mode is on" bar. The
admin, sign-in, password reset, payment-provider webhooks and return URLs, health checks, theme assets and the
sitemap's robots response keep working.

## 4. Security (`settings.advanced`)

| Setting                      | Notes                                                                                                                                                                                    |
| ---------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Require two-factor for staff | Staff accounts without 2FA are sent to their security settings until they enable it (the owner is warned before turning this on without 2FA themselves; saving is refused in that case). |
| Session lifetime             | Minutes, 15–1440 (default from `.env`).                                                                                                                                                  |
| Sign-in attempts             | Attempts per minute before a lockout, 3–20 (Fortify login limiter).                                                                                                                      |
| Minimum password length      | 8–64, applied to new and changed passwords.                                                                                                                                              |

## 5. Social & scripts

- **Social links** (settings.manage): Facebook, Instagram, X, TikTok, YouTube, LinkedIn, Pinterest, WhatsApp;
  validated https URLs; shared to the storefront as `storefront.social` so themes can show them (Circuit's
  footer uses them when its own social fields are empty).
- **Custom code** (`settings.advanced`): a head snippet and a body-end snippet (e.g. analytics, chat widgets), each
  on/off, max 10,000 characters. Rendered only on storefront pages (not admin, not checkout unless "Also on
  checkout" is ticked). `<script>` tags in the snippet get the page's CSP nonce, and **Allowed script hosts**
  (a list of https origins) are added to the CSP's `script-src`/`connect-src`/`frame-src`. A clear warning
  explains that code here runs for every shopper.

## Data

All values live in the settings store under `general.*` (`general.branding.*`, `general.mail.*`,
`general.maintenance.*`, `general.security.*`, `general.social.*`, `general.scripts.*`); the SMTP password under
`general.mail.password_encrypted`. No new tables. Defaults reproduce today's behaviour, so nothing changes until
the owner saves.

## Testing

Feature tests per section: validation; permissions (settings.manage vs settings.advanced; customers forbidden);
SMTP password encrypted at rest, never in props or audit log, kept when blank, removed on request; mail config
applied at runtime and test email sent with `Mail::fake` and an SMTP failure message masked; branding uploads
(type/size, old file deleted, SVG refused) and shared props; maintenance (storefront 503 with Retry-After,
admin/webhooks/login/staff/allowed IP pass); 2FA requirement redirect and owner self-lockout refusal; session
lifetime and login limiter applied; password minimum; scripts rendered with nonce only on the storefront, hosts
added to CSP, checkout excluded by default. Browser check of the page and a test email to the dev mail log.

## Out of scope

Per-store multi-domain mail, DKIM setup, IP blocklists, custom CSS (themes cover it).
