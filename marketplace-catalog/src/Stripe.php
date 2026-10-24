<?php

/**
 * Stripe Checkout for premium catalog products.
 */
final class Stripe
{
    public function __construct(private readonly Store $store) {}

    public function configured(): bool
    {
        return str_starts_with($this->store->setting('stripe_secret'), 'sk_');
    }

    /**
     * @param  array<string, mixed>  $product
     */
    public function checkout(array $product, array $customer, string $success, string $cancel): string
    {
        $amount = (int) round(((float) $product['price']) * 100);
        if ($amount < 50) {
            throw new RuntimeException('The price is too small for Stripe.');
        }

        $body = [
            'mode' => 'payment',
            'success_url' => $success,
            'cancel_url' => $cancel,
            'customer_email' => $customer['email'],
            'client_reference_id' => (string) $customer['id'],
            'metadata[extension]' => $product['extension'],
            'metadata[customer_id]' => (string) $customer['id'],
            'metadata[product_name]' => $product['name'],
            'line_items[0][quantity]' => '1',
            'line_items[0][price_data][currency]' => strtolower($this->store->currency()),
            'line_items[0][price_data][unit_amount]' => (string) $amount,
            'line_items[0][price_data][product_data][name]' => $product['name'],
        ];

        $session = $this->request('POST', 'checkout/sessions', $body);
        if (! is_string($session['url'] ?? null)) {
            throw new RuntimeException('Stripe did not return a checkout page.');
        }

        return $session['url'];
    }

    /**
     * @return array<string, mixed>
     */
    public function session(string $id): array
    {
        if (! preg_match('/^cs_[A-Za-z0-9_]+$/', $id)) {
            throw new RuntimeException('That payment reference is not valid.');
        }

        return $this->request('GET', 'checkout/sessions/'.$id);
    }

    /**
     * @param  array<string, string>  $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $body = []): array
    {
        $handle = curl_init('https://api.stripe.com/v1/'.$path);
        if ($handle === false) {
            throw new RuntimeException('Stripe could not be contacted.');
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_USERPWD => $this->store->setting('stripe_secret').':',
            CURLOPT_TIMEOUT => 20,
        ]);
        if ($body !== []) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($body));
        }

        $raw = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);
        $json = is_string($raw) ? json_decode($raw, true) : null;
        if (! is_array($json)) {
            throw new RuntimeException('Stripe sent an unreadable answer.');
        }
        if ($status >= 400) {
            $message = is_array($json['error'] ?? null) ? (string) ($json['error']['message'] ?? 'Stripe refused the payment.') : 'Stripe refused the payment.';
            throw new RuntimeException($message);
        }

        return $json;
    }
}
