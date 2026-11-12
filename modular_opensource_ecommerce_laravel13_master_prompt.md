# Master Development Prompt: Modular Open-Source eCommerce Platform

## 1. Role and Mission

Act as a principal Laravel architect, senior full-stack engineer, database designer, application security engineer, QA lead, and technical product manager.

Design and build a production-oriented, standalone, self-hosted eCommerce platform using **Laravel 13**, PHP, MySQL, React, Inertia.js, TypeScript, and Vite. The platform must have a genuinely usable **MIT-licensed open-source core** and an extensible architecture that supports free and paid themes, modules, plugins, integrations, and eventually third-party marketplace publishers.

Do not merely create mockups, placeholder screens, static dashboards, or nonfunctional CRUD demonstrations. Implement real application behavior, database persistence, validation, authorization, error handling, tests, and documented setup instructions.

Work through the phases in this document in order. Inspect the existing repository before changing it. Preserve working functionality where possible, identify existing defects, and avoid unnecessary rewrites. If a requested dependency or API is incompatible with Laravel 13 or the installed PHP version, verify compatibility and select a supported alternative rather than silently forcing an incompatible package.

## 2. Product Vision

Build a self-hosted eCommerce application that merchants can install on their own infrastructure. Its core commerce capabilities are free and open source under the MIT License. Revenue comes from premium themes, premium modules, paid integrations, support, updates, and an official extension marketplace.

### Initial release strategy

- Release a complete, useful single-store application first.
- The platform owner is the only marketplace publisher in version 1.
- Design marketplace data models to support third-party publishers later, without building the entire multi-vendor seller workflow in the first release.
- Keep the core, extension API, basic module manager, default theme, and developer documentation available to users of the open-source release.
- Commercial extensions may have separate license terms. Do not misrepresent the MIT license or attempt to make the MIT-licensed core depend on a proprietary license server for basic operation.
- Make the application deployable on a conventional PHP/MySQL VPS. Provide Docker Compose for development and optional containerized deployment. Do not require Kubernetes.

## 3. Non-Negotiable Engineering Rules

1. Use Laravel 13 and a PHP version officially supported by Laravel 13. Verify the actual runtime and dependency compatibility at the beginning.
2. Use MySQL with InnoDB for production relational data.
3. Use React, TypeScript, Inertia.js, and Vite for the admin application. Use a clear, reusable component system and responsive layouts.
4. Build a modular monolith first. Do not introduce microservices without a demonstrated requirement.
5. Keep business logic in domain services/actions and explicit contracts—not in large controllers or UI components.
6. Use Laravel policies, gates, request validation, CSRF protection, secure session settings, rate limits, and authorization checks on the server.
7. Use database transactions for critical operations such as order creation, inventory reservation, payment state changes, refunds, and license issuance.
8. Make payment webhooks and background jobs idempotent.
9. Never store raw card numbers or card security codes.
10. Never execute untrusted uploaded PHP or package code merely because it has a valid manifest or license key.
11. Do not rely on frontend controls for authorization or price calculation.
12. Never commit secrets, API keys, production credentials, or real customer data.
13. Do not use fake data to claim a feature is complete. Seed data may be used for demos and tests, clearly separated from production data.
14. No gradients in the default design system. Use a clean, professional, accessible interface with restrained colors, clear typography, consistent spacing, and strong responsive behavior.
15. Do not leave critical flows as TODOs, placeholder buttons, fake success messages, or simulated payment results in production paths.
16. Keep migrations reversible where practical. For destructive data migrations, document a tested recovery or forward-fix strategy.
17. Do not silently overwrite user work. Inspect the repository and make incremental changes.
18. Do not proceed past a phase's acceptance criteria until tests and the build pass, or explicitly document a blocker and the safest next action.

## 4. Initial Repository Assessment

Before implementation:

1. Inspect the repository, installed PHP/Composer/Node versions, Laravel version, package manifests, frontend tooling, database configuration, and existing tests.
2. Identify whether this is a new project or an existing application.
3. Produce a concise architecture assessment, dependency compatibility report, risk list, and phased execution plan.
4. Check Laravel 13-compatible versions of the proposed dependencies before installing them.
5. Record assumptions and decisions in `docs/architecture/decisions.md`.
6. Do not delete or replace existing working features without explaining why and obtaining approval if the change is destructive.

Create or maintain these project documents:

- `README.md`
- `LICENSE` — MIT license for the platform's own open-source code, with the appropriate copyright holder and year
- `CONTRIBUTING.md`
- `SECURITY.md`
- `CHANGELOG.md`
- `docs/architecture/overview.md`
- `docs/architecture/decisions.md`
- `docs/database/schema.md`
- `docs/extensions/module-development.md`
- `docs/extensions/theme-development.md`
- `docs/installation/production.md`
- `docs/operations/backup-and-restore.md`
- `docs/marketplace/licensing.md`
- `docs/testing/strategy.md`
- `docs/roadmap.md`

Do not invent copyright ownership or falsely claim that all third-party dependencies use MIT. Include third-party notices and review each dependency's license.

## 5. Recommended Technology and Infrastructure

### Backend
- Laravel 13
- Supported PHP version
- Composer
- MySQL 8-compatible production database, after confirming the selected deployment environment's support
- Redis for cache, queues, and rate limiting where available
- Laravel queue workers and scheduler
- Laravel-supported authentication approach appropriate for a standalone web application
- REST API versioning for future external clients

### Frontend
- React
- TypeScript
- Inertia.js
- Vite
- Tailwind CSS or the project's existing compatible styling system
- Accessible reusable UI components
- A chart library only where real reporting data warrants it

### Storage and deployment
- Local storage for development and small deployments
- S3-compatible object storage support through a storage abstraction
- Docker Compose for local development and optional deployment
- Nginx or another documented compatible web server
- Automated CI for backend tests, frontend type checks, linting, and production builds

Do not install dependencies speculatively. Document why each important package is needed, its license, and its Laravel/PHP compatibility.

## 6. High-Level Architecture

Build a modular monolith with explicit boundaries.

### Main layers

1. **Presentation layer**
   - React storefront
   - React admin dashboard
   - Inertia pages for first-party web flows
   - Versioned API for integrations and future headless clients

2. **Application layer**
   - Actions/use cases
   - Request validation
   - Authorization
   - Application services
   - DTOs/value objects where useful

3. **Domain layer**
   - Catalog
   - Pricing
   - Customers
   - Cart
   - Checkout
   - Orders
   - Payments
   - Inventory
   - Shipping
   - Tax
   - Discounts
   - Extensions
   - Themes
   - Marketplace and licenses

4. **Infrastructure layer**
   - MySQL persistence
   - Cache and queues
   - Payment provider adapters
   - Email
   - File/object storage
   - Scheduler
   - External integrations

### Suggested top-level structure

Adapt this to Laravel conventions instead of fighting the framework:

```text
app/
  Core/
    Extensions/
    Themes/
    Marketplace/
    Licensing/
    Events/
    Support/
  Domains/
    Catalog/
    Customers/
    Cart/
    Checkout/
    Orders/
    Payments/
    Inventory/
    Shipping/
    Discounts/
  Http/
    Controllers/
    Middleware/
    Requests/
    Resources/
  Policies/
  Providers/
  Support/
modules/
themes/
resources/
  js/
    admin/
    storefront/
  css/
routes/
database/
  factories/
  migrations/
  seeders/
tests/
  Feature/
  Unit/
  Architecture/
docs/
```

For independently versioned extensions, support Composer packages and a consistent package convention. Do not make every first-party module require a separate Composer package if that adds unnecessary overhead; use one predictable module contract for bundled and external modules.

### Architectural rules

- Domain code must not depend on React or Inertia.
- Critical commerce services must not depend directly on a particular payment vendor.
- Themes control presentation, not order/payment business rules.
- Modules use documented contracts, events, and registries rather than editing core files.
- Core APIs and extension contracts must have compatibility and deprecation policies.
- Keep module execution and theme rendering separate.
- Use dependency injection and Laravel service providers deliberately.
- Avoid global mutable state and uncontrolled runtime file inclusion.

## 7. Roles and Permissions

Implement server-enforced role-based access control.

Initial roles:
- Owner
- Administrator
- Store manager
- Catalog manager
- Order manager
- Support agent
- Read-only analyst

Define granular permissions such as:
- `products.view`, `products.create`, `products.update`, `products.delete`
- `orders.view`, `orders.manage`, `orders.refund`
- `customers.view`, `customers.manage`
- `inventory.view`, `inventory.manage`
- `settings.manage`
- `extensions.view`, `extensions.install`, `extensions.enable`, `extensions.disable`, `extensions.update`
- `themes.manage`
- `licenses.manage`
- `marketplace.manage`

Requirements:
- Enforce permissions in policies/controllers/services, not only in the UI.
- Prevent unauthorized role escalation.
- Protect sensitive account actions with re-authentication or another appropriate step-up control.
- Log security-relevant admin actions without logging passwords, tokens, or payment secrets.
- Include feature tests for forbidden and permitted actions.

## 8. Commerce Core Requirements

### 8.1 Catalog
Implement:
- Products and product types
- Simple products
- Products with variants
- SKU management
- Categories and nested categories
- Product attributes
- Images and media ordering
- Product descriptions and slugs
- Publication/draft status
- Search, filtering, pagination, and sorting
- Basic SEO metadata
- Product availability and stock status
- Product price and currency handling
- CSV import/export with validation and error reporting

Design product types to be extensible, but do not build every imaginable type in version 1.

### 8.2 Customers
Implement:
- Customer accounts
- Email verification
- Password reset
- Customer profiles
- Address book
- Order history
- Account security controls
- Guest checkout where configured

### 8.3 Cart and checkout
Implement:
- Persistent cart for authenticated users
- Guest cart with safe session handling
- Quantity changes and item removal
- Server-side price recalculation
- Discount application and validation
- Shipping selection
- Tax calculation abstraction
- Currency validation
- Checkout validation
- Order confirmation
- Stock validation/reservation
- Clear handling of unavailable products and changed prices

Never trust product price, tax, discount, shipping cost, or order total supplied by the browser.

### 8.4 Orders
Implement:
- Unique human-readable order number
- Order state machine with validated transitions
- Order item snapshots
- Billing and shipping address snapshots
- Currency and monetary snapshots
- Payment status and fulfillment status separately
- Admin order search and filters
- Order notes and audit history
- Email notifications
- Cancellation rules
- Refund records and reconciliation

Do not permit arbitrary state transitions through a generic status dropdown.

### 8.5 Inventory
Implement:
- Stock tracking by product variant
- Inventory adjustments
- Inventory movement history
- Optional stock locations in a way that does not complicate the first release
- Concurrency-safe stock reservation/decrement
- Configurable low-stock alerts
- Overselling policy
- Tests for concurrent checkout and insufficient stock

### 8.6 Discounts
Implement:
- Coupon codes
- Percentage and fixed discounts
- Start/end dates
- Usage limits
- Minimum cart value
- Eligible products/categories where feasible
- Validation and clear rejection reasons
- Audit history

### 8.7 Shipping and tax
Implement stable contracts for shipping methods and tax providers. Include a basic configurable manual shipping method and a configurable tax strategy. Do not claim legal tax compliance for every jurisdiction. Document that merchants must configure rules appropriate to their jurisdictions.

### 8.8 Search and reporting
Implement database-backed search and basic reporting first:
- Sales over time
- Order counts and status
- Top products
- Average order value
- Low stock
- Refund totals

Use real persisted data and date/currency-aware calculations. Make advanced search providers and analytics possible as modules.

## 9. Payment Architecture

Define a stable `PaymentGatewayInterface` and provider-specific adapters. At minimum, implement Stripe as a real gateway only after credentials and webhook configuration are available. Include a test-mode integration and a documented local setup.

The contract should cover:
- Payment/session creation
- Redirect or client confirmation details
- Webhook signature verification
- Webhook event normalization
- Refund support where the provider supports it
- Provider errors and retry behavior
- Idempotency keys
- Payment reconciliation

Rules:
- Never mark an order paid based only on a browser redirect.
- Verify webhook signatures.
- Persist provider event IDs and enforce idempotent processing.
- Use transactions when applying verified payment results.
- Handle duplicate, delayed, out-of-order, and failed webhook events.
- Do not store card numbers or CVV.
- Never ship live secrets in frontend code.
- Do not report a payment integration as complete until test-mode end-to-end flows pass.

Add cash on delivery or manual payment as a separate explicitly configured method, not as a fake Stripe fallback.

## 10. Theme Engine

Treat themes as versioned presentation packages.

### Theme capabilities
- Home page
- Category listing
- Search results
- Product details
- Cart and checkout presentation using stable core interfaces
- Customer account pages
- Header/footer and navigation
- Reusable product cards and content sections
- Responsive layouts
- Theme settings
- Colors, typography, spacing, logo, favicon
- Theme preview and publish/activate
- Demo content import, with explicit confirmation before altering existing content
- Compatibility declaration and changelog

### Theme package contract
Each theme must include a validated manifest with:
- Unique name
- Display name
- Version
- Theme type
- Supported platform version range
- Entry/manifest path
- Dependencies
- Declared capabilities
- Asset locations

The manifest is data, not executable code. Do not execute arbitrary PHP in a theme. Establish a controlled template/component rendering model. If server-side templates are used, document the trust model and restrict untrusted marketplace uploads.

### Theme requirements
- A theme update must not silently overwrite merchant content or settings.
- Separate theme defaults from merchant overrides.
- Validate package paths to prevent path traversal.
- Keep checkout functionality and security rules in the core.
- Include one high-quality free default theme and at least two sample premium-ready themes as later milestones.

Do not build a drag-and-drop page builder until stable theme sections and component contracts exist.

## 11. Module and Plugin System

The extension system is a first-class product feature and must be implemented early.

### 11.1 Module manifest
Create a schema-validated `module.json` or equivalent manifest with:
- `name`
- `display_name`
- `version`
- `type`
- `platform_version`
- `provider`
- `requires`
- `permissions`
- `capabilities`
- `migrations`
- `routes`
- `assets`
- Optional settings schema and admin navigation declarations

Use semantic versioning and explicit platform compatibility. Reject invalid manifests, unknown capabilities, conflicting module names, incompatible versions, and dependency cycles.

### 11.2 Lifecycle
Implement explicit states such as:
- Discovered
- Validated
- Installed
- Enabled
- Disabled
- Update pending
- Failed

Implement:
- Discover
- Validate
- Install
- Enable
- Disable
- Update
- Uninstall
- Dependency checks
- Lifecycle audit logs
- Safe failure reporting
- Cache/route/config rebuild steps as needed

Disabling a module must preserve its data. Uninstall must warn about data loss and require explicit confirmation. Prevent disabling a dependency required by an active module.

### 11.3 Extension contracts
Create contracts for common extension points:
- Payment gateway
- Shipping method
- Tax provider
- Search provider
- Notification channel
- Product type/attribute provider
- Admin navigation
- Scheduled tasks
- Event listeners
- Storefront components
- Checkout validators

Use typed contracts for critical operations and events for decoupled reactions. Document public APIs and their compatibility guarantees.

### 11.4 Event system
Define domain events such as:
- `ProductCreated`
- `OrderPlaced`
- `OrderPaid`
- `OrderCancelled`
- `OrderShipped`
- `InventoryLow`
- `CustomerRegistered`

Use queued listeners for noncritical side effects such as email and external notifications. Ensure event handling is safe under retries and duplicate delivery.

### 11.5 Module security
- Never treat a license key or package signature as proof that PHP code is safe.
- Validate package integrity, paths, metadata, declared capabilities, and dependencies.
- Store packages outside the public web root.
- Restrict file permissions.
- Require explicit administrator confirmation before enabling executable extensions.
- Do not automatically activate a newly uploaded extension.
- Provide a safe maintenance mode and recovery procedure for failed activation.
- Keep an audit trail of installation and update actions.
- Document that PHP extensions run with the application's server privileges. For public third-party submissions, introduce security review and stronger isolation/deployment controls before accepting executable code.
- Avoid arbitrary code execution through editable module settings, templates, or admin forms.

### 11.6 Sample modules
Create and test sample first-party modules:
1. Product reviews
2. Coupons/advanced promotions, if not included in core
3. Basic analytics widget
4. A sample notification listener

At least one module must demonstrate a full install-enable-configure-disable-update lifecycle.

## 12. Extension Marketplace and Commercial Licensing

Build the official marketplace for the platform owner’s products in the initial commercial release. Design data structures so third-party vendors can be added later.

### 12.1 Marketplace catalog
Implement:
- Marketplace product listings
- Product type: theme, module, integration, bundle
- Listing title, description, screenshots, compatibility, changelog, documentation, price, status
- Versioned releases
- Release package checksum
- Purchase records and line-item snapshots
- Customer downloads
- Refund and revocation handling
- License entitlement
- Update availability checks

Do not publish a package until it passes required validation and review.

### 12.2 Licensing
Commercial licenses should be separate from the MIT core license. Support:
- License issuance after verified purchase
- License status: active, expired, revoked, refunded, suspended
- Optional activation limits
- Domain/installation activation records
- Activation/deactivation
- Update entitlements
- Secure, short-lived download links
- License audit events
- A configurable grace period for temporary licensing-service outages

Do not block a merchant’s core storefront checkout merely because the marketplace licensing API is unavailable. Define which premium capabilities need online validation and which can continue during a temporary outage.

### 12.3 Secure updates
Implement:
- Release compatibility validation
- Signed release metadata or equivalent trusted integrity mechanism
- SHA-256 package checksum verification
- Authorization before download
- Download links with expiry
- Backup before update
- Controlled installation
- Failure reporting
- Rollback or documented recovery strategy
- Update audit history

A checksum detects corruption but does not establish publisher trust by itself. Verify signed metadata against trusted public keys when signing is implemented.

### 12.4 Payments for the marketplace
Keep marketplace payments separate from payments made by merchants’ storefront customers. The platform's own product checkout may use Stripe, but do not mix merchant order records with marketplace purchases.

For version 1, the platform owner is the sole seller. Defer vendor payouts, split payments, commission settlement, tax forms, and multi-vendor dispute workflows until third-party seller onboarding is explicitly scheduled.

### 12.5 Future third-party vendors
Plan for:
- Vendor profiles
- Seller verification
- Product submission and review
- Security scanning
- Version/release management
- Commission rules
- Refund and dispute handling
- Payout accounting
- Reviews and support policies
- Takedown and incident response
- Publisher audit history

Do not enable public uploads of executable extensions until the review, signing, security, and incident-response process is ready.

## 13. Suggested Database Model

Create migrations, models, factories, seeders, indexes, constraints, and tests. Normalize transactional data and use JSON only for genuinely flexible extension settings or structured attributes where appropriate.

### Commerce tables
- `users`
- `roles`
- `permissions`
- `role_user` or the selected RBAC package's compatible pivot tables
- `customers`
- `customer_addresses`
- `products`
- `product_translations`
- `product_variants`
- `product_attributes`
- `product_attribute_values`
- `categories`
- `product_categories`
- `product_images`
- `product_prices`
- `carts`
- `cart_items`
- `orders`
- `order_items`
- `order_addresses` or order address snapshots
- `payments`
- `payment_webhook_events`
- `refunds`
- `shipments`
- `inventory_locations` if required
- `inventory_stocks`
- `inventory_movements`
- `coupons`
- `coupon_redemptions`
- `settings`
- `audit_logs`

### Extension and theme tables
- `extensions`
- `extension_versions`
- `extension_installations`
- `extension_dependencies`
- `extension_settings`
- `extension_permissions`
- `extension_events`
- `themes`
- `theme_settings`
- `theme_assignments`

### Marketplace and licensing tables
- `marketplace_products`
- `marketplace_releases`
- `marketplace_orders`
- `marketplace_order_items`
- `licenses`
- `license_activations`
- `license_events`
- `download_tokens`

### Future vendor tables, not required to power the initial release
- `vendors`
- `vendor_payouts`
- `marketplace_commissions`
- `vendor_verifications`

### Database rules
- Use foreign keys where appropriate.
- Add indexes to lookup fields, status/date filters, SKU, slug, order number, and external provider references.
- Enforce uniqueness for critical identifiers and webhook event IDs.
- Use `DECIMAL` for money; never use floating point for currency.
- Store currency codes explicitly.
- Preserve order item, price, tax, discount, and address snapshots.
- Define deletion behavior deliberately; preserve financial and audit history.
- Use transactions and locking for inventory and order/payment state changes.
- Avoid putting the entire order, product, or customer record into a JSON column.
- Document relationships, indexes, lifecycle states, and data-retention choices.

## 14. API and Integration Standards

Provide a versioned API where external clients and modules need it.

Requirements:
- API versioning, e.g. `/api/v1`
- Consistent resource representations
- Form Request validation
- API Resources/DTOs
- Authentication appropriate to the client type
- Rate limiting
- Authorization scopes/permissions
- Pagination and filtering
- Consistent error format
- Idempotency support for critical write operations where applicable
- OpenAPI documentation for public endpoints
- No exposure of internal secrets, private paths, license keys, or unnecessary personal data

Do not build every admin page through the public API if Inertia is the simpler first-party option. Keep a clean boundary between first-party web flows and external API contracts.

## 15. Admin UI and UX

Build a professional, production-quality admin interface.

### Navigation
- Dashboard
- Catalog
- Categories
- Inventory
- Orders
- Customers
- Discounts
- Shipping
- Payments
- Reports
- Themes
- Extensions
- Marketplace
- Settings
- Users and permissions
- System status/logs

Only show menu items when the user has permission. Route-level authorization must still be enforced on the server.

### UI requirements
- Responsive sidebar and mobile navigation
- Searchable/filterable/paginated data tables
- Clear loading, empty, error, success, and permission-denied states
- Accessible labels and keyboard navigation
- Confirmation for destructive actions
- Consistent form validation and server error display
- No inert buttons or fake actions
- No gradient backgrounds in the default design
- Use real persisted data
- Avoid unnecessary animation
- Provide status badges only when they convey meaningful state

### Dashboard
Show real metrics such as sales, orders, pending fulfillment, low-stock items, and recent activity. Use explicit timezone and currency conventions.

## 16. Installation and Operations

Build an installation and update workflow suitable for self-hosted users.

### Installation
- Check PHP extensions and version
- Check writable directories
- Validate database connection
- Create/apply migrations
- Create the first owner account securely
- Generate and protect application secrets
- Configure base URL, storage, cache, queue and mail
- Provide a post-install hardening checklist
- Prevent the installer from being exposed or rerun unsafely after setup

Never display secrets in logs or error pages.

### Operations
Document:
- Production environment variables
- Web server configuration
- Queue worker configuration and restart process
- Scheduler configuration
- Cache and config optimization
- Storage symlink or object storage setup
- HTTPS
- Database backups and restore
- Log rotation
- Health checks
- Disk space monitoring
- Update and rollback procedure
- Permission and ownership guidance
- Troubleshooting

Provide Docker Compose for local development with app, database, Redis, and required supporting services. Do not expose database or Redis ports publicly by default. Keep sample secrets clearly marked as development-only.

## 17. Security Requirements

Use a threat-model-driven approach and follow current OWASP guidance.

Cover:
- Authentication, email verification, password reset, and session security
- Authorization and privilege escalation
- CSRF, XSS, SQL injection, SSRF, path traversal, unsafe file uploads
- Secure image and archive processing
- Package signature and integrity checks
- Rate limits for login, password reset, checkout, and license activation
- Secret management
- Payment/webhook verification
- Admin audit logs
- Secure cookies and HTTPS deployment
- Mass-assignment prevention
- Output escaping
- Data minimization and privacy controls
- Dependency vulnerability checks
- Backup encryption/access controls where supported
- Security incident and extension takedown process

Treat marketplace packages, extension metadata, theme assets, imported CSVs, webhook payloads, and all external input as untrusted.

## 18. Testing and Quality Gates

Write tests continuously, not only at the end.

### Unit tests
- Pricing and discount rules
- Order state transitions
- Inventory calculations
- License entitlement checks
- Extension dependency resolution
- Manifest validation
- Permission logic

### Feature tests
- Authentication and role permissions
- Product/catalog CRUD
- Guest and authenticated cart
- Checkout and order creation
- Payment webhook processing
- Duplicate webhook handling
- Refunds
- Inventory concurrency safeguards
- Module lifecycle
- Theme activation
- License activation and expiry
- Secure download authorization
- Installer access restrictions

### Integration and end-to-end tests
- Successful test-mode purchase
- Failed payment
- Duplicate/out-of-order webhooks
- Product stock changes during checkout
- Module install, enable, disable, update and recovery
- Theme switching without checkout regressions
- Marketplace purchase through download and activation
- Responsive storefront and admin key flows

### Quality gates for every phase
- Automated tests pass
- Static analysis and linting pass where configured
- TypeScript checks pass
- Production frontend build succeeds
- No critical unresolved security defect
- No known broken critical flow
- Documentation updated
- Migrations and seeders tested from a clean database

Report exact commands run and actual results. Never claim tests passed if they were not executed.

## 19. Phased Delivery Plan

Implement the following phases sequentially. Each phase must deliver working code, migrations, tests, documentation, and a concise completion report.

### Phase 0 — Discovery and technical plan
Tasks:
- Inspect repository and runtime
- Confirm Laravel 13/PHP/package compatibility
- Create architecture diagrams and decisions
- Define MVP scope and acceptance criteria
- Identify security and licensing risks

Deliverables:
- Repository assessment
- Architecture overview
- Dependency list with license notes
- Updated roadmap

Exit criteria:
- No major architectural ambiguity remains
- Existing functionality and risks are documented

### Phase 1 — Application foundation
Tasks:
- Configure Laravel, database, frontend build, environment
- Implement authentication and secure initial owner creation
- Implement RBAC
- Establish logging, exception handling, settings, and audit logging
- Set up factories, seeders, CI, and test conventions
- Create basic admin shell and responsive design system

Deliverables:
- Clean install
- Secure owner login
- Working role/permission enforcement
- Automated CI baseline

Exit criteria:
- Fresh setup and migrations work
- Auth and permission tests pass
- Frontend production build succeeds

### Phase 2 — Commerce core
Tasks:
- Catalog, products, variants, categories, media, search
- Customers and addresses
- Cart and checkout
- Orders and order history
- Inventory movements and concurrency protection
- Discounts
- Shipping and tax abstractions
- Email notifications and basic reports

Deliverables:
- Working merchant admin
- Working customer storefront
- End-to-end test purchase using an explicitly configured payment method

Exit criteria:
- No fake price calculations
- Order snapshots and inventory behavior are tested
- Checkout, validation, and authorization tests pass

### Phase 3 — Payment integrations
Tasks:
- Payment gateway contracts
- Stripe test-mode integration
- Webhook verification and idempotency
- Refund flow where supported
- Payment state reconciliation and error handling
- Documentation for provider setup

Deliverables:
- Real test-mode payment flow
- Payment event audit trail
- Webhook replay/duplicate tests

Exit criteria:
- Browser redirect alone cannot mark orders paid
- Duplicate webhooks do not duplicate effects
- Secrets remain server-side

### Phase 4 — Module engine
Tasks:
- Manifest schema and validation
- Module registry and lifecycle state machine
- Dependency and version resolution
- Service provider registration
- Contracts and event system
- Settings and permissions
- Migration lifecycle and error recovery
- Extension admin interface
- Sample module

Deliverables:
- Working install/enable/configure/disable/update flow
- Developer documentation and sample package

Exit criteria:
- Incompatible packages are rejected
- Failed activation/update has a documented recovery process
- Disable preserves data
- Extension lifecycle tests pass

### Phase 5 — Theme engine
Tasks:
- Theme manifests and compatibility checks
- Layout and component contracts
- Storefront templates and sections
- Theme configuration and merchant overrides
- Preview/activate workflow
- Default theme polish
- Two sample premium-ready themes

Deliverables:
- Theme manager
- Developer guide
- Theme compatibility tests

Exit criteria:
- Theme switching does not break core commerce
- Invalid paths/packages are rejected
- Merchant settings are preserved appropriately

### Phase 6 — Official marketplace and licensing
Tasks:
- Marketplace listings and releases
- Platform-owner product publishing
- Marketplace checkout
- License issuance and activation
- Update entitlement checks
- Secure package downloads
- Signed release metadata or equivalent trusted verification
- Refund/revocation handling
- Customer purchase/download portal

Deliverables:
- Buy → license → activate → download → update flow
- Marketplace administration
- License audit history

Exit criteria:
- Unauthorized users cannot download paid packages
- Integrity verification is enforced
- Licensing-service outage does not break basic store operations
- Refund/revocation behavior is tested

### Phase 7 — Production hardening
Tasks:
- Security review and dependency audit
- Performance review and indexes
- Queue and scheduler documentation
- Backup/restore drills
- Installer hardening
- Error and health monitoring
- Accessibility and responsive QA
- Production deployment guide
- Final license/attribution audit

Deliverables:
- Release candidate
- Deployment and recovery documentation
- Security findings and remediation report

Exit criteria:
- Critical tests pass
- Restore process is demonstrated
- No unresolved critical/high security issue without an explicitly approved mitigation
- Fresh installation and upgrade paths are tested

### Phase 8 — Post-launch ecosystem
Only after the initial release is stable:
- Developer SDK and module scaffolder
- Public extension documentation
- Third-party seller accounts and review workflow
- Vendor commission and payout accounting
- Package security scanning and signing infrastructure
- Extension reviews and support workflows
- More gateways, shipping integrations, marketing modules, and industry themes
- Optional multi-store or multi-channel architecture

Do not let Phase 8 block the first production release.

## 20. Definition of Done for Version 1.0

Version 1.0 is not complete until all of the following are true:

- A new user can install the platform using documented instructions.
- The owner can securely sign in and configure a store.
- Products, variants, categories, customers, and orders persist correctly.
- A customer can complete a real test-mode checkout.
- Payment webhook handling is verified and idempotent.
- Inventory is protected against concurrent overselling.
- Themes can be switched without breaking core commerce.
- A module can be installed, enabled, configured, disabled, and updated.
- Failed extension operations are recoverable or have a documented recovery path.
- Paid products can be purchased, licensed, downloaded securely, and updated.
- Unauthorized access is denied on the server.
- Backup and restore have been tested.
- Fresh installation, migration, and production build checks pass.
- Open-source license, third-party licenses, and commercial license boundaries are documented.
- No critical path relies on a placeholder, fake success state, or unimplemented button.

## 21. Execution Protocol for the Coding Agent

Follow this protocol throughout development:

1. Start by inspecting the repository and reporting the current state.
2. Present the plan for the next phase, including files/areas to change and acceptance criteria.
3. Implement only the next phase unless a prerequisite fix is essential.
4. Add migrations, models, validation, authorization, UI, tests, and documentation for each implemented feature.
5. Run the relevant tests, static checks, type checks, and build commands.
6. Fix regressions before continuing.
7. Summarize completed work, commands run, actual test results, known limitations, and next steps.
8. Keep `docs/roadmap.md` and a development progress log current.
9. If blocked by missing credentials or external services, build the real integration boundary and test with safe provider test modes; clearly mark what still requires configuration. Do not pretend the integration has been verified.
10. If the task is too large for one session, stop at a clean, tested checkpoint and provide exact continuation instructions. Do not attempt a rushed all-at-once implementation.

### Required progress report format

For each phase, report:

- **Status:** Not started / In progress / Complete / Blocked
- **Implemented:** Specific working features
- **Files and migrations:** Important additions/changes
- **Tests executed:** Exact commands and actual results
- **Security considerations:** Issues addressed and remaining risks
- **Known limitations:** Honest list
- **Next phase:** Scope and acceptance criteria

## 22. Final Instruction

Begin with **Phase 0: Discovery and technical plan**. Inspect the existing repository, verify Laravel 13 and dependency compatibility, document the architecture and decisions, then implement Phase 1 only after the plan is clear.

Build a maintainable open-source product—not a mockup. Prioritize correctness, security, clean extension contracts, testability, and a stable developer ecosystem over the number of features delivered in the first pass.
