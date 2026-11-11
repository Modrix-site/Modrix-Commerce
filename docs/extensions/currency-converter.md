# Live Currency Converter

Shoppers switch currency in the store header. Prices on the catalog, product and cart convert from the store currency using a live rate. Checkout and the order stay in the store currency, and the existing notice still says the converted prices are estimates.

## Settings

On the extension page:

| Setting                        | Effect                                                                                                           |
| ------------------------------ | ---------------------------------------------------------------------------------------------------------------- |
| Currencies shoppers can choose | Comma-separated codes, such as `USD, AED, EUR, GBP`. The store currency is always included and is not converted. |
| Refresh rates every (hours)    | How long a fetched rate is reused. Default 6.                                                                    |

Rates are units of the chosen currency for 1 unit of the store currency. A rate typed by hand under store settings is replaced for any code this extension also lists. A failed lookup keeps the previous rate, or leaves the shop in the store currency when no rate has been stored yet.

The hourly scheduler refreshes the rates. The first storefront page after they expire refreshes them as well.
