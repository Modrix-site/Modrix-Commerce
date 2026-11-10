# PayTabs for Modrix Commerce

Package: `modrixcommerce/paytabs` · Publisher: **Modrix team** · Version: **1.0.0**

PayTabs hosted **sale** checkout through the platform's `PaymentGateway` contract. Card details and payment tokens are not collected or stored by this module. This release does not implement authorization/capture checkout, saved cards, subscriptions or direct card collection.

## Features

- Core checkout selection, payment reconciliation, order workflow, inventory holds, invoices and refund accounting.
- Ten official regional API hosts: UAE, Saudi Arabia, Egypt, Oman, Jordan, Kuwait, Iraq, Morocco, Qatar and Global.
- Separate encrypted test/live server keys and profile IDs. Selecting test mode chooses the test profile; a local flag cannot turn a live PayTabs profile into a test profile.
- Persisted original payment region/profile/key fingerprint. Existing orders keep using their original mode and region when new checkout settings change. Keep both profiles' credentials saved; replacing an original key or profile requires restoring it to reconcile old transactions.
- Independent enable/disable controls for checkout, callbacks/IPN, API refunds, billing prefill, shipping prefill and hidden address fields.
- All merchant-enabled payment methods, or a selected set of cards, wallets and local methods. Regional/profile activation and currency restrictions still apply.
- Custom checkout label, English/Arabic pages, selected merchant currencies, and a 5–60 minute local session reuse lifetime. Local expiry cannot revoke a provider page already opened.
- HMAC verification for raw JSON callbacks/IPN and separately for browser returns. Provider status is re-read through the API before the core changes financial state. Amount/currency, transaction type, merchant profile, cart and transaction reference are checked. Held payments remain processing.
- Full/partial refund requests; successful and pending responses map to core refunds. Signed IPN updates can confirm pending refunds or import refunds issued from the merchant dashboard. Payment and refund notifications use the same core webhook inbox and duplicate handling.
- Persistent refund reservations prevent replay after a timeout, process crash or ambiguous response. The same request key cannot be reused with another amount/payment. An unresolved attempt blocks new API refunds for that payment until reconciled.
- An admin refund reconciliation form queries a known PayTabs refund reference, applies the verified result to the core and resolves the corresponding uncertain reservation. If the dashboard shows no refund, contact the merchant operations team before attempting another; this module deliberately provides no blind reset/resend action.
- Standard module migrations, install/enable/disable/uninstall, protected admin routes, credential masking, safe validation failures, audit events and signed marketplace licensing.

## Publish and install

From the repository root:

```text
php marketplace-catalog/bin/publish-package.php modrixcommerce/paytabs
```

The publisher creates the catalog listing, retained source copy, downloadable ZIP, SHA-256 and signed release using the existing marketplace key. It preserves any configured free/paid offer.

1. Open `/item/modrixcommerce-paytabs` on the marketplace. Claim a free license from your account, or purchase first if the offer is paid.
2. Configure the target core store's marketplace URL and trust the catalog public signing key, preserving existing trusted keys.
3. Add the license in the core Marketplace. Activation pulls the signed package when its files are absent or older. Then install the module (runs its two table migrations) and enable it in Extensions. A clean target store exercises the full download path; this source checkout already contains the module's files.
4. Run `npm run build` on the target after pulling the package. The core compiles extension React pages with its frontend assets.
5. Open **Payments → PayTabs**. Set the account region, test profile ID and test server key. Enable checkout; select only currencies and methods active on the merchant profile. Test before selecting the live profile.
6. Serve the store over public HTTPS. Return URLs are generated per payment. Configure `/webhooks/payments/paytabs` as the profile's IPN listener to receive later payment and refund updates. Keep callbacks enabled for these deliveries.
7. Run the platform scheduler and queue worker. Core reconciliation checks open payments if shoppers do not return. The module must remain enabled to register its gateway and routes; disabling only checkout preserves processing of existing payments.
8. Enable API refunds only when the merchant profile supports them. After an ambiguous request, inspect the PayTabs dashboard and use **Reconcile a refund** with the original core payment ID and the refund transaction's reference. Never enter the parent sale reference as a refund reference.

The module tables contain payment context and refund reservations, not credentials or customer/card details. Retain these tables and backups when maintaining a store; dropping them removes retry protection and historical context. The uninstall migration removes both tables. Marketplace outages follow core licensing policy and do not automatically disable installed payment gateways.

## Verification

```text
php vendor/phpunit/phpunit/phpunit tests/Feature/Payments/PayTabsTest.php tests/Feature/Marketplace/PayTabsPackageTest.php
npm run types:check
npm run build
```

Tests use isolated databases, provider HTTP fakes and a temporary signed marketplace. The signature test includes PayTabs' published verification example. Live charges/refund settlement require a merchant account, matching profile permissions and real test/live credentials.

## Provider documentation

- [Hosted payment initiation](https://docs.paytabs.com/manuals/PT-API-Endpoints/Integration-Types-Manuals/Hosted-Payment-Page/HPP-Step-3-Initiating-Payment/HPP-Initiating-Payment/)
- [Signature verification](https://support.paytabs.com/en/support/solutions/articles/60000718961)
- [Payment methods](https://docs.paytabs.com/manuals/PT-API-Endpoints/Integration-Types-Manuals/Request-Response-Parameters/Request-Parameter-Payment-Methods/)
- [Refund transaction API](https://docs.paytabs.com/manuals/PT-API-Endpoints/Integration-Types-Manuals/Hosted-Payment-Page/HPP-Step-7-Manage-Transactions/HPP-Step-7-Refund-Transaction/)
