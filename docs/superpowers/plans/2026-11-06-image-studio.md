# Image Studio Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A `modrixcommerce/image-studio` module that turns product images into transparent-background WebP variants and serves them on the storefront, keeping originals for revert.

**Architecture:** A core registry (`ProductImageUrls`) lets a module supply a URL for a `ProductImage`; `ProductImage::url()` consults it first. The module stores one variant row per product image, produced by a queued job running `ImageProcessor` (a `BackgroundRemover` driver, then GD WebP encoding). Triggers: `ProductImage::created`, admin bulk actions, artisan command.

**Tech Stack:** Laravel 13, PHP 8.4 GD (WebP), Inertia React admin page, PHPUnit on SQLite.

**Spec:** `docs/superpowers/specs/2026-11-06-image-studio-design.md`

## Global Constraints

- PHP binary: `C:\php84\php.exe`; run tests with `C:\php84\php.exe artisan test --filter=...`.
- GD only (no Imagick). No image processing inside a web request: jobs or CLI only.
- Module name `modrixcommerce/image-studio`, namespace `EcoModular\ImageStudio\`, provider `EcoModular\ImageStudio\ImageStudioServiceProvider`, `MODULE` const on the provider.
- Route names: `admin.modrixcommerce.image-studio.*`; Inertia page `module/modrixcommerce/image-studio/admin/index`.
- Permission `image_studio.manage` (label "Manage product image processing", group "Catalog").
- Variant path: `products/{product_id}/studio/{Str::random(40)}.webp` on the original's disk, `visibility => public`.
- Statuses: `pending`, `processing`, `done`, `failed`, `skipped`. Drivers: `gd-edge`, `removebg`, `none`.
- Settings defaults: enabled=true, process_on_upload=true, remove_background=true, driver=`gd-edge`, tolerance=18 (1–100), feather_px=1 (0–4), webp_quality=82 (40–100).
- Downscale before processing when a side exceeds 2400px. Reject > `ImageSanitizer::MAX_PIXELS`.
- Edge uniformity threshold 85% of border samples; skip when removed share < 3% or > 97%.
- remove.bg: `POST https://api.remove.bg/v1.0/removebg`, header `X-Api-Key`, fields `size=auto`, `format=png`, 60s timeout. Secret key `image_studio.removebg_api_key` via `App\Core\Settings\SecretSettings`; never returned to the browser or written to error messages/logs.
- Feature tests use `ModuleFixtures` + `installPlatform()` + `File::copyDirectory(base_path('modules'), $this->modulesPath)` then `install`/`enable`, and `config(['inertia.ssr.enabled' => false])`. Run `npm run build` once before feature tests that render Inertia pages.
- Do not commit unless the user asks (shared dirty branch). "Commit" steps below become "stage nothing; note files changed".

## Review Focus

1. **Image where the product touches the frame edge** (cropped jeans legs, a strap running off-canvas) — the edge-connected fill must only take pixels close to the background colour, so the product part on the edge stays opaque. Test: `test_product_touching_edge_keeps_its_pixels` (Task 3).
2. **PNG originals that already have transparency** — the processor must not flatten existing alpha to black. Test: `test_transparent_png_keeps_alpha` (Task 4).
3. **Module disabled after processing** — storefront must show originals with no stale resolver from a scoped map. Test: `test_disabled_module_serves_originals` (Task 5).
4. **Product image deleted while a variant exists** — the variant file is removed, no orphan on disk. Test: `test_deleting_product_image_removes_variant_file` (Task 5).
5. **remove.bg failure text leaking the key** — failed row's `error` must never contain the API key. Assertion in `test_removebg_402_marks_failed_without_leaking_key` (Task 6).

---

### Task 1: Core image URL seam

**Files:**

- Create: `app/Core/Media/ProductImageUrls.php`
- Modify: `app/Domains/Catalog/Models/ProductImage.php` (`url()`), `app/Providers/AppServiceProvider.php` (singleton), `app/Core/Extensions/Manifest.php` (`CAPABILITIES`), `docs/extensions/module.schema.json` (capability enum), `docs/extensions/module-development.md` (one short section)
- Test: `tests/Feature/Media/ProductImageUrlsTest.php`

**Interfaces:**

- Produces: `final class App\Core\Media\ProductImageUrls` with `register(string $module, Closure $resolver): void` (`Closure(ProductImage): ?string`), `forget(string $module): void`, `resolve(ProductImage $image): ?string` (first non-null in registration order). Bound as a `singleton`.
- Produces: capability `'catalog.image_urls' => 'Replaces product image files on the storefront'`.

- [ ] **Step 1: Write failing tests**
    - `test_url_falls_back_to_disk_url_without_resolvers`: `Storage::fake('public')`, product image path `products/1/a.jpg` → `url()` equals `Storage::disk('public')->url('products/1/a.jpg')`.
    - `test_registered_resolver_overrides_url`: register `'acme/x'` returning `'https://cdn.test/x.webp'` → `url()` returns it.
    - `test_null_resolver_falls_through_and_forget_removes`: resolver returning null → disk URL; after `forget('acme/x')` with an overriding resolver → disk URL.
    - `test_manifest_accepts_catalog_image_urls_capability`: `array_key_exists('catalog.image_urls', Manifest::CAPABILITIES)`.
- [ ] **Step 2: Run** `C:\php84\php.exe artisan test --filter=ProductImageUrlsTest` → FAIL (class not found).
- [ ] **Step 3: Implement** the registry (array keyed by module), bind singleton, change `url()` to `app(ProductImageUrls::class)->resolve($this) ?? Storage::disk($this->disk)->url($this->path)`, add the capability to `Manifest::CAPABILITIES` and the schema enum, and a 5-line doc section describing the seam.
- [ ] **Step 4: Run** the filter → PASS; then `--filter=Catalog` to confirm no regressions.
- [ ] **Step 5: Note changed files** (no commit).

### Task 2: Module skeleton, variant table, settings

**Files:**

- Create: `modules/modrixcommerce/image-studio/module.json`, `src/ImageStudioServiceProvider.php`, `src/Models/ImageVariant.php`, `src/Support/ImageStudioSettings.php`, `database/migrations/2026_11_06_000001_create_image_studio_variants_table.php`, `routes/admin.php` (empty group for now)
- Create: `tests/Support/ImageStudioFixtures.php`
- Test: `tests/Feature/Extensions/ImageStudio/ImageStudioModuleTest.php`

**Interfaces:**

- Produces: `ImageVariant` model (`image_studio_variants`), constants `ImageVariant::PENDING|PROCESSING|DONE|FAILED|SKIPPED`, `belongsTo(ProductImage::class)` as `image()`, `url(): ?string` (disk URL of `path`). Columns per spec §2; `product_image_id` unique, FK cascade.
- Produces: `ImageStudioSettings` (constructor-injected `ModuleSettingsSchema`, `SecretSettings`) with `enabled(): bool`, `processOnUpload(): bool`, `removeBackground(): bool`, `driver(): string`, `tolerance(): int`, `featherPx(): int`, `webpQuality(): int`, `removeBgKey(): ?string`, `setRemoveBgKey(?string): void`, `hasRemoveBgKey(): bool`. Values clamped to the Global Constraints ranges.
- Produces: trait `Tests\Support\ImageStudioFixtures` with `bootImageStudio(): void` (the install/enable sequence from Global Constraints for `modrixcommerce/image-studio`, plus `Storage::fake('public')`), `studioImage(string $format = 'jpg', int $w = 200, int $h = 200, ?Closure $draw = null): ProductImage` (creates a product via `CommerceFixtures::sellable()`, draws `#F4F2EF` background and by default a `#2B4C7E` filled 100×100 square in the centre, stores it under `products/{id}/...`, creates the `ProductImage` row **with `ProductImage::withoutEvents`** so the upload trigger does not fire), and `pixelAlpha(string $bytes, int $x, int $y): int`.
- module.json capabilities: `admin.routes`, `admin.navigation`, `database.migrations`, `settings`, `permissions`, `events.listen`, `console.commands`, `catalog.image_urls`. Settings per Global Constraints (`tolerance`/`feather_px` with `visible_when: {driver: "gd-edge"}`). Admin nav: key `image-studio`, title "Image Studio", route `index`, icon `image`, group `Catalog`, permission `image_studio.manage`, order 230. Icon `image`, accent `#0ea5e9`, version `1.0.0`, license MIT, author "Modrix Commerce".

- [ ] **Step 1: Write failing tests**: `test_module_installs_and_enables` (extensions row `state=enabled`, table `image_studio_variants` exists); `test_settings_defaults_and_clamping` (defaults equal Global Constraints; storing tolerance 500 through `ModuleSettingsSchema::save` is rejected or clamped to 100).
- [ ] **Step 2: Run** `--filter=ImageStudioModuleTest` → FAIL (module not found).
- [ ] **Step 3: Implement** the files above; `C:\php84\php.exe artisan ecomodular:modules discover` then `ecomodular:modules list` shows the module with no validation error.
- [ ] **Step 4: Run** → PASS.
- [ ] **Step 5: Note changed files.**

### Task 3: Background removers — contract and GD edge driver

**Files:**

- Create: `src/Contracts/BackgroundRemover.php`, `src/Exceptions/SkipImage.php`, `src/Removers/GdEdgeRemover.php`
- Test: `tests/Feature/Extensions/ImageStudio/GdEdgeRemoverTest.php`

**Interfaces:**

- Produces: `interface BackgroundRemover { public function remove(GdImage $image, string $originalBytes): GdImage; }` — returns a true-colour image with `imagesavealpha` on; throws `SkipImage` (extends `RuntimeException`, message is merchant-readable).
- Produces: `GdEdgeRemover::__construct(int $tolerance = 18, int $featherPx = 1)`; `public const KEY = 'gd-edge'`.

Algorithm (spec §3 fixes it; implement as described there):

1. Border samples: every pixel on the four edges when side ≤ 1000, else stride `ceil(side/1000)`. Median per channel.
2. Uniform if ≥ 85% of samples have distance ≤ `threshold`, where `threshold = tolerance * 1.1` (Euclidean RGB). Else `SkipImage('Background is not uniform')`.
3. BFS from all border pixels within threshold over a visited bitmap (`str_repeat("\0", w*h)`, 4-neighbours, `SplQueue` or int array with head index). Mark background.
4. Removed share < 3% or > 97% → `SkipImage('Could not separate the product from the background')`.
5. Output: a new true-colour image with alpha. Background pixels get alpha 127. A foreground pixel at Chebyshev distance `d` (1..`featherPx`) from the nearest background pixel gets alpha `round(127 * (featherPx + 1 - d) / (featherPx + 2))` (so with featherPx 1, the outer foreground ring is ~42 = one-third transparent). Pixels farther than `featherPx` keep alpha 0; `featherPx = 0` → hard edge. Source alpha is preserved: final alpha = max(source alpha, computed alpha).

- [ ] **Step 1: Write failing tests** (images built in-test with GD):
    - `test_corners_become_transparent_and_centre_stays_opaque`: 200×200 `#F4F2EF` + centred square → alpha at (2,2) and (197,197) = 127; alpha at (100,100) = 0.
    - `test_light_interior_not_connected_to_edge_is_kept`: dark ring (outer 120px, ring width 10) with interior filled `#F4F2EF` → alpha at centre = 0.
    - `test_product_touching_edge_keeps_its_pixels`: dark bar from x=80..120 spanning full height → alpha at (100,0) = 0 and (100,199) = 0; (5,5) = 127.
    - `test_noisy_background_is_skipped`: `mt_srand(7)` random pixels → `expectException(SkipImage::class)`.
    - `test_blank_image_is_skipped`: solid `#F4F2EF` → `SkipImage`.
    - `test_feather_zero_gives_hard_edge`: featherPx 0 → pixel just inside the square edge alpha 0.
- [ ] **Step 2: Run** `--filter=GdEdgeRemoverTest` → FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** → PASS. Also time a 2400×2400 synthetic image in a throwaway tinker call; note the seconds in the task report (expected: a few seconds).
- [ ] **Step 5: Note changed files.**

### Task 4: ImageProcessor (decode → remove → WebP → store)

**Files:**

- Create: `src/Services/ImageProcessor.php`, `src/Services/ProcessResult.php`, `src/Removers/RemoverFactory.php`
- Test: `tests/Feature/Extensions/ImageStudio/ImageProcessorTest.php`

**Interfaces:**

- Consumes: `ImageStudioSettings` (Task 2), `BackgroundRemover`, `GdEdgeRemover`, `SkipImage` (Task 3).
- Produces: `RemoverFactory::make(string $driver): BackgroundRemover` (`gd-edge` → `GdEdgeRemover(tolerance, featherPx)`; `removebg` → `RemoveBgRemover` from Task 6 — until then throw `InvalidArgumentException` for unknown drivers).
- Produces: `final readonly class ProcessResult(string $path, string $driver, int $bytesBefore, int $bytesAfter, int $width, int $height)`.
- Produces: `ImageProcessor::process(ProductImage $image): ProcessResult` — throws `SkipImage` (skip) or `RuntimeException` (fail) with merchant-readable messages: "Original image file is missing", "Image could not be decoded", "The image dimensions are too large". Writes only the new variant file; never touches the original or its row.

- [ ] **Step 1: Write failing tests**:
    - `test_produces_webp_with_alpha`: from `studioImage('jpg')` → stored file starts with `RIFF` and bytes 8–11 `WEBP`; `getimagesizefromstring` type `IMAGETYPE_WEBP`; `pixelAlpha` at corner 127; path matches `#^products/\d+/studio/[A-Za-z0-9]{40}\.webp$#`; original file bytes unchanged.
    - `test_webp_only_mode_keeps_pixels_opaque`: `remove_background=false` → driver `none`, corner alpha 0.
    - `test_large_image_is_downscaled_to_2400`: 3000×1500 → result width 2400, height 1200.
    - `test_transparent_png_keeps_alpha`: PNG with fully transparent background + opaque square, `remove_background=false` → corner alpha 127.
    - `test_missing_original_throws`: delete file → `RuntimeException` "Original image file is missing".
- [ ] **Step 2: Run** `--filter=ImageProcessorTest` → FAIL.
- [ ] **Step 3: Implement**: decode via `imagecreatefromstring`; convert palette to true colour (`imagepalettetotruecolor`), `imagealphablending(false)` + `imagesavealpha(true)`; downscale with `imagescale` (keeping alpha); encode with `imagewebp($img, null, quality)` into an output buffer; `Storage::disk($image->disk)->put(...)`.
- [ ] **Step 4: Run** → PASS.
- [ ] **Step 5: Note changed files.**

### Task 5: Job, upload trigger, URL resolver, deletion cleanup

**Files:**

- Create: `src/Jobs/ProcessProductImage.php`, `src/Services/VariantUrls.php`, `src/Services/ImageStudio.php`
- Modify: `src/ImageStudioServiceProvider.php`
- Test: `tests/Feature/Extensions/ImageStudio/ImageStudioPipelineTest.php`

**Interfaces:**

- Consumes: `ProductImageUrls` (Task 1), `ImageVariant`, `ImageStudioSettings` (Task 2), `ImageProcessor`, `ProcessResult` (Task 4), `SkipImage`.
- Produces: `ProcessProductImage(int $productImageId)` — `ShouldQueue`, `ShouldBeUnique` (`uniqueId` = image id), `$tries = 2`, `handle(ImageProcessor, ImageStudio)`. Missing image → return silently. Sets `processing`, then `done` (stores result fields, replaces + deletes previous variant file), `skipped` (error = exception message), or `failed` (error = `Str::limit(message, 500)`; on final attempt `failed()` sets the row).
- Produces: `ImageStudio` service: `queue(ProductImage $image): ImageVariant` (upsert row to `pending`, clear error, `ProcessProductImage::dispatch($id)->afterCommit()`), `queueMany(iterable<ProductImage>): int`, `revert(ImageVariant $variant): void` (delete file + row), `revertAll(): int`, `stats(): array{pending:int,processing:int,done:int,failed:int,skipped:int,bytes_saved:int}`.
- Produces: `VariantUrls` (`scoped` binding): `resolve(ProductImage $image): ?string` — loads `image_studio_variants where status=done` once as `product_image_id => [disk, path]`, returns `Storage::disk(disk)->url(path)` or null.
- Provider `boot`: if `settings->enabled()`, `ProductImageUrls::register(self::MODULE, fn ($img) => app(VariantUrls::class)->resolve($img))`; `ProductImage::created` → if enabled && processOnUpload → `ImageStudio::queue`; `ProductImage::deleting` → delete variant file (row cascades). Listeners check `enabled()` at call time so toggling the setting needs no reboot.

- [ ] **Step 1: Write failing tests**:
    - `test_upload_queues_job_and_creates_pending_row`: `Queue::fake()`; `app(StoreProductImage::class)->handle($product, UploadedFile::fake()->image('a.jpg', 300, 300))` → `Queue::assertPushed(ProcessProductImage::class)`; row status `pending`.
    - `test_upload_not_queued_when_process_on_upload_off`.
    - `test_job_marks_done_and_storefront_uses_variant`: `studioImage()`, `ProcessProductImage::dispatchSync($id)` → row `done`, `bytes_after > 0`; `$image->fresh()->url()` ends with `.webp` and contains `/studio/`; `GET /` storefront catalog page (or `app(StorefrontCatalog::class)` card payload) has `image.url` equal to the variant URL.
    - `test_skipped_image_keeps_serving_original`: noisy image → row `skipped`, `url()` is original.
    - `test_revert_restores_original_and_deletes_file`.
    - `test_enabled_setting_off_serves_originals`: process, then save setting `enabled=false` → `url()` returns the original disk URL (the resolver checks `settings->enabled()` at call time).
    - `test_disabled_module_serves_originals`: process, `ModuleManager::disable(MODULE)`, then `app()->forgetInstance(ProductImageUrls::class)` (a fresh request's container state) and `app(ModuleRepository::class)->enabledForBoot()` does not contain the module → `url()` returns the original. (`disable` only flips DB state; providers load on the next boot via `ModuleLoader::bootEnabled`, so a fresh registry with no resolver is what the next request sees.)
    - `test_deleting_product_image_removes_variant_file`.
    - `test_variant_lookup_is_one_query_for_many_images`: 10 processed images, `DB::enableQueryLog()`, call `url()` on all → exactly one query against `image_studio_variants`.
- [ ] **Step 2: Run** `--filter=ImageStudioPipelineTest` → FAIL.
- [ ] **Step 3: Implement.** The resolver closure returns null when `settings->enabled()` is false.
- [ ] **Step 4: Run** → PASS; run `--filter=Catalog` and `--filter=Cart` for regressions.
- [ ] **Step 5: Note changed files.**

### Task 6: remove.bg driver

**Files:**

- Create: `src/Removers/RemoveBgRemover.php`
- Modify: `src/Removers/RemoverFactory.php`
- Test: `tests/Feature/Extensions/ImageStudio/RemoveBgRemoverTest.php`

**Interfaces:**

- Consumes: `ImageStudioSettings::removeBgKey()`, `BackgroundRemover`.
- Produces: `RemoveBgRemover::__construct(?string $apiKey)`; `KEY = 'removebg'`; `remove()` sends `Http::timeout(60)->withHeaders(['X-Api-Key' => $key])->attach('image_file', $originalBytes, 'image')->post(URL, ['size' => 'auto', 'format' => 'png'])`; 200 → `imagecreatefromstring(body)` with alpha; non-200 → `RuntimeException("remove.bg request failed (HTTP {status})")`; null key → `RuntimeException('remove.bg API key is not set')` before any request.

- [ ] **Step 1: Write failing tests**:
    - `test_removebg_success_stores_variant`: `Http::fake(['api.remove.bg/*' => Http::response($transparentPngBytes, 200)])`, driver `removebg`, key set → `dispatchSync` → row `done`, driver `removebg`; `Http::assertSent` with header `X-Api-Key` = key.
    - `test_removebg_402_marks_failed_without_leaking_key`: 402 → row `failed`, error contains `402`, `assertStringNotContainsString($key, $row->error)`.
    - `test_removebg_without_key_fails_and_sends_nothing`: `Http::assertNothingSent()`.
- [ ] **Step 2: Run** → FAIL. **Step 3: Implement.** **Step 4: Run** → PASS. **Step 5: Note changed files.**

### Task 7: Artisan command

**Files:**

- Create: `src/Console/ProcessImagesCommand.php`; register in provider under `runningInConsole()`
- Test: `tests/Feature/Extensions/ImageStudio/ProcessImagesCommandTest.php`

**Interfaces:**

- Consumes: `ImageStudio::queue`, `ProcessProductImage`.
- Produces: signature `image-studio:process {--all} {--product=*} {--force} {--sync}`. Without `--all` or `--product` it prints usage and exits 1. Selection: product images with no `done` variant (or all with `--force`), filtered by product ids. `--sync` runs `ProcessProductImage::dispatchSync` per image with a progress bar; otherwise queues. Final line: `Processed: X done, Y skipped, Z failed.` (sync) or `Queued N images.`

- [ ] **Step 1: Write failing tests**: `test_sync_processes_all_unprocessed` (2 images → both `done`, output contains `2 done`); `test_product_filter_and_force` (done image not reprocessed without `--force`); `test_requires_scope_option` (exit code 1).
- [ ] **Step 2–4:** Run → FAIL, implement, run → PASS. **Step 5: Note changed files.**

### Task 8: Admin page and actions

**Files:**

- Create: `src/Http/Controllers/ImageStudioAdminController.php`, `resources/js/pages/admin/index.tsx`; fill `routes/admin.php`
- Test: `tests/Feature/Extensions/ImageStudio/ImageStudioAdminTest.php`

**Interfaces:**

- Consumes: `ImageStudio` (Task 5), `ImageStudioSettings` (Task 2).
- Routes (all `can:image_studio.manage`): `GET /` `index`; `POST process-all` `process-all`; `POST retry` `retry` (failed + skipped); `POST revert-all` `revert-all`; `POST images/{image}/process` `process`; `DELETE images/{image}` `revert`; `PUT removebg-key` `removebg-key` (`key` nullable string max 200; empty clears).
- `index` props: `stats` (from `ImageStudio::stats()`), `hasRemoveBgKey: bool`, `driver: string`, `images`: paginated 24, filter `status` (`unprocessed|pending|processing|done|failed|skipped`), each `{id, productName, originalUrl (disk URL, not resolved), variantUrl|null, status, error|null, bytesBefore, bytesAfter}`. The API key itself is never a prop.
- Page: follow `modules/modrixcommerce/social-proof-notifications/resources/js/pages/admin/index.tsx` for layout/components. Thumbnails on a CSS checkerboard background; confirm dialog on Revert all; `router.reload({ only: ['stats','images'] })` every 5s while `stats.pending + stats.processing > 0`.

- [ ] **Step 1: Write failing tests**:
    - `test_index_renders_for_owner` (Inertia component name; `hasRemoveBgKey` false; no prop contains the key after setting one).
    - `test_process_all_queues_only_unprocessed` (`Queue::fake`, one `done` + two new → 2 pushed).
    - `test_retry_requeues_failed_and_skipped`.
    - `test_revert_all_deletes_rows_and_files`.
    - `test_removebg_key_is_stored_encrypted_and_cleared` (`SecretSettings::get` returns it; raw settings value ≠ key; empty clears).
    - `test_routes_forbidden_without_permission` (user with `roleWithPermissions('clerk', [])` → 403 on index and process-all).
- [ ] **Step 2: Run** → FAIL. **Step 3: Implement.** Run `npm run build`.
- [ ] **Step 4: Run** `--filter=ImageStudio` (all module tests) → PASS, then full `C:\php84\php.exe artisan test` → no new failures vs. baseline (record baseline before Task 1).
- [ ] **Step 5: Note changed files.**

### Task 9: Browser verification on Sable

- [ ] **Step 1:** `preview_start` `ecomodular` (and `vite`); screenshot `/collections/all` (or the Shop all route) as the "before".
- [ ] **Step 2:** `C:\php84\php.exe artisan ecomodular:modules install modrixcommerce/image-studio` then `ecomodular:modules enable modrixcommerce/image-studio --trust`, then `C:\php84\php.exe artisan image-studio:process --all --sync` in the background; record the done/skipped/failed counts.
- [ ] **Step 3:** Reload Shop all; check network requests show `/storage/products/*/studio/*.webp`; screenshot "after"; spot-check a PDP gallery and the cart thumbnail.
- [ ] **Step 4:** Open the admin Image Studio page; screenshot; note skipped images. If halos appear on many images, try tolerance 24 and reprocess; report the chosen value.
- [ ] **Step 5:** Write a hand-over note in the shared notes folder summarising results and any images needing remove.bg.
