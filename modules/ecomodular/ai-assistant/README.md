# AI Content Assistant

An optional MIT-licensed extension for product titles, descriptions and catalog translation using **OpenAI**, **Kimi (Moonshot)** or **DeepSeek**. Core catalog editing and manual translation work independently of this extension.

## Configure

Open **Configuration → AI Assistant**, select a provider and enter its API key. The model field suggests current models for the selected provider (OpenAI `gpt-6-luna` by default, Kimi `kimi-k3`, DeepSeek `deepseek-flash`); choose **Custom model ID…** to enter any other ID. Save, then **Load available models** to add your account's models to the list. In **Extensions → AI Content Assistant**, only the selected provider's fields are shown. Each provider retains its own encrypted key, selected model and API mode. A blank key preserves the existing key; the removal checkbox clears only the selected provider's key. Only a masked hint is sent to the browser. The extension is free; provider API usage is billed according to your account plan.

OpenAI uses `https://api.openai.com/v1`; Kimi supports the international `https://api.moonshot.ai/v1` and China `https://api.moonshot.cn/v1` endpoints; DeepSeek uses `https://api.deepseek.com`. Credentials are sent only to these fixed HTTPS endpoints and redirects are disabled.

## Model versions

Suggested models are a convenience, not an allowlist. Enter any currently available text model, version snapshot or supported fine-tuned model ID accessible to your account. Retired, inaccessible and non-text models cannot be made usable by this extension.

- **Responses API**: OpenAI's default; uses `max_output_tokens`, disables response storage and reads message output text.
- **Chat Completions**: any provider; uses `max_completion_tokens`.
- **Legacy Chat Completions**: any provider; uses `max_tokens`. The default for Kimi and DeepSeek.
- **Request JSON output**: turn off if the selected model does not support JSON mode. All returned fields are still parsed and validated before applying.

The connection test checks model listing and access without generating billable text. It does not guarantee that a model supports the selected API mode. No automatic retries, provider fallback or automatic model changes are performed.

## Use

- Product **General → Write with AI**: choose fields, add known facts, generate, review and edit, then apply the draft. Save the product separately.
- Product/category **Translations → Translate with AI**: translate into the selected enabled storefront language, review and apply, then save the translation. Empty source fields are skipped.

Requests include only the selected editor content, supplied facts, desired fields and language. Customer data and uploaded images are not included. Audits contain operation metadata, not prompts, generated text or API keys. Settings require settings management permission; generation requires product update permission and is rate limited. Responses are validated against field limits and inserted as plain text. Incomplete or malformed output is rejected.

API references: [OpenAI text generation](https://developers.openai.com/api/docs/guides/text), [OpenAI models](https://developers.openai.com/api/reference/resources/models/methods/list), [Kimi Chat Completions](https://platform.moonshot.ai/docs/api/chat), [DeepSeek models](https://api-docs.deepseek.com/quick_start/pricing).
