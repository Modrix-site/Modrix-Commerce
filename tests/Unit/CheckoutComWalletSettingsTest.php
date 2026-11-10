<?php

namespace Tests\Unit;

use App\Core\Settings\SecretSettings;
use App\Core\Settings\SettingsRepository;
use EcoModular\CheckoutCom\CheckoutComSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutComWalletSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_switches_choose_which_methods_checkout_com_hides(): void
    {
        require_once base_path('modules/modrixcommerce/checkout-com/src/CheckoutComSettings.php');

        $settings = new CheckoutComSettings(app(SettingsRepository::class), app(SecretSettings::class));

        $this->assertSame(['applepay', 'googlepay'], $settings->disabledPaymentMethods());

        $settings->update(false, 'https://api.sandbox.checkout.com', 'hosted', '', '', true, false, null, null);

        $this->assertTrue($settings->applePay());
        $this->assertFalse($settings->googlePay());
        $this->assertSame(['googlepay'], $settings->disabledPaymentMethods());
    }
}
