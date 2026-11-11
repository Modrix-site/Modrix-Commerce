# Changelog

All notable changes to the `modrixcommerce/newsletter` module will be documented in this file.

## [2.0.0] - 2026-11-10

### Added
- **Full Email Marketing System**:
  - Broadcast campaign engine allowing store owners to draft, preview, test, and send newsletters directly from Modrix Commerce.
  - Responsive campaign template with personalization tags (`{{name}}`, `{{email}}`, `{{store_name}}`, `{{unsubscribe_url}}`).
  - Target audience segmentation: broadcast to all active subscribers, store customers only, storefront sign-ups, or custom audience groups.
  - Delivery and failure statistics per campaign with error diagnosis.
- **Audience Groups & Segments**:
  - Custom audience groups/tags with customizable colors, descriptions, and dynamic subscriber counts.
  - Multi-select bulk tagging and group assignment for subscribers.
  - Subscriber profile editing to add/remove groups.
- **App Core SMTP Integration**:
  - Direct connection to `App\Core\Settings\MailSettings` and `App\Core\Settings\GeneralSettings`.
  - Configurable SMTP host, port, encryption (TLS / SSL / None), credentials, and sender headers.
  - Live SMTP connectivity status indicator.
  - On-demand "Send Test Email" tool to verify delivery before launching campaigns.
- **Admin Dashboard**:
  - Modern multi-tab admin interface: Subscribers, Audience Groups, Campaigns, and SMTP Settings.
  - Quick KPI stats: Total subscribers, active subscriptions, customer conversions, groups, and campaigns.
  - Search and filter by status and audience segment.
