# Image Studio module — design

Date: 2026-11-06
Module: `modrixcommerce/image-studio` (namespace `EcoModular\ImageStudio`)

## Purpose

Product photos in the Sable "Shop all" grid sit on their own off-white/grey
studio backgrounds that clash with the card background. Image Studio removes
those backgrounds and stores each result as a transparent WebP, so products
blend into any theme and pages ship lighter images.

Success means:

- An installable, enableable module with admin settings and a way to process
  the existing catalogue.
- Processed images are WebP with an alpha channel and are what the storefront
  serves.
- Originals are never modified; any image (or all of them) can be reverted.
- Feature tests pass; the result is verified in the browser on Sable "Shop all".

## Decisions (agreed with the user)

| Question                  | Decision                                                                                                                                              |
| ------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------- |
| Background removal engine | Built-in pure PHP/GD "edge" driver by default; optional remove.bg API driver.                                                                         |
| When processing runs      | Both: queued job on new uploads, plus a bulk admin action and artisan command for existing images.                                                    |
| Originals / serving       | Originals kept untouched. A small core URL seam lets the module serve the variant; disabling the module or reverting restores the original instantly. |

Constraints: the dev PHP (8.4, `C:\php84`) has GD with WebP support and no
Imagick, so GD is the only image library assumed. The dev server is
single-threaded, so no image processing happens inside a web request (jobs or
the artisan command only). The queue connection is `database`.

## 1. Core seam

New `App\Core\Media\ProductImageUrls` (container singleton):

```php
final class ProductImageUrls
{
    /** @param  Closure(ProductImage): ?string  $resolver */
    public function register(string $module, Closure $resolver): void;
    public function forget(string $module): void;
    public function resolve(ProductImage $image): ?string; // first non-null wins
}
```

`ProductImage::url()` becomes:

```php
return app(ProductImageUrls::class)->resolve($this)
    ?? Storage::disk($this->disk)->url($this->path);
```

Modules declare use of the seam with a new manifest capability,
`catalog.image_urls` ("Replaces product image files on the storefront"), added
to `Manifest::CAPABILITIES` and `docs/extensions/module.schema.json`.

All existing call sites (storefront catalog grid and hover image, PDP gallery,
cart lines, admin product/variant/inventory screens) pick up the variant with
no theme or controller changes. Modules only boot when enabled, so a disabled
Image Studio registers no resolver and originals are served.

Resolver cost: on the first `resolve()` call in a request, the module loads
all `done` variants in one query (`product_image_id => path` only) into a
scoped in-memory map; later calls are array lookups. A 54-product grid costs
one extra query, not 54. The table holds at most one row per product image, so
loading it whole is cheap at catalogue sizes this platform targets. The map is
bound with `scoped()` so queue workers and tests do not see stale entries.

## 2. Data and storage

Migration `create_image_studio_variants_table`:

| column                    | type                                        | notes                                                |
| ------------------------- | ------------------------------------------- | ---------------------------------------------------- |
| id                        | bigint pk                                   |                                                      |
| product_image_id          | fk → product_images, unique, cascade delete |                                                      |
| status                    | string(16)                                  | `pending`, `processing`, `done`, `failed`, `skipped` |
| disk                      | string                                      | same disk as the original                            |
| path                      | string nullable                             | `products/{product_id}/studio/{random40}.webp`       |
| driver                    | string(32) nullable                         | `gd-edge` / `removebg` / `none` (WebP only)          |
| error                     | string(500) nullable                        | reason for `failed` / `skipped`                      |
| bytes_before, bytes_after | unsigned int nullable                       | for "bytes saved"                                    |
| width, height             | unsigned int nullable                       |                                                      |
| timestamps                |                                             |                                                      |

Model `EcoModular\ImageStudio\Models\ImageVariant`.

- The original file and its `product_images` row are never written to.
- When a product image is deleted, the FK cascade removes the row; a
  `ProductImage::deleting` listener also deletes the variant file.
- Reprocessing replaces the variant file (old file deleted after the new one
  is written).
- Revert = delete the variant file and row.

## 3. Processing pipeline

`Jobs\ProcessProductImage(int $productImageId)` — queued, `tries = 2`,
unique per image id (`ShouldBeUnique`). It marks the row `processing`, calls
`ImageProcessor::process(ProductImage): ProcessResult`, and stores the
outcome. Exceptions mark the row `failed` with a short message (never a stack
trace; never the API key).

`Services\ImageProcessor`:

1. Load the original bytes from its disk; decode with GD (`imagecreatefromstring`),
   rejecting anything over `ImageSanitizer::MAX_PIXELS`.
2. If `remove_background` is on, run the configured `BackgroundRemover`;
   otherwise skip to step 3 (driver `none`).
3. Encode WebP with alpha (`imagesavealpha` + `imagewebp`, `webp_quality`).
4. Write to the variant path; return dimensions and byte sizes.

`Contracts\BackgroundRemover`:

```php
interface BackgroundRemover
{
    /** Returns a true-colour GdImage with alpha, or throws SkipImage. */
    public function remove(GdImage $image, string $originalBytes): GdImage;
}
```

### `GdEdgeRemover` (default, driver key `gd-edge`)

1. **Sample the border**: read pixels along all four edges (every pixel on
   images ≤ 1000px per side, otherwise a stride). Compute the median colour.
2. **Uniformity check**: if fewer than 85% of border samples are within
   `tolerance` of the median, throw `SkipImage("Background is not uniform")`.
   The row is marked `skipped`; the original keeps being served.
3. **Flood fill from the edges**: breadth-first over a visited bitmap
   (`SplFixedArray` / packed string), seeded from every border pixel within
   tolerance, expanding to 4-neighbours whose colour distance to the median
   is within tolerance. Only background _connected to the edge_ is removed,
   so a white product interior that does not touch the edge survives.
4. **Feather**: for background pixels bordering foreground, and foreground
   pixels within `feather_px` of background, set partial alpha by distance,
   softening the cut edge.
5. **Safety check**: if more than 97% or less than 3% of pixels were removed,
   throw `SkipImage` (whole image matched or nothing matched).

Colour distance: Euclidean in RGB, scaled so `tolerance` 1–100 maps to
0–~110 of distance (default 18).

Performance: a 2000×2000 image is 4M pixels; pure-PHP BFS is acceptable in a
queued job / CLI (seconds per image), never in a request. Images larger than
`max_dimension` (fixed 2400px) are downscaled first; the variant is stored at
the downscaled size.

### `RemoveBgRemover` (optional, driver key `removebg`)

POSTs the original bytes to `https://api.remove.bg/v1.0/removebg`
(`size=auto`, `format=png`) via Laravel `Http` with `X-Api-Key`, 60s timeout.
A 200 response's PNG is decoded with GD and passed to the WebP encoder.
402/403/429 and other errors raise a failure with the HTTP status in the
message. With no key configured, the job fails with "remove.bg API key is not
set" and nothing is sent.

The API key is stored encrypted through `App\Core\Settings\SecretSettings`
(key `image_studio.removebg_api_key`), set from the module admin page, and
never sent back to the browser (the page only shows whether a key is set).

## 4. Triggers

- **New uploads**: the service provider registers
  `ProductImage::created(...)`. When `enabled` and `process_on_upload` are on,
  it creates a `pending` variant row and dispatches the job
  (`afterCommit`). `StoreProductImage` is not modified.
- **Admin bulk actions** (POST, CSRF, `can:image_studio.manage`):
    - _Process all_: queues every product image without a `done` variant.
    - _Retry failed/skipped_: queues those rows again.
    - _Revert all_: deletes all variants and their files.
    - Per image: _Process / Reprocess_ and _Revert_.
- **Artisan** `image-studio:process {--all} {--product=*} {--force} {--sync}`:
  `--all` queues every unprocessed image, `--product` limits to product ids,
  `--force` includes `done` ones, `--sync` processes inline in the CLI
  (`dispatchSync`) with a progress bar, so the dev site works without a
  queue worker.

## 5. Settings (module.json)

| key               | type           | default   | notes                                                  |
| ----------------- | -------------- | --------- | ------------------------------------------------------ |
| enabled           | boolean        | true      | master switch for serving variants and auto-processing |
| process_on_upload | boolean        | true      |                                                        |
| remove_background | boolean        | true      | off = WebP conversion only                             |
| driver            | select         | `gd-edge` | `gd-edge`, `removebg`                                  |
| tolerance         | integer 1–100  | 18        | gd-edge only (`visible_when` driver)                   |
| feather_px        | integer 0–4    | 1         | gd-edge only                                           |
| webp_quality      | integer 40–100 | 82        |                                                        |

When `enabled` is false the resolver returns null (originals served) and
uploads are not queued; existing variants are kept.

Permission: `image_studio.manage` ("Manage product image processing",
group Catalog). Admin navigation: "Image Studio", group Catalog, icon `image`.

## 6. Admin page

Inertia page `resources/js/pages/admin/index.tsx`, following the existing
module admin page conventions:

- Summary: counts per status, total bytes saved, queue hint ("Run
  `php artisan queue:work` or use `image-studio:process --all --sync`").
- Actions: Process all, Retry failed/skipped, Revert all (confirm dialog).
- remove.bg key form (set / replace / clear); shows "Key set" only.
- Paginated grid (24 per page, filter by status): original thumbnail and
  variant thumbnail on a checkerboard background, product name, status
  badge, error text, bytes saved, Reprocess / Revert buttons.

The page polls (every 5s while any row is `pending`/`processing`) by
re-requesting the Inertia props.

## 7. Error handling

- Decode failure, oversize image, missing file → `failed` with a message.
- Non-uniform or degenerate background → `skipped`; original keeps serving.
- remove.bg errors → `failed` with HTTP status; nothing logged with the key.
- Job retries once; a second failure leaves the row `failed` for the
  admin to retry.
- Variant file missing on disk for a `done` row → resolver still returns the
  URL (cheap); the admin "Reprocess" fixes it. (No filesystem check per
  request.)

## 8. Testing (Feature, SQLite)

Fixtures are generated in-test with GD (no binary fixtures): a coloured
shape centred on an off-white `#F4F2EF` background, saved as JPEG/PNG on
`Storage::fake('public')`.

- `GdEdgeRemover`: corners become alpha 127; the shape centre stays opaque;
  a white blob inside a dark ring (not edge-connected) is preserved; a noisy
  random-pixel image throws `SkipImage`.
- `ImageProcessor`: the output is WebP (`RIFF….WEBP` magic, `getimagesize`
  type `IMAGETYPE_WEBP`) and carries alpha; WebP-only mode keeps all pixels
  opaque.
- Seam: with a `done` variant, `ProductImage::url()` and the storefront
  catalog payload return the variant URL; after revert, or with `enabled`
  off, the original URL comes back; a disabled module registers no resolver.
- Upload trigger: storing a product image with `Queue::fake()` pushes
  `ProcessProductImage` and creates a `pending` row; with
  `process_on_upload` off nothing is pushed.
- Admin routes: process-all queues only unprocessed images; revert deletes
  files and rows; all routes return 403 without `image_studio.manage`.
- Artisan: `image-studio:process --all --sync` produces `done` variants.
- remove.bg: `Http::fake()` returning a PNG → `done` with driver
  `removebg`; the API key header is sent; 402 → `failed`; no key →
  `failed` and no request is sent.
- Product image deletion removes the variant file.

## 9. Browser verification

On the dev site (`ecomodular`, 127.0.0.1:8017): install and enable the
module, run `image-studio:process --all --sync` over the demo catalogue,
then screenshot Sable "Shop all" before and after and spot-check the PDP
gallery and the cart thumbnail. Note any images marked `skipped` and how they
look.

## Out of scope

- Responsive sizes / `srcset`, CDN rewriting, and `<picture>` fallbacks
  (every browser the storefront supports renders WebP).
- Converting non-product media (logos, banners, media library uploads).
- Local AI models (rembg / ONNX); the `BackgroundRemover` contract leaves
  room for one later.
- Editing or cropping images.
