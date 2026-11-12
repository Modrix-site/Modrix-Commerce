# SMS Notifications & Phone Verification

`modrixcommerce/sms` asks new customers for a mobile number, confirms it with a texted one-time code, and texts order updates to customers who confirmed a number.

## Set up

1. **Admin → Extensions → SMS Notifications & Phone Verification**: install and enable.
2. **Admin → Marketing → SMS**: choose a provider, enter its credentials and save. Secrets are encrypted with the app key and never shown again.
3. Set the **default country calling code** (used when a customer types a number without `+`).
4. Send a test message from the same page.

Until the provider has every required credential, the badge shows **Setup required**, registration does **not** ask for a number, and no customer is held on the code page. A broken provider never locks customers out.

## Registration flow

1. The register page shows a **Mobile number** field and a "Text me updates about my orders" checkbox (the `auth.register` storefront slot).
2. The number is normalised to E.164. A number already confirmed on another account is refused.
3. After the account is created a 6-digit code is texted. Until it is entered, pages behind sign-in (account, orders, …) redirect to `/m/modrixcommerce/sms/verify`. Browsing the store and guest checkout stay open.
4. The customer can resend the code, correct the number, or log out. Email verification still applies separately.

Existing customers (registered before the module was enabled, or while it was not ready) are not held. They can add or change a number under **Account → Mobile & text updates**. A changed number replaces the old one only after its code is entered.

### Code security

| Rule | Value |
| --- | --- |
| Length | 6 digits, single use |
| Lifetime | configurable, 1–60 minutes (default 10) |
| Wrong guesses | 5, then the code is burned |
| Sending | 1 a minute and 5 an hour per account; 5 an hour per number |
| Sign-ups that text a code | 10 an hour per IP address |
| Storage | HMAC-SHA256 keyed with the app key; the message log keeps a redacted copy |

## Order updates

Texts go to the account that placed the order when its owner confirmed a number and kept order texts on. Each event is texted at most once per order.

| Event | On by default |
| --- | --- |
| Order placed | yes |
| Payment confirmed | no |
| Order shipped (skipped when staff ship without notifying) | yes |
| Order delivered | yes |
| Order cancelled | yes |
| Refund issued | no |

Templates are plain text with placeholders: `{store}`, `{name}`, `{order_number}`, `{total}`, `{order_url}`, `{tracking}` (" Tracking: …" or empty), `{tracking_number}`. The verification message must contain `{code}` and may use `{store}`, `{minutes}`, `{name}`. Placeholders are substituted, never evaluated.

Listeners are queued: keep a queue worker running in production.

## Providers

| Provider | Notes |
| --- | --- |
| Twilio | Account SID + auth token; From number or Messaging Service SID. |
| Vonage (Nexmo) | API key + secret; sender. |
| MSG91 | Sends through Flow templates (Indian DLT). Flows receive `message`, `code`, `order_number`, `store`, `name`, `total`. Separate template IDs for codes and order updates. |
| Fast2SMS | Indian numbers only. Quick SMS (free text) or DLT route (sender ID + message IDs; the value sent is the code or the order number). |
| Semaphore | Philippine numbers only. Optional approved sender name. |
| Amazon SNS | Access key + secret (only `sns:Publish` needed), region, optional sender ID. Signed with SigV4, no SDK. |
| Plivo | Auth ID + token; sender. |
| Custom HTTP gateway | Any HTTPS API: URL, method, JSON or form body template, headers, an encrypted `{api_key}`, optional success text and message-ID path. Placeholders: `{to}`, `{to_digits}`, `{message}`, `{code}`, `{order_number}`, `{sender}`, `{api_key}`; values are escaped for the URL, JSON or form body. Private, loopback and `localhost` hosts are refused. |
| Log only | Development: writes texts to the log. Refused in production. |

### Adding a provider from another module

Implement `ModrixCommerce\Sms\Contracts\SmsProvider` and register it from your provider's `boot()`, with `"requires": {"modrixcommerce/sms": "^1.0"}` in your manifest:

```php
public function boot(\ModrixCommerce\Sms\SmsProviders $providers): void
{
    $providers->register(new AcmeSmsProvider);
}
```

`fields()` describes the admin form (`string`, `secret`, `select`, `text`); `send()` receives the values with secrets decrypted and returns `SmsResult::sent($id)` or `SmsResult::failed($reason)`. Don't throw for gateway errors, and never include credentials in error text.

## Data

| Table | Holds |
| --- | --- |
| `sms_phones` | one number per account, confirmation time, pending new number, order-texts preference |
| `sms_verification_codes` | hashed codes, attempts, expiry |
| `sms_messages` | every text sent or attempted (codes redacted), with provider status and error |

The customer's number and preference are included in their personal data export. Uninstalling the module deletes these tables.

## Platform change

The module needed one new extension point: the `auth.register` storefront slot, rendered inside the registration form. Inputs a slot component renders are submitted with the form; a module validates them by decorating `Laravel\Fortify\Contracts\CreatesNewUsers`.
