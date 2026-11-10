<?php

namespace EcoModular\AiAssistant\Http;

use App\Core\Audit\AuditLogger;
use App\Core\Extensions\ModuleRepository;
use App\Core\Extensions\ModuleSettingsSchema;
use App\Core\Extensions\ModuleState;
use App\Core\Localization\Locales;
use App\Core\Settings\SecretSettings;
use App\Http\Controllers\Controller;
use EcoModular\AiAssistant\AiAssistantServiceProvider;
use EcoModular\AiAssistant\AiClient;
use EcoModular\AiAssistant\AiSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AiAssistantController extends Controller
{
    public function index(ModuleRepository $modules, ModuleSettingsSchema $settings, SecretSettings $secrets): Response
    {
        $manifest = $modules->find(AiAssistantServiceProvider::MODULE)?->manifest();
        abort_if($manifest === null, 404);
        $providers = array_keys(AiSettings::PROVIDERS);

        return Inertia::render('admin/ai/settings', [
            'values' => $settings->values($manifest),
            'updateUrl' => route('admin.modrixcommerce.ai-assistant.settings'),
            'testUrl' => route('admin.modrixcommerce.ai-assistant.test'),
            'modelsUrl' => route('admin.modrixcommerce.ai-assistant.models'),
            'keyHints' => array_combine($providers, array_map(fn (string $provider) => $secrets->hint(AiSettings::secretName($provider)), $providers)),
            'suggestions' => array_combine($providers, array_map(fn (string $provider) => $manifest->setting("{$provider}_model")['suggestions'] ?? [], $providers)),
        ]);
    }

    public function update(Request $request, ModuleRepository $modules, ModuleSettingsSchema $settings, SecretSettings $secrets, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'provider' => ['required', Rule::in(array_keys(AiSettings::PROVIDERS))],
            'openai_model' => ['required', 'string', 'max:200', 'regex:/^[a-zA-Z0-9][a-zA-Z0-9._:\/-]{0,199}$/'],
            'kimi_model' => ['required', 'string', 'max:200', 'regex:/^[a-zA-Z0-9][a-zA-Z0-9._:\/-]{0,199}$/'],
            'deepseek_model' => ['required', 'string', 'max:200', 'regex:/^[a-zA-Z0-9][a-zA-Z0-9._:\/-]{0,199}$/'],
            'api_key' => ['nullable', 'string', 'min:10', 'max:2048', 'regex:/^\S+$/'],
            'remove_api_key' => ['sometimes', 'boolean'],
        ]);
        $manifest = $modules->find(AiAssistantServiceProvider::MODULE)?->manifest();
        abort_if($manifest === null, 404);
        $settings->save($manifest, $request->except(['api_key', 'remove_api_key']));
        if (filled($data['api_key'] ?? null)) {
            $secrets->put(AiSettings::secretName($data['provider']), $data['api_key']);
        } elseif ($data['remove_api_key'] ?? false) {
            $secrets->put(AiSettings::secretName($data['provider']), null);
        }
        $audit->record('ai.settings_updated');
        Inertia::flash('toast', ['type' => 'success', 'message' => 'AI Assistant settings saved.']);

        return redirect()->route('admin.modrixcommerce.ai-assistant.index');
    }

    public function models(AiClient $client): JsonResponse
    {
        return response()->json(['models' => $client->models()]);
    }

    public function test(AiClient $client): JsonResponse
    {
        $client->test();

        return response()->json(['message' => 'Connected. Your API key can access the selected model. Text generation support depends on the model and API mode.']);
    }

    public function draft(Request $request, AiSettings $settings, AiClient $client, Locales $locales, AuditLogger $audit): JsonResponse
    {
        abort_unless(app(ModuleRepository::class)->find(AiAssistantServiceProvider::MODULE)?->state === ModuleState::Enabled, 404);
        if (! $settings->enabled()) {
            throw ValidationException::withMessages(['ai' => 'AI suggestions are switched off. Enable them in AI Assistant settings.']);
        }
        $data = $request->validate([
            'operation' => ['required', Rule::in(['generate', 'translate'])],
            'kind' => ['required', Rule::in(['product', 'category'])],
            'target_locale' => ['required', Rule::in($locales->enabled())],
            'fields' => ['required', 'array', 'min:1', 'max:5'],
            'fields.*' => ['required', 'string', 'distinct', Rule::in(['name', 'short_description', 'description', 'meta_title', 'meta_description'])],
            'source' => ['required', 'array:name,short_description,description,meta_title,meta_description'],
            'source.*' => ['nullable', 'string', 'max:50000'],
            'facts' => ['nullable', 'string', 'max:4000'],
            'tone' => ['nullable', Rule::in(['professional', 'friendly', 'concise'])],
        ]);
        $limits = $data['kind'] === 'product'
            ? ['name' => 191, 'short_description' => 500, 'description' => 50000, 'meta_title' => 191, 'meta_description' => 500]
            : ['name' => 160, 'description' => 20000, 'meta_title' => 191, 'meta_description' => 500];
        $allowed = $data['operation'] === 'generate' ? ['name', 'short_description', 'description'] : array_keys($limits);
        foreach ($data['fields'] as $field) {
            if (! in_array($field, $allowed, true)) {
                throw ValidationException::withMessages(['fields' => 'Select supported fields for this operation.']);
            }
        }
        if ($data['operation'] === 'translate' && ! in_array($data['target_locale'], $locales->additional(), true)) {
            throw ValidationException::withMessages(['target_locale' => 'Choose an additional storefront language for translation.']);
        }
        $source = [];
        foreach (array_keys($limits) as $field) {
            $source[$field] = (string) ($data['source'][$field] ?? '');
        }
        $fields = $data['operation'] === 'translate'
            ? array_values(array_filter($data['fields'], fn (string $field) => trim($source[$field] ?? '') !== ''))
            : $data['fields'];
        if ($fields === [] || (array_filter($source) === [] && blank($data['facts'] ?? null))) {
            throw ValidationException::withMessages(['ai' => 'Add a product name, existing content or product facts first.']);
        }
        $selectedLimits = array_intersect_key($limits, array_flip($fields));
        $draft = $client->draft($data['operation'], $data['kind'], Locales::name($data['target_locale']), $source, $selectedLimits,
            $data['operation'] === 'translate' ? '' : ($data['facts'] ?? ''), $data['tone'] ?? $settings->tone());
        $audit->record('ai.draft_generated', null, ['operation' => $data['operation'], 'kind' => $data['kind'], 'locale' => $data['target_locale'], 'fields' => $fields]);

        return response()->json(['draft' => $draft]);
    }
}
