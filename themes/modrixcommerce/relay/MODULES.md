# Module and hook compatibility

Relay Electronics uses the platform extension renderer and commerce actions, with no module allowlist or copied payment logic. Installed, enabled modules register through the same public contracts as other themes. New modules using those contracts do not require a theme edit; rebuild frontend assets when adding new React components.

| Area               | Integration                                                                                                                                                                          |
| ------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Header             | header.actions via PlaceSlot                                                                                                                                                         |
| Home               | home.after_featured via enabled Apps section                                                                                                                                         |
| Catalog            | catalog.before_products via PlaceSlot; catalog.after_products via Apps/page.slots                                                                                                    |
| Product cards      | product.card with current product; ProductLabels from backend                                                                                                                        |
| Product            | product.after_title, product.after_price, product.after_add_to_cart, product.after_description in the module tab; product.after_details through the wide product module area         |
| Sales              | shared variant pricing, flash-sale SaleNotice, labels, adjusted prices from server                                                                                                   |
| Cart               | PlatformCartPage preserves cart.after_lines, cart.after_totals, shipping progress, cross-sells, taxes, fees and discount rows                                                        |
| Checkout           | native platform checkout/payment quote, checkout.payment_method, checkout.after_summary, checkout.before_place_order                                                                 |
| Customer orders    | native order.confirmation and account.order pages                                                                                                                                    |
| Footer             | storefront.footer via shared footerSlots                                                                                                                                             |
| Global             | DefaultLayout retains storefront.overlay, SEO/analytics, popups, cookies, editor bridge and navigation shell                                                                         |
| Backend hooks      | native cart/checkout actions retain pricing steps, sale-price providers, image URL providers, checkout validators, payment gateways, shipping/tax/search providers and domain events |
| Admin-only modules | standard admin pages, permissions, schedules and event handlers remain platform-owned                                                                                                |

The latest customizer can move, reorder and disable extension blocks. Apps sections must remain enabled on pages where page-level modules should appear. Modules keep their own configuration, credentials and enablement requirements. Theme compatibility does not certify a live payment transaction or every possible third-party combination.

## Verification

Six React render contract tests exercise Relay Electronics’s product hooks, card product props, labels/sales notices, catalog hooks, header/footer, native cart delegation and enabled page app sections. Backend integration checks cover the theme, latest editor, demo import, filters, cart and Buy now. The module inventory below records the packages currently present, not a claim that every gateway has been live-transaction tested.

- Advanced SEO (modrixcommerce/advanced-seo): admin.routes, admin.navigation, storefront.slots, settings, permissions
- AI Content Assistant (modrixcommerce/ai-assistant): admin.routes, admin.navigation, settings
- Amazon Payment Services (modrixcommerce/amazon-payment-services): payment.gateway, admin.routes, admin.navigation, storefront.routes, content_security
- Authorize.Net (modrixcommerce/authorize-net): payment.gateway, storefront.routes, admin.routes, admin.navigation, content_security
- Bot Protection (modrixcommerce/bot-protection): admin.routes, admin.navigation, storefront.routes, database.migrations, events.listen, scheduled.tasks, console.commands, settings, permissions
- Cart Abandonment Recovery (modrixcommerce/cart-abandonment-recovery): admin.routes, admin.navigation, storefront.routes, storefront.slots, database.migrations, events.listen, scheduled.tasks, console.commands, settings, permissions
- Cashfree Payments (modrixcommerce/cashfree): payment.gateway, admin.routes, admin.navigation, storefront.routes, content_security
- CCAvenue (modrixcommerce/ccavenue): payment.gateway, admin.routes, admin.navigation, storefront.routes, content_security
- Checkout.com (modrixcommerce/checkout-com): payment.gateway, storefront.routes, admin.routes, admin.navigation, content_security
- Modrix Cloud Connector (modrixcommerce/cloud-connector): admin.routes, admin.navigation, console.commands, scheduled.tasks
- Cross-sell & Upsell (modrixcommerce/cross-sell-upsell): admin.routes, admin.navigation, storefront.routes, storefront.slots, database.migrations, pricing.steps, events.listen, settings, permissions
- Live Currency Converter (modrixcommerce/currency-converter): settings, scheduled.tasks
- Easebuzz Payments (modrixcommerce/easebuzz): payment.gateway, admin.routes, admin.navigation, storefront.routes, content_security
- Flash Sales (modrixcommerce/flash-sales): admin.routes, admin.navigation, storefront.slots, database.migrations, catalog.sale_prices, settings, permissions
- Fraud Detection (modrixcommerce/fraud-detection): admin.routes, admin.navigation, database.migrations, events.listen, scheduled.tasks, console.commands, settings, permissions
- Frequently Bought Together (modrixcommerce/frequently-bought-together): admin.routes, admin.navigation, storefront.routes, storefront.slots, settings, permissions
- GDPR Cookie Consent (modrixcommerce/gdpr-cookie-consent): admin.routes, admin.navigation, storefront.routes, storefront.slots, database.migrations, settings, permissions
- Google Analytics (modrixcommerce/google-analytics): admin.routes, admin.navigation, storefront.slots, settings, permissions, content_security
- Image Studio (modrixcommerce/image-studio): admin.routes, admin.navigation, database.migrations, settings, permissions, events.listen, console.commands, catalog.image_urls
- Instamojo Payments (modrixcommerce/instamojo): payment.gateway, admin.routes, admin.navigation, storefront.routes, content_security
- Login Monitoring (modrixcommerce/login-monitoring): admin.routes, admin.navigation, database.migrations, events.listen, scheduled.tasks, console.commands, settings, permissions
- MyFatoorah Payments (modrixcommerce/myfatoorah): payment.gateway, admin.routes, admin.navigation, storefront.routes, content_security
- Newsletter Sign-up (modrixcommerce/newsletter): admin.routes, admin.navigation, storefront.routes, storefront.slots, database.migrations, settings, permissions
- Network International (N-Genius) (modrixcommerce/ngenius): payment.gateway, admin.routes, admin.navigation, storefront.routes, content_security
- Paid Order Alerts (modrixcommerce/order-alerts): events.listen, notifications, settings, database.migrations
- Order Returns (modrixcommerce/order-returns): admin.routes, admin.navigation, storefront.routes, storefront.slots, database.migrations, settings, permissions
- PayBy (Astra Tech) (modrixcommerce/payby): payment.gateway, admin.routes, admin.navigation, storefront.routes, content_security
- PayPal (modrixcommerce/paypal): payment.gateway, admin.routes, admin.navigation
- PayTabs (modrixcommerce/paytabs): payment.gateway, admin.routes, admin.navigation, storefront.routes, database.migrations
- Paytm Payment Gateway (modrixcommerce/paytm): payment.gateway, admin.routes, admin.navigation, storefront.routes, content_security
- PayU Payments (modrixcommerce/payu): payment.gateway, admin.routes, admin.navigation, storefront.routes, content_security
- PhonePe Payment Gateway (modrixcommerce/phonepe): payment.gateway, admin.routes, admin.navigation, storefront.routes, content_security
- Product Addons & Warranties (modrixcommerce/product-addons): admin.routes, admin.navigation, storefront.routes, storefront.slots, database.migrations, pricing.steps, events.listen, settings, permissions
- Product Fields (modrixcommerce/product-fields): admin.routes, admin.navigation, storefront.slots, database.migrations, permissions
- Product Label (modrixcommerce/product-label): admin.routes, admin.navigation, product.labels, settings, permissions
- Product Reviews (modrixcommerce/product-reviews): admin.routes, admin.navigation, storefront.routes, storefront.slots, database.migrations, settings, permissions
- Marketing Popup (modrixcommerce/promo-popup): admin.routes, admin.navigation, storefront.routes, storefront.slots, database.migrations, settings, permissions
- Razorpay (modrixcommerce/razorpay): payment.gateway, admin.routes, admin.navigation, storefront.routes, database.migrations, content_security
- Advanced Refund Management (modrixcommerce/refund-management): admin.routes, admin.navigation, database.migrations, settings, permissions, events.listen
- Sales Trend Widget (modrixcommerce/sales-widget): dashboard.widgets, settings
- XML Sitemap (modrixcommerce/sitemap): admin.routes, admin.navigation, settings, permissions, sitemap.provider
- Social Proof Notifications (modrixcommerce/social-proof-notifications): admin.routes, admin.navigation, storefront.routes, storefront.slots, database.migrations, settings, permissions, events.listen
- Tabby: Buy Now, Pay Later (modrixcommerce/tabby): payment.gateway, storefront.slots, storefront.routes, admin.routes, admin.navigation, settings, content_security
- Tamara: Buy Now, Pay Later (modrixcommerce/tamara): payment.gateway, storefront.slots, storefront.routes, admin.routes, admin.navigation, settings
- Tap Payments (modrixcommerce/tap): payment.gateway, admin.routes, admin.navigation
- Telr (modrixcommerce/telr): payment.gateway, storefront.routes, admin.routes, admin.navigation, content_security
- Ziina Payment Gateway (modrixcommerce/ziina): payment.gateway, admin.routes, admin.navigation, storefront.routes, content_security
