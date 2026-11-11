# External Cloud Storage (`modrixcommerce/external-storage`)

Offloads and automatically saves product photos, product videos, and media library assets directly to external cloud storage instead of local server disk.

## Install & Enable

1. Go to **Admin → Extensions**, locate **External Cloud Storage (Wasabi, AWS S3, Cloudflare & Multi-Cloud)** (`modrixcommerce/external-storage`), and click **Install**.
2. Click **Enable** to activate the module.
3. Once installed or enabled:
    - Basic settings and region/endpoint suggestions are immediately available on the extension detail page (`/admin/extensions/{id}`).
    - Full interactive **Provider Setup Documentation**, API credential management, connection testing, direct product photo & video cloud uploads, and bulk local-to-cloud syncing are available at **Admin → Catalog → External Storage** (`/admin/m/modrixcommerce/external-storage`).
    - On every **Admin Product Edit** page (`/admin/products/{id}/edit`), the **External Cloud Storage** sidebar panel allows uploading product photos and videos directly to cloud storage for that product.

## Supported Providers & Setup Summary

| Provider                          | Code             | Protocol                  | Required Configuration                                                                                          |
| --------------------------------- | ---------------- | ------------------------- | --------------------------------------------------------------------------------------------------------------- |
| **Wasabi Hot Cloud Storage**      | `wasabi`         | AWS Signature V4 (S3)     | Bucket, Region (`us-east-1`, `eu-central-1`, etc.), Access Key ID, Secret Access Key                            |
| **Amazon Web Services (AWS S3)**  | `s3`             | AWS Signature V4 (S3)     | Bucket, Region (`us-east-1`, `eu-west-1`, etc.), Access Key ID, Secret Access Key, Optional CloudFront CDN URL  |
| **Cloudflare R2 (S3 API)**        | `cloudflare_r2`  | AWS Signature V4 (R2)     | Bucket, Cloudflare Account ID, Access Key ID, Secret Access Key, Public CDN / `r2.dev` URL                      |
| **Cloudflare Storage (REST API)** | `cloudflare_api` | Cloudflare v4 REST API    | Bucket, Cloudflare Account ID, Bearer API Token, Public CDN / `r2.dev` URL                                      |
| **DigitalOcean Spaces**           | `digitalocean`   | AWS Signature V4 (S3)     | Bucket (Space Name), Region (`nyc3`, `ams3`, `sgp1`, `fra1`), Access Key ID, Secret Access Key                  |
| **Backblaze B2**                  | `backblaze`      | AWS Signature V4 (S3)     | Bucket, Region (`us-west-004`, `eu-central-003`), Access Key ID (`keyID`), Secret Access Key (`applicationKey`) |
| **BunnyCDN Edge Storage**         | `bunnycdn`       | Bunny HTTP Storage API    | Bucket (Storage Zone), Region (`de`, `ny`, `sg`), API Token (Storage Password), Pull Zone CDN URL               |
| **Google Cloud Storage**          | `gcs`            | S3 Interoperable XML API  | Bucket, Region (`us-east1`), HMAC Access Key ID, HMAC Secret Key                                                |
| **MinIO Object Storage**          | `minio`          | AWS Signature V4 (S3)     | Bucket, Custom S3 Endpoint URL, Access Key ID, Secret Access Key                                                |
| **Custom S3-Compatible**          | `custom_s3`      | AWS Signature V4 (S3)     | Bucket, Region, Custom S3 Endpoint URL, Access Key ID, Secret Access Key                                        |
| **Custom HTTP REST API**          | `custom_api`     | HTTP `PUT`/`GET`/`DELETE` | Custom API Endpoint URL, Optional Bearer API Token, Optional Public CDN URL                                     |

## CLI Command

To bulk-offload existing local product photos from the command line:

```bash
php artisan ecomodular:external-storage:sync
```

Or for a single product:

```bash
php artisan ecomodular:external-storage:sync --product=42
```
