<?php

namespace App\Domains\Checkout;

use App\Domains\Checkout\Contracts\CheckoutValidator;
use App\Domains\Checkout\Pricing\PricedCart;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Registry of checkout validators contributed by modules.
 */
final class CheckoutValidators
{
    /** @var list<CheckoutValidator> */
    private array $validators = [];

    public function register(CheckoutValidator $validator): void
    {
        $this->validators[] = $validator;
    }

    /**
     * @throws ValidationException with the first refusal
     */
    public function assertValid(PricedCart $cart, CheckoutData $data): void
    {
        foreach ($this->validators as $validator) {
            try {
                $reason = $validator->validate($cart, $data);
            } catch (Throwable $e) {
                Log::error('A checkout validator failed; checkout was blocked.', ['validator' => $validator::class, 'exception' => $e]);
                $reason = 'We could not complete your order right now. Please try again later.';
            }

            if ($reason !== null) {
                throw ValidationException::withMessages(['cart' => $reason]);
            }
        }
    }
}
