# Telr

Hosted payment page and iframe checkout for Modrix Commerce. Card details are entered on Telr. This store confirms each payment with Telr's `order.json` check API before marking an order paid, and refunds through Telr's remote API.

Setup, webhook signature, test mode and go-live steps: [docs/extensions/telr.md](../../../docs/extensions/telr.md).

Telr's own API reference: [hosted payment page](https://docs.telr.com/reference/payment-page), [iframe display](https://telr-api.readme.io/reference/iframe-display), [transaction advice](https://docs.telr.com/reference/webhook), [refunds](https://docs.telr.com/reference/request-methods).
