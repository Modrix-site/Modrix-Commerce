# Payment gateway settings menus

Enabled modules that declare the `payment.gateway` capability have their `admin_navigation` entries rendered as tabs on Admin > Payments rather than in the sidebar. Each tab links to the module's existing configuration route. This works for third-party modules without a provider-specific core change.

Declare `admin.navigation`, `admin.routes`, and `payment.gateway`, then supply `admin_navigation` entries with a title, route, icon, order and permission. Use `payments.manage` for gateway credentials; keep authorization and password confirmation on the settings routes themselves. Entries are filtered by staff permissions and registered routes. Disabled modules do not register routes or menus on the next request. Ordinary modules continue using the sidebar.

Built-in Stripe, manual payment methods, checkout settings and payment events remain on the Payments screen.
