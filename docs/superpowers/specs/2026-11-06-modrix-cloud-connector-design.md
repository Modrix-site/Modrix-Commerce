# Modrix Cloud and the Modrix Cloud Connector: design

Status: approved in conversation 2026-11-06.

## Goal

Rename the store's "Marketplace" to **Modrix Cloud**, and move the store-side
client into a bundled module, **Modrix Cloud Connector**. When the connector
links the store to a Modrix Cloud account, Modrix Cloud lists every published
theme and module, installs free and owned items in one click and keeps the
store's licenses in sync.

## Store side

- Module `modrixcommerce/cloud-connector`, shipped in `modules/`. It owns:
  the cloud client, license service, package installer, the Modrix Cloud
  admin page and routes, the connection flow and the daily license check.
- Core keeps what other features use: `Signing`, `PackageArchive`,
  `PackageScanner`, and the data models `StoreLicense` and `UpdateHistory`
  (tables `marketplace_licenses`, `marketplace_update_history`). Uninstalling
  the connector therefore never deletes licenses of installed packages, and
  installed packages keep working while it is disabled (existing grace rules).
- Without the connector, Admin → Extensions shows a card pointing to it.
- Cloud address: connector setting, defaulting to `ECOMODULAR_MARKETPLACE_URL`.
  The trusted signing keys stay in configuration only.
- Old URL `admin/extensions/marketplace` redirects to the module page.

## Connection (OAuth-style, PKCE S256)

1. Store: Connect creates `state` and a code verifier (session), then
   redirects to `{cloud}/connect?instance_id&store_name&domain&return_url&state&code_challenge`.
2. Cloud: the customer signs in (or registers) and approves "Connect <store>
   (<domain>)". `return_url` must be on `domain`. A one-time code (5 minutes)
   is created and the browser returns to `return_url?code&state`.
3. Store: checks `state`, then `POST connections/token {code, code_verifier,
   instance_id}` → connection token (64 random characters; the cloud stores
   only its SHA-256). The store keeps it encrypted; it never reaches the
   browser.
4. Disconnect: `DELETE connections/current` and the store forgets the token.

## Connected features

- `GET catalog` with the token adds per item `owned` and `license`
  (`{key_hint, status, in_use_elsewhere}`).
- `GET connections/me`: account name and email, store name.
- `GET connections/licenses`: the account's licenses (with keys) for sync.
- `POST connections/licenses/claim {identifier}`: returns the account's
  license for the item, creating one for a free item; paid items not owned
  are refused (`not_owned`).
- Install: claim → activate through the existing signed flow → install with
  the existing verified installer. Paid items not owned: "Buy on Modrix
  Cloud" opens the item page.
- Manual key entry stays for keys bought without an account.

## Cloud side (marketplace-catalog)

Tables `store_connections` (user_id, instance_id, store_name, domain,
token_hash, last_used_at, revoked_at) and `connection_codes` (user_id,
connection data, code_hash, code_challenge, expires_at, used_at). Pages:
`/connect` (approve) and "Connected stores" on `/account` with disconnect.
API under `/api/marketplace/v1/connections/*` with bearer token, throttled.

## Out of scope

Buying inside the store admin, multiple cloud accounts per store, team
accounts.
