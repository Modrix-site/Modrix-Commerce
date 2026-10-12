# Phase 3 Plan: Payment Integrations

Branch: `feature/phase-3-payments`. Each slice ships with migrations, validation, authorization, UI, tests and docs, and is committed once the quality gates pass.

## Design summary

- **Contract first.** `PaymentGateway` (in `app/Domains/Payments/Contracts`) extends the Phase 2 `PaymentMethod` with: create a payment session (redirect URL + provider reference + idempotency key), retrieve a session for reconciliation, verify and normalise a webhook, and refund. Checkout and orders depend only on the contract (architecture test).
- **Stripe adapter** in `app/Domains/Payments/Gateways/Stripe`, using Stripe Checkout (hosted payment page). Card numbers never reach the application (SAQ-A scope). It uses Laravel's HTTP client against the Stripe REST API instead of the SDK, so every call is fakeable in tests and there is no extra dependency (ADR-025).
- **Secrets** (secret key, webhook signing secret) are stored encrypted in settings with the application key, are write-only in the admin UI (only the last 4 characters are shown), and are never logged or sent to the browser (ADR-026).
- **Never paid by redirect.** The return page only shows "processing" unless the server itself retrieves the session from Stripe with the secret key and it reports `paid`. Webhooks and server-side retrieval both go through one idempotent `PaymentReconciler` → `OrderWorkflow::confirmGatewayPayment` path that checks amount and currency (ADR-027).
- **Webhooks.** `POST /webhooks/payments/{provider}`: CSRF-exempt, signature verified before anything is stored, event persisted in `payment_webhook_events` (unique `provider + event_id`), then processed by a queued job. Duplicates are acknowledged without effects; out-of-order and late events are handled explicitly.
- **Abandoned payments.** Stripe sessions expire after 30 minutes (configurable). On `checkout.session.expired`, or when the reconcile command finds an expired session, the payment is marked expired and the unpaid order is cancelled, which releases its stock. A payment that succeeds after the order was cancelled is recorded and flagged for refund instead of being lost.

## Slices

| #   | Slice                             | Key areas                                                                                                                                                                                                                                                                                                  |
| --- | --------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 3.1 | Contracts, storage and webhooks   | `PaymentGateway` contract and DTOs, gateway registry, encrypted secret settings, `payments` columns (`provider_payment_id`, `expires_at`, `failure_reason`), `payment_webhook_events` table, webhook endpoint with verification, dedupe and queued processing                                              |
| 3.2 | Stripe adapter and admin settings | Checkout Session creation with idempotency keys, minor-unit conversion (zero- and three-decimal currencies), signature verification with timestamp tolerance, event normalisation, session retrieval, refunds, provider error mapping; admin Stripe settings with write-only secrets and a connection test |
| 3.3 | Checkout payment flow             | Redirect to the hosted page after `PlaceOrder`, return and cancel pages, retry payment, `confirmGatewayPayment` / failure / expiry transitions, cancellation of abandoned orders, `ecomodular:payments:reconcile` command on the scheduler                                                                 |
| 3.4 | Gateway refunds                   | Admin refunds call the provider first (idempotency key), then record the refund; refund webhooks confirm or fail pending refunds; refunds made in the Stripe dashboard are imported                                                                                                                        |
| 3.5 | Audit trail and docs              | Payment events on the order timeline, admin webhook event log, provider setup guide (test mode, Stripe CLI forwarding), ADRs, roadmap and test results                                                                                                                                                     |

## Acceptance criteria

- A browser redirect alone never marks an order paid (tested by hitting the return URL with a forged session and with an unpaid session).
- Forged, unsigned, stale and wrong-secret webhooks are rejected before anything is stored (tested).
- Duplicate webhooks do not duplicate effects; out-of-order events (`expired` after `completed`, `completed` after cancellation) leave consistent state (tested).
- A payment whose amount or currency does not match the order is not applied and is flagged (tested).
- Secret keys never appear in Inertia props, logs or audit metadata (tested).
- Test-mode purchase end to end with a real Stripe test account. **This needs the merchant's Stripe test keys and cannot be completed without them.** Until then the flow is verified with signed fixture payloads and faked Stripe API responses, and the phase report states this.
