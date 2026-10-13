<?php

namespace Tests\Feature\Storefront;

use App\Core\Localization\InterfaceTranslations;
use App\Core\Localization\Locales;
use App\Core\Localization\StorefrontStrings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

class InterfaceTranslationTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        app(Locales::class)->update('en', ['es', 'fr', 'de', 'it']);
    }

    public function test_the_source_catalogue_lists_every_storefront_text_in_the_code()
    {
        $this->assertSame(
            app(StorefrontStrings::class)->extract(),
            app(InterfaceTranslations::class)->keys(),
            'lang/storefront/en.json is out of date: run php artisan ecomodular:storefront-strings.',
        );
    }

    public function test_shipped_translations_are_complete_and_keep_placeholders()
    {
        $keys = app(InterfaceTranslations::class)->keys();

        foreach (['es', 'fr', 'de'] as $locale) {
            $shipped = app(InterfaceTranslations::class)->shipped($locale);
            $this->assertSame([], array_values(array_diff($keys, array_keys($shipped))), "{$locale} is missing texts");
            $this->assertSame([], array_values(array_diff(array_keys($shipped), $keys)), "{$locale} has unknown texts");

            foreach ($shipped as $key => $value) {
                preg_match_all('/:[a-z]+/', $key, $a);
                preg_match_all('/:[a-z]+/', $value, $b);
                sort($a[0]);
                sort($b[0]);
                $this->assertSame($a[0], $b[0], "{$locale}: placeholders differ for \"{$key}\"");
            }
        }
    }

    public function test_shoppers_get_the_wording_in_their_language_and_admin_stays_english()
    {
        $this->get('/?lang=es')->assertInertia(fn (Assert $page) => $page
            ->where('i18n.locale', 'es')
            ->where('i18n.messages.Add to cart', 'Añadir al carrito'));

        $this->get('/?lang=fr')->assertInertia(fn (Assert $page) => $page->where('i18n.messages.Cart', 'Panier'));

        // English needs no messages (the keys are English).
        $this->get('/?lang=en')->assertInertia(fn (Assert $page) => $page->where('i18n.locale', 'en')->where('i18n.messages', []));

        // Admin pages are English and carry no storefront messages.
        $this->withCookie('storefront_locale', 'es')->actingAs($this->owner())->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('i18n.locale', 'en')->where('i18n.messages', []));

        // Sign-in pages follow the shopper's language too.
        $this->post('/logout');
        $this->withCookie('storefront_locale', 'de')->get(route('login'))
            ->assertInertia(fn (Assert $page) => $page->where('i18n.messages.Log in', 'Anmelden'));
    }

    public function test_server_messages_and_validation_are_translated()
    {
        Mail::fake();
        $variant = $this->sellable();

        $this->withCookie('storefront_locale', 'es')
            ->post(route('cart.items.store'), ['variant_id' => $variant->id, 'quantity' => 1])
            ->assertInertiaFlash('toast.message', 'Añadido a tu carrito.');

        $this->withCookie('storefront_locale', 'es')->get(route('storefront.search', ['q' => 'zzz']))
            ->assertInertia(fn (Assert $page) => $page->where('title', 'Resultados de búsqueda para «zzz»'));

        $this->withCookie('storefront_locale', 'es')->post(route('cart.coupon.apply'), ['code' => 'NOPE'])
            ->assertSessionHasErrors(['code' => 'Este código de cupón no es válido.']);

        // Laravel validation messages, with localized field names.
        $this->enableCheckout();
        $this->withCookie('storefront_locale', 'es')->post(route('checkout.store'), [])
            ->assertSessionHasErrors(['email' => 'El campo correo electrónico es obligatorio.']);

        // Country names follow the language.
        $this->withCookie('storefront_locale', 'de')->get(route('checkout.show'))
            ->assertInertia(fn (Assert $page) => $page->where('countries', fn ($countries) => collect($countries)->contains(fn ($c) => $c['code'] === 'ES' && $c['name'] === 'Spanien')));
    }

    public function test_failed_sign_in_messages_are_translated()
    {
        User::factory()->create(['email' => 'ana@example.com']);

        $this->withCookie('storefront_locale', 'fr')->post(route('login.store'), ['email' => 'ana@example.com', 'password' => 'wrong'])
            ->assertSessionHasErrors(['email' => 'Ces identifiants ne correspondent à aucun compte.']);
    }

    public function test_merchants_override_and_complete_texts()
    {
        $owner = $this->owner();

        $this->actingAs($owner)->get(route('admin.settings.interface-text.index', ['locale' => 'es', 'search' => 'Add to cart']))
            ->assertInertia(fn (Assert $page) => $page->where('rows.data.0.key', 'Add to cart')->where('rows.data.0.shipped', 'Añadir al carrito'));

        // Italian is not shipped: everything is still to do.
        $this->get(route('admin.settings.interface-text.index', ['locale' => 'it', 'filter' => 'missing']))
            ->assertInertia(fn (Assert $page) => $page->where('counts.translated', 0)->where('rows.total', count(app(InterfaceTranslations::class)->keys())));

        $this->put(route('admin.settings.interface-text.update', 'es'), ['texts' => [
            ['key' => 'Add to cart', 'value' => 'Lo quiero'],
            ['key' => 'Not a storefront text', 'value' => 'ignored'],
        ]])->assertRedirect();
        $this->put(route('admin.settings.interface-text.update', 'it'), ['texts' => [['key' => 'Add to cart', 'value' => 'Aggiungi al carrello']]])->assertRedirect();
        $this->assertDatabaseMissing('interface_translations', ['key' => 'Not a storefront text']);

        auth()->logout();
        $this->get('/?lang=es')->assertInertia(fn (Assert $page) => $page->where('i18n.messages.Add to cart', 'Lo quiero'));
        $this->get('/?lang=it')->assertInertia(fn (Assert $page) => $page->where('i18n.messages.Add to cart', 'Aggiungi al carrello'));

        // Clearing the field brings the shipped translation back.
        $this->actingAs($owner)->put(route('admin.settings.interface-text.update', 'es'), ['texts' => [['key' => 'Add to cart', 'value' => '']]]);
        auth()->logout();
        $this->get('/?lang=es')->assertInertia(fn (Assert $page) => $page->where('i18n.messages.Add to cart', 'Añadir al carrito'));

        // English is the source language; only settings managers edit texts.
        $this->actingAs($owner)->put(route('admin.settings.interface-text.update', 'en'), ['texts' => [['key' => 'Cart', 'value' => 'Bag']]])->assertNotFound();
        $this->actingAs($this->userWithRole('catalog-manager'))->get(route('admin.settings.interface-text.index'))->assertForbidden();
    }
}
