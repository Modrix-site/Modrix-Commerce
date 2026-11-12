<?php

namespace ModrixCommerce\Sms;

use App\Core\Extensions\StorefrontSlots;
use App\Core\Storefront\AccountMenu;
use App\Domains\Customers\PersonalDataExport;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use ModrixCommerce\Sms\Http\RequireVerifiedPhone;
use ModrixCommerce\Sms\Listeners\SendOrderUpdate;
use ModrixCommerce\Sms\Models\SmsPhone;
use ModrixCommerce\Sms\Registration\RequirePhoneOnRegistration;
use ModrixCommerce\Sms\Registration\SendCodeAfterRegistration;

/**
 * Mobile number + one-time code at registration, and order updates by text.
 * Other modules can add gateways: app(SmsProviders::class)->register(new MyProvider).
 */
class SmsServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/sms';

    public function register(): void
    {
        $this->app->singleton(SmsProviders::class);
        $this->app->singleton(SmsSettings::class);
    }

    public function boot(StorefrontSlots $slots, AccountMenu $menu, PersonalDataExport $export): void
    {
        // Registration also asks for the mobile number while SMS is ready
        // (Fortify binds CreatesNewUsers while booting, before modules boot).
        $this->app->extend(CreatesNewUsers::class, fn (CreatesNewUsers $inner, $app) => new RequirePhoneOnRegistration($inner, $app->make(SmsSettings::class)));

        // Through the HTTP kernel: it re-syncs groups to the router when it is
        // first built, which would drop middleware pushed onto the router alone.
        $this->app->make(HttpKernel::class)->appendMiddlewareToGroup('web', RequireVerifiedPhone::class);

        Event::listen(Registered::class, SendCodeAfterRegistration::class);

        foreach (array_keys(SendOrderUpdate::EVENTS) as $event) {
            Event::listen($event, SendOrderUpdate::class);
        }

        $menu->add('sms', 'Mobile & text updates', 'modrixcommerce.sms.account', 'notifications', 20);

        $slots->register('auth.register', self::MODULE, 'RegisterPhone', function () {
            $settings = $this->app->make(SmsSettings::class);

            return $settings->requiredAtRegistration()
                ? ['defaultCountryCode' => (string) $settings->value('default_country_code')]
                : null;
        });

        $export->contribute('sms', function (User $user) {
            $phone = SmsPhone::query()->where('user_id', $user->id)->first();

            return $phone === null ? [] : [
                'phone' => $phone->phone,
                'verified_at' => $phone->verified_at?->toIso8601String(),
                'pending_phone' => $phone->pending_phone,
                'order_updates' => $phone->order_updates,
            ];
        });
    }
}
