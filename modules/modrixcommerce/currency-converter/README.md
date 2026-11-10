# Live Currency Converter

Shoppers pick another currency and product prices convert from the store currency using a live rate. Checkout and payments stay in the store currency.

Rates come from [ExchangeRate-API](https://www.exchangerate-api.com/) (`open.er-api.com`) and are reused for the number of hours set on the extension page. A failed lookup keeps the last good rate.

The default list is USD, AED, EUR, GBP and SAR. The store currency is skipped, so a USD store offers AED and a AED store offers USD. Change **Currencies shoppers can choose** to any codes the store already understands, separated by commas.
