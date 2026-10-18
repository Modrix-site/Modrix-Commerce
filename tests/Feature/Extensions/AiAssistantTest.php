<?php

namespace Tests\Feature\Extensions;

use App\Core\Extensions\ModuleManager;
use App\Core\Extensions\ModuleRepository;
use App\Core\Extensions\ModuleSettingsSchema;
use App\Core\Localization\Locales;
use App\Core\Settings\SecretSettings;
use App\Domains\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiAssistantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inertia.ssr.enabled' => false]);
        $this->installPlatform();
        ModuleRepository::flushBootCache();
        app(ModuleRepository::class)->sync();
        app(ModuleManager::class)->install('ecomodular/ai-assistant');
        app(ModuleManager::class)->enable('ecomodular/ai-assistant', true);
        app(SecretSettings::class)->put('ai-assistant.openai', 'sk-test-openai-secret');
        app(SecretSettings::class)->put('ai-assistant.kimi', 'sk-test-kimi-secret');
        Http::preventStrayRequests();
    }

    private function payload(): array
    {
        return ['operation' => 'generate', 'kind' => 'product', 'target_locale' => 'en', 'fields' => ['name', 'description'],
            'source' => ['name' => 'Cotton shirt'], 'facts' => 'Blue, 100% cotton'];
    }

    private function values(array $changes = []): array
    {
        $manifest = app(ModuleRepository::class)->find('ecomodular/ai-assistant')->manifest();

        return array_replace(app(ModuleSettingsSchema::class)->values($manifest), $changes);
    }

    private function settings(array $changes): void
    {
        $manifest = app(ModuleRepository::class)->find('ecomodular/ai-assistant')->manifest();
        app(ModuleSettingsSchema::class)->save($manifest, $this->values($changes));
    }

    private function responseOutput(string $text): array
    {
        return ['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => $text]]]]];
    }

    public function test_openai_responses_generate_drafts_without_saving_products(): void
    {
        $product = Product::factory()->create(['name' => 'Original name']);
        Http::fake(['api.openai.com/v1/responses' => Http::response($this->responseOutput('{"name":"Blue cotton shirt","description":"<p>Soft cotton in blue.</p>","extra":"ignored"}'))]);
        $this->actingAs($this->owner())->postJson(route('admin.ecomodular.ai-assistant.draft'), $this->payload())
            ->assertOk()->assertExactJson(['draft' => ['name' => 'Blue cotton shirt', 'description' => 'Soft cotton in blue.']]);
        $this->assertSame('Original name', $product->fresh()->name);
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer sk-test-openai-secret') && $request['store'] === false
            && $request['max_output_tokens'] === 8192 && $request['text']['format']['type'] === 'json_object');
    }

    public function test_kimi_uses_its_own_key_region_and_configured_version(): void
    {
        $this->settings(['provider' => 'kimi', 'kimi_region' => 'china', 'kimi_model' => 'kimi-version-snapshot', 'kimi_api' => 'chat']);
        Http::fake(['api.moonshot.cn/v1/chat/completions' => Http::response(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => '{"name":"Shirt","description":"Cotton shirt."}']]]])]);
        $this->actingAs($this->owner())->postJson(route('admin.ecomodular.ai-assistant.draft'), $this->payload())->assertOk();
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer sk-test-kimi-secret') && $request['model'] === 'kimi-version-snapshot'
            && $request['max_completion_tokens'] === 8192 && ! isset($request['temperature']));
    }

    public function test_deepseek_uses_its_own_key_endpoint_and_model(): void
    {
        app(SecretSettings::class)->put('ai-assistant.deepseek', 'sk-test-deepseek-secret');
        $this->settings(['provider' => 'deepseek', 'deepseek_model' => 'deepseek-v4-pro']);
        Http::fake(['api.deepseek.com/chat/completions' => Http::response(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => '{"name":"Shirt","description":"Cotton shirt."}']]]])]);
        $this->actingAs($this->owner())->postJson(route('admin.ecomodular.ai-assistant.draft'), $this->payload())->assertOk()->assertJsonPath('draft.name', 'Shirt');
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.deepseek.com/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer sk-test-deepseek-secret') && $request['model'] === 'deepseek-v4-pro'
            && $request['max_tokens'] === 8192 && $request['response_format']['type'] === 'json_object');
    }

    public function test_settings_page_offers_each_providers_suggested_models(): void
    {
        $this->actingAs($this->owner())->get(route('admin.ecomodular.ai-assistant.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('admin/ai/settings')
                ->where('values.deepseek_model', 'deepseek-flash')
                ->where('suggestions.deepseek', ['deepseek-flash', 'deepseek-v4-pro'])
                ->where('suggestions.kimi.0', 'kimi-k3')
                ->where('suggestions.openai.0', 'gpt-6-luna')
                ->has('keyHints.deepseek'));
        $this->put(route('admin.ecomodular.ai-assistant.settings'), $this->values(['provider' => 'deepseek', 'deepseek_model' => 'deepseek-custom-0901', 'api_key' => 'sk-new-deepseek-key']))
            ->assertSessionHasNoErrors();
        $this->assertSame('sk-new-deepseek-key', app(SecretSettings::class)->get('ai-assistant.deepseek'));
        $this->assertSame('deepseek-custom-0901', $this->values()['deepseek_model']);
    }

    public function test_legacy_chat_and_plain_json_work_without_model_allowlist(): void
    {
        $this->settings(['openai_model' => 'ft:custom:model-snapshot', 'openai_api' => 'legacy_chat', 'json_mode' => false]);
        Http::fake(['api.openai.com/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => "```json\n{\"name\":\"Shirt\",\"description\":\"Cotton.\"}\n```"]]]])]);
        $this->actingAs($this->owner())->postJson(route('admin.ecomodular.ai-assistant.draft'), $this->payload())->assertOk()->assertJsonPath('draft.name', 'Shirt');
        Http::assertSent(fn (Request $request) => $request['max_tokens'] === 8192 && ! isset($request['response_format']) && $request['model'] === 'ft:custom:model-snapshot');
    }

    public function test_translations_skip_empty_fields_and_do_not_save_automatically(): void
    {
        app(Locales::class)->update('en', ['fr']);
        Http::fake(['*' => Http::response($this->responseOutput('{"name":"Chemise en coton"}'))]);
        $this->actingAs($this->owner())->postJson(route('admin.ecomodular.ai-assistant.draft'), array_replace($this->payload(), [
            'operation' => 'translate', 'target_locale' => 'fr', 'source' => ['name' => 'Cotton shirt', 'description' => null],
        ]))->assertOk()->assertJsonPath('draft.name', 'Chemise en coton')->assertJsonMissingPath('draft.description');
        $this->assertDatabaseCount('product_translations', 0);
        Http::assertSent(fn (Request $request) => str_contains($request['instructions'], 'Français'));
        $this->postJson(route('admin.ecomodular.ai-assistant.draft'), array_replace($this->payload(), ['target_locale' => 'de']))->assertUnprocessable();
        Http::assertSentCount(1);
    }

    public function test_invalid_or_incomplete_provider_output_is_rejected(): void
    {
        Http::fake(['*' => Http::sequence()->push($this->responseOutput('not JSON'))
            ->push($this->responseOutput(json_encode(['name' => str_repeat('a', 192), 'description' => 'Text'])))
            ->push(['status' => 'incomplete', 'output' => []])]);
        $this->actingAs($this->owner());
        foreach ([1, 2, 3] as $attempt) {
            $this->postJson(route('admin.ecomodular.ai-assistant.draft'), $this->payload())->assertUnprocessable()->assertJsonValidationErrors('ai');
        }
    }

    public function test_keys_are_encrypted_preserved_separately_and_not_returned(): void
    {
        $this->actingAs($this->owner())->put(route('admin.ecomodular.ai-assistant.settings'), $this->values(['provider' => 'kimi']) + ['api_key' => 'sk-new-kimi-key-value'])
            ->assertSessionHasNoErrors();
        $this->assertSame('sk-new-kimi-key-value', app(SecretSettings::class)->get('ai-assistant.kimi'));
        $this->assertSame('sk-test-openai-secret', app(SecretSettings::class)->get('ai-assistant.openai'));
        $stored = DB::table('settings')->where('key', 'secrets.ai-assistant.kimi')->value('value');
        $this->assertStringNotContainsString('sk-new-kimi-key-value', $stored);
        $this->get(route('admin.ecomodular.ai-assistant.index'))->assertOk()->assertDontSee('sk-new-kimi-key-value')->assertDontSee('sk-test-openai-secret');
        $this->put(route('admin.ecomodular.ai-assistant.settings'), $this->values() + ['api_key' => ''])->assertSessionHasNoErrors();
        $this->assertSame('sk-test-openai-secret', app(SecretSettings::class)->get('ai-assistant.openai'));
        $this->put(route('admin.ecomodular.ai-assistant.settings'), array_replace($this->values(['provider' => 'openai']), ['remove_api_key' => true]))->assertSessionHasNoErrors();
        $this->assertNull(app(SecretSettings::class)->get('ai-assistant.openai'));
        $this->assertSame('sk-new-kimi-key-value', app(SecretSettings::class)->get('ai-assistant.kimi'));
    }

    public function test_keys_are_not_flashed_after_validation_failure(): void
    {
        $this->actingAs($this->owner())->put(route('admin.ecomodular.ai-assistant.settings'), $this->values(['openai_model' => 'bad model']) + ['api_key' => 'sk-test-do-not-flash'])
            ->assertSessionHasErrors('openai_model')->assertSessionMissing('_old_input.api_key');
    }

    public function test_models_and_connection_test_use_read_only_model_listing(): void
    {
        Http::fake(['api.openai.com/v1/models' => Http::response(['data' => [['id' => 'gpt-6-luna'], ['id' => 'custom-text-model']]])]);
        $this->actingAs($this->owner())->postJson(route('admin.ecomodular.ai-assistant.models'))->assertOk()->assertJsonCount(2, 'models');
        $this->postJson(route('admin.ecomodular.ai-assistant.test'))->assertOk();
        Http::assertSent(fn (Request $request) => $request->method() === 'GET');
        Http::assertSentCount(2);
    }

    public function test_missing_keys_and_disabled_extension_do_not_send_requests(): void
    {
        Http::fake();
        $this->actingAs($this->userWithRole('analyst'))->postJson(route('admin.ecomodular.ai-assistant.draft'), $this->payload())->assertForbidden();
        app(SecretSettings::class)->put('ai-assistant.openai', null);
        $this->actingAs($this->owner())->postJson(route('admin.ecomodular.ai-assistant.draft'), $this->payload())->assertUnprocessable();
        $this->settings(['enabled' => false]);
        $this->postJson(route('admin.ecomodular.ai-assistant.draft'), $this->payload())->assertUnprocessable();
        app(ModuleManager::class)->disable('ecomodular/ai-assistant');
        $this->postJson(route('admin.ecomodular.ai-assistant.draft'), $this->payload())->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_provider_errors_never_expose_response_bodies(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'sk-sensitive-upstream']], 401)]);
        $this->actingAs($this->owner())->postJson(route('admin.ecomodular.ai-assistant.test'))->assertUnprocessable()->assertJsonValidationErrors('ai')->assertDontSee('sk-sensitive-upstream');
    }
}
