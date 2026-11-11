# Changelog — External Cloud Storage (`modrixcommerce/external-storage`)

## [1.0.0] - 2026-11-11

### Added

- **Multi-Cloud External Storage Engine**:
    - Added native API & S3-compatible protocol support for **Wasabi Hot Cloud Storage**, **AWS S3**, **Cloudflare R2 (S3 API)**, **Cloudflare Storage via Account REST API v4**, **DigitalOcean Spaces**, **Backblaze B2**, **BunnyCDN Edge Storage API**, **Google Cloud Storage**, **MinIO**, and **Custom S3 / HTTP REST APIs**.
- **Save Automatically in External Storage Instead of Locally**:
    - Added configurable `save_mode` (`external_only`, `hybrid`, `manual`) and `delete_local_after_sync`.
    - Registered custom Laravel filesystem disk `external_storage` (`ExternalStorageFlysystemAdapter`) and dynamically switches `config('ecomodular.media_disk')` so product photos and media uploads are saved directly to external storage without consuming local disk space.
    - Added automatic `ProductImage` and `Media` event listeners to offload newly uploaded product images and media items to external cloud storage and remove local copies when configured.
- **Product Photos & Product Videos Support**:
    - Added direct upload for product photos (`.jpg`, `.png`, `.webp`, `.gif`, `.avif`) and product videos (`.mp4`, `.webm`, `.mov`, `.m4v`, `.ogg`) to external cloud storage with automatic product association.
    - Added Storefront `ProductExternalMediaSlot` (`product.after_details`) to render an interactive product video gallery on storefront product pages.
    - Added Admin `AdminProductStoragePanel` (`admin.product.sidebar`) on the product edit page for per-product cloud photo & video uploads and syncing.
    - Integrated with `ProductImageUrls` (`catalog.image_urls`) so product photos seamlessly resolve to their cloud or custom CDN URLs across the storefront and admin.
- **CLI & Bulk Migration**:
    - Added one-click Admin bulk sync and `ecomodular:external-storage:sync` Artisan command to offload existing local product images to external storage.
