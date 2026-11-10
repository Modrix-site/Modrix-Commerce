# CCAvenue for Modrix Commerce

Package: `modrixcommerce/ccavenue` · Publisher: **Modrix team** · Version: **1.0.0**

CCAvenue India hosted checkout (`ccavenue.com`) through the platform's `PaymentGateway` contract. Other regional CCAvenue products, including UAE accounts, require their own verified endpoint/API integration and are not supported by this release.

## Features

- Core payment-method registration, checkout selection, pending orders, inventory holds, payment reconciliation, invoices and order workflow.
- Owned, expiring POST handoff to CCAvenue; card fields stay on CCAvenue.
- Encrypted access code/working key; write-only credentials in admin; permission and recent password confirmation required to change settings.
- Enable/disable checkout, test mode, encrypted callbacks, API refunds, billing prefill and shipping prefill independently.
- Custom checkout label; English/Hindi; merchant-enabled INR, USD, SGD, GBP and EUR currencies; 5–60 minute local handoff lifetime.
- Server-side status verification, amount/currency checks through the core, duplicate callback handling, and core scheduled reconciliation.
- Optional full/partial API refund requests with stable `refund_ref_no`. Accepted refunds are pending bank settlement; verify settlement in M.A.R.S. This release does not poll refund settlement or import dashboard refunds.
- Standard module install/enable/disable/uninstall and signed marketplace/license installation. License failures follow core policy and do not automatically stop installed payment gateways.

## Publish and install

From the repository root, run `php marketplace-catalog/bin/publish-package.php modrixcommerce/ccavenue`. This creates the catalog listing, retained source, signed release and downloadable ZIP using the existing marketplace signer. It preserves the package's configured free/paid offer.

1. Open `/item/modrixcommerce-ccavenue` in the marketplace. Claim a free license from the customer account (or purchase first if the catalog offer is paid).
2. Configure the store's marketplace URL and trust the catalog public signing key using the existing marketplace settings. Never replace existing trusted keys.
3. Add the license under the core's Marketplace → Licenses. Pull/install the signed package, then install and enable it in Extensions. Existing source copies are discovered automatically; use a clean store to test the download installation path.
4. Run `npm run build` on the target store after downloading the module; the core bundles extension React pages with its frontend assets. Open Payments → CCAvenue. Save Merchant ID, Access Code and Working Key from M.A.R.S. Enable the status API and server IP allowlisting with CCAvenue. Refund API access is separate.
5. Select only currencies enabled on the merchant account. Start in test mode. Serve the store over public HTTPS. Enable callbacks for customer returns.
6. The extension supplies its POST response URL automatically. Optional notifications at `/webhooks/payments/ccavenue` must use `encResp`. Other CCAvenue notification formats are rejected.
7. Run the platform scheduler and queue worker. The existing reconciliation command handles pending payments when the shopper does not return. Disabling checkout preserves reconciliation for existing orders; disabling the whole module stops its routes and gateway registration.

The local link lifetime cannot revoke a CCAvenue page already opened by a shopper. Changing merchant credentials or test/live environment while payments are pending prevents reconciliation until the original settings are restored. Test and production accounts require their corresponding API access.

## Provider references

- [CCAvenue integration guide 3.3](https://links.avenues.info/downloads/CCAvenue_Integration_Ver_3_3.pdf)
- [CCAvenue merchant API guide](https://links.avenues.info/downloads/CCAvenues_API_Vers-1_3.pdf)

## Verification

`php artisan test --compact tests/Feature/Payments/CCAvenueTest.php tests/Feature/Marketplace/CCAvenuePackageTest.php`

Tests use isolated databases, mocked provider HTTP and a temporary marketplace. Live payment settlement requires merchant credentials and CCAvenue test/live access.
