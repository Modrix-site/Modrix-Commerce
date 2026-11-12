<?php

namespace App\Domains\Customers;

use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CustomerProfiles
{
    /**
     * The customer profile of a user account, created on first use.
     */
    public function forUser(User $user): Customer
    {
        $customer = Customer::query()->where('user_id', $user->id)->first();

        if ($customer !== null) {
            return $customer;
        }

        [$first, $last] = $this->splitName($user->name);

        $customer = new Customer([
            'email' => Str::lower($user->email),
            'first_name' => $first,
            'last_name' => $last,
        ]);
        $customer->user_id = $user->id;
        $customer->save();

        return $customer;
    }

    /**
     * A guest customer record for checkout without an account.
     */
    public function guest(string $email, ?string $firstName, ?string $lastName, ?string $phone): Customer
    {
        return Customer::query()->create([
            'email' => Str::lower($email),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $phone,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data  validated address fields (+ default flags)
     */
    public function saveAddress(Customer $customer, array $data, ?CustomerAddress $address = null): CustomerAddress
    {
        return DB::transaction(function () use ($customer, $data, $address) {
            $address ??= new CustomerAddress;
            $address->customer_id = $customer->id;
            $address->fill(collect($data)->only([...CustomerAddress::FIELDS, 'is_default_shipping', 'is_default_billing'])->all());
            $address->country_code = Str::upper($address->country_code);

            $isFirst = ! $customer->addresses()->whereKeyNot($address->id ?? 0)->exists();

            if ($isFirst) {
                $address->is_default_shipping = true;
                $address->is_default_billing = true;
            }

            $address->save();

            foreach (['is_default_shipping', 'is_default_billing'] as $flag) {
                if ($address->{$flag}) {
                    $customer->addresses()->whereKeyNot($address->id)->update([$flag => false]);
                }
            }

            return $address;
        });
    }

    public function deleteAddress(CustomerAddress $address): void
    {
        DB::transaction(function () use ($address) {
            $customer = $address->customer;
            $address->delete();

            // Keep a default address when one remains.
            $next = $customer->addresses()->first();

            if ($next !== null) {
                foreach (['is_default_shipping', 'is_default_billing'] as $flag) {
                    if (! $customer->addresses()->where($flag, true)->exists()) {
                        $next->update([$flag => true]);
                    }
                }
            }
        });
    }

    /**
     * @return array{string|null, string|null}
     */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2) ?: [];

        return [$parts[0] ?? null, $parts[1] ?? null];
    }
}
