<?php

namespace EcoModular\Newsletter;

use App\Core\Extensions\ModuleSettingsSchema;
use App\Core\Extensions\StorefrontSlots;
use App\Domains\Customers\PersonalDataExport;
use App\Models\User;
use EcoModular\Newsletter\Models\Subscriber;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Routes, permissions, navigation, settings and migrations come from
 * module.json; the provider wires the two sign-up slots.
 */
class NewsletterServiceProvider extends ServiceProvider
{
    public const MODULE = 'ecomodular/newsletter';

    public function boot(StorefrontSlots $slots, ModuleSettingsSchema $settings, PersonalDataExport $export): void
    {
        $export->contribute('newsletter', fn (User $user) => Subscriber::query()->where('email', Str::lower($user->email))->get()
            ->map(fn (Subscriber $s) => [
                'email' => $s->email,
                'consent_text' => $s->consent_text,
                'consented_at' => $s->consented_at->toIso8601String(),
                'unsubscribed_at' => $s->unsubscribed_at?->toIso8601String(),
            ])->all());

        $props = fn (string $source) => [
            'heading' => (string) $settings->of(self::MODULE, 'heading', 'Get our news and offers'),
            'consentText' => (string) $settings->of(self::MODULE, 'consent_text', ''),
            'submitUrl' => route('ecomodular.newsletter.subscribe'),
            'source' => $source,
        ];

        $slots->register('home.after_featured', self::MODULE, 'SignupForm', fn () => $props('home'));

        $slots->register('order.confirmation', self::MODULE, 'SignupForm', function (array $context) use ($props, $settings) {
            return (bool) $settings->of(self::MODULE, 'show_on_confirmation', true) ? $props('order') : null;
        });
    }
}
