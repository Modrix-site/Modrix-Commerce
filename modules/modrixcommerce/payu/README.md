# PayU Payment Gateway for Modrix Commerce

Accept payments in India using **PayU** (Credit / Debit Cards, UPI, Net Banking across all major banks, EMI, and Wallets).

## Features

- **Hosted Checkout Form**: Dynamic SHA-512 hashed form redirect to PayU's payment checkout (`/_payment`).
- **Cryptographic Security**: Outgoing SHA-512 request hashing and incoming reverse response hash verification (`sha512(SALT|status||||||...|key)`).
- **Server-to-Server Verification**: Authoritative transaction reconciliation using the PayU `verify_payment` command on `postservice.php`.
- **API Refunds**: Full and partial refunds executed directly through PayU's `cancel_refund_transaction` command.
- **Encrypted Credentials**: Merchant Salt is stored safely using `SecretSettings`, preventing exposure in frontend assets.

## Endpoints Used

- **Test Mode**: `https://test.payu.in/_payment`
- **Production Mode**: `https://secure.payu.in/_payment`
- **Web Service API (Test)**: `https://test.payu.in/merchant/postservice.php?form=2`
- **Web Service API (Production)**: `https://info.payu.in/merchant/postservice.php?form=2`
