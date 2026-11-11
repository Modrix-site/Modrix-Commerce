# External Cloud Storage for Modrix Commerce (`modrixcommerce/external-storage`)

Offload and automatically save product photos, product videos, and media library assets directly to external cloud storage instead of local server disk.

Once **installed and enabled**, open **Admin → Catalog → External Storage** (`/admin/m/modrixcommerce/external-storage`) to access the interactive **Provider Setup Documentation**, configure your storage credentials, test your connection, upload product photos & videos directly to cloud storage, or bulk-sync existing local images.

---

## Supported External Storage Providers

1. **Wasabi Hot Cloud Storage (`wasabi`)** — S3-compatible API (`AWS4-HMAC-SHA256`)
2. **Amazon Web Services S3 (`s3`)** — Native AWS S3 API (`AWS4-HMAC-SHA256`)
3. **Cloudflare R2 Storage (`cloudflare_r2`)** — S3-compatible R2 API
4. **Cloudflare Storage via REST API (`cloudflare_api`)** — Cloudflare Account v4 R2 REST API (Bearer Token)
5. **DigitalOcean Spaces (`digitalocean`)** — S3-compatible Spaces API + CDN
6. **Backblaze B2 Cloud Storage (`backblaze`)** — Backblaze S3-Compatible API
7. **BunnyCDN Edge Storage API (`bunnycdn`)** — Bunny.net HTTP Storage API (`AccessKey`)
8. **Google Cloud Storage (`gcs`)** — GCS S3 Interoperable XML API (HMAC Keys)
9. **MinIO Object Storage (`minio`)** — Self-hosted S3-compatible storage
10. **Custom S3-Compatible Storage (`custom_s3`)** — Scaleway, Hetzner, Linode/Akamai, Vultr, OVHcloud, Contabo, Ceph
11. **Custom HTTP REST Storage API (`custom_api`)** — Custom `PUT`/`GET`/`HEAD`/`DELETE` media server with Bearer authentication

---

## Automatic Storage Modes (`save_mode`)

| Mode | Behavior |
| --- | --- |
| **External Only (`external_only`)** | **Save automatically in external storage instead of locally (No local copy).** Switches `ecomodular.media_disk` to `external_storage` so newly uploaded product photos and videos are stored directly in your cloud bucket and removed from local disk. |
| **Hybrid (`hybrid`)** | **Save automatically in external storage + keep local backup.** Uploads every new product photo and video to external cloud storage while keeping a local copy on the server's `public` disk. |
| **Manual (`manual`)** | **Manual sync only.** Keeps new uploads on local disk until you click **Sync Local Photos** or run `php artisan ecomodular:external-storage:sync`. |

---

## Provider Setup & Configuration Guide

### 1. Wasabi Hot Cloud Storage (`wasabi`)

- **Console**: https://console.wasabisys.com
- **Official Docs**: https://docs.wasabi.com/docs/creating-a-bucket
- **Setup Steps**:
  1. Sign in to the **Wasabi Console** and go to **Buckets → Create Bucket**.
  2. Enter a bucket name (e.g. `store-media`) and choose your closest region (e.g. `us-east-1`, `us-west-1`, `eu-central-1`, `ap-southeast-1`).
  3. Open **Bucket Settings → Policies** and paste the Public Read bucket policy below so product photos and videos can be loaded on your storefront:
     ```json
     {
       "Version": "2012-10-17",
       "Statement": [
         {
           "Sid": "PublicReadForStoreMedia",
           "Effect": "Allow",
           "Principal": "*",
           "Action": ["s3:GetObject"],
           "Resource": ["arn:aws:s3:::YOUR-BUCKET-NAME/*"]
         }
       ]
     }
     ```
  4. Go to **Access Keys → Create New Access Key** and copy the **Access Key ID** and **Secret Access Key**.
  5. In **Admin → Catalog → External Storage**, select **Wasabi Hot Cloud Storage (S3)**, enter your **Bucket Name**, **Region**, **Access Key ID**, and **Secret Access Key**, then click **Save Storage Settings** and **Test Connection**.

---

### 2. Amazon Web Services — AWS S3 (`s3`)

- **Console**: https://s3.console.aws.amazon.com/s3/home
- **Official Docs**: https://docs.aws.amazon.com/AmazonS3/latest/userguide/GetStartedWithS3.html
- **Setup Steps**:
  1. Open the **AWS S3 Console** and click **Create bucket**. Choose a bucket name and AWS Region (e.g. `us-east-1`, `eu-west-1`, `me-central-1`, `ap-southeast-1`).
  2. Under **Block Public Access settings**, uncheck *Block all public access* (or front the bucket with an **Amazon CloudFront** distribution and enter its domain in **Public CDN URL**).
  3. Under **Permissions → Bucket policy**, allow public `s3:GetObject` on `arn:aws:s3:::YOUR-BUCKET-NAME/*`.
  4. In **AWS IAM → Users**, create an IAM user with `s3:PutObject`, `s3:GetObject`, `s3:DeleteObject`, and `s3:ListBucket` permissions on your bucket, and generate an **Access Key**.
  5. Enter your **Bucket Name**, **Region**, **Access Key ID**, and **Secret Access Key** in External Storage settings and click **Test Connection**.

---

### 3. Cloudflare R2 Storage — S3 API (`cloudflare_r2`)

- **Console**: https://dash.cloudflare.com/?to=/:account/r2
- **Official Docs**: https://developers.cloudflare.com/r2/api/s3/tokens/
- **Setup Steps**:
  1. Log in to the **Cloudflare Dashboard → R2 Object Storage** and click **Create bucket**.
  2. Copy your **32-character Cloudflare Account ID** from the R2 overview page sidebar.
  3. Open your bucket → **Settings → Public Access**, and connect a **Custom Domain** (e.g. `https://cdn.yourstore.com`) or enable the public `r2.dev` subdomain. Enter that URL in **Public CDN / Custom Domain URL**.
  4. Go to **R2 Overview → Manage R2 API Tokens → Create API token** with **Object Read & Write** permissions.
  5. Copy the generated S3 **Access Key ID** and **Secret Access Key**, enter them along with your **Bucket Name** and **Cloudflare Account ID** (`Region` defaults to `auto`), and save.

---

### 4. Cloudflare Storage via REST API (`cloudflare_api`)

- **Console**: https://dash.cloudflare.com/profile/api-tokens
- **Official Docs**: https://developers.cloudflare.com/api/resources/r2/subresources/buckets/
- **Setup Steps**:
  1. In **Cloudflare Dashboard → R2 Object Storage**, create your bucket and copy your **32-character Cloudflare Account ID**.
  2. In your R2 bucket **Settings**, connect a **Custom Domain** or enable the public `r2.dev` URL and paste it into **Public CDN / Custom Domain URL**.
  3. Go to **My Profile → API Tokens → Create Token** (or **R2 → Manage R2 API Tokens**) and grant **Account → Workers R2 Storage → Edit** permission.
  4. Copy the **Bearer API Token**.
  5. Select **Cloudflare Storage via REST API**, enter your **Bucket Name**, **Cloudflare Account ID**, **Public CDN URL**, and paste your token into **API Token / Storage Access Key**.

---

### 5. DigitalOcean Spaces (`digitalocean`)

- **Console**: https://cloud.digitalocean.com/spaces
- **Official Docs**: https://docs.digitalocean.com/products/spaces/how-to/create/
- **Setup Steps**:
  1. In the **DigitalOcean Control Panel**, go to **Spaces Object Storage → Create a Space**.
  2. Select a datacenter region (`nyc3`, `ams3`, `sgp1`, `fra1`, `sfo3`, `lon1`, `syd1`), optionally enable the built-in CDN, and create your Space.
  3. Go to **API → Spaces Keys → Generate New Key** and copy the **Access Key** and **Secret Key**.
  4. Select **DigitalOcean Spaces**, enter your Space name in **Bucket**, datacenter code in **Region**, and your **Access Key ID** and **Secret Access Key**.

---

### 6. Backblaze B2 Cloud Storage (`backblaze`)

- **Console**: https://secure.backblaze.com/b2_buckets.htm
- **Official Docs**: https://www.backblaze.com/docs/cloud-storage-s3-compatible-api
- **Setup Steps**:
  1. Sign in to **Backblaze B2 → Buckets → Create a Bucket** and set **Files in Bucket are:** to **Public**.
  2. Check the **Endpoint** shown on your bucket (e.g. `s3.us-west-004.backblazeb2.com`); the middle segment (`us-west-004`) is your **Region**.
  3. Open **Application Keys → Add a New Application Key** with Read and Write access.
  4. Copy `keyID` (enter as **Access Key ID**) and `applicationKey` (enter as **Secret Access Key**).

---

### 7. BunnyCDN Edge Storage API (`bunnycdn`)

- **Console**: https://dash.bunny.net/storage
- **Official Docs**: https://docs.bunny.net/reference/storage-api
- **Setup Steps**:
  1. In **Bunny.net Dashboard → Storage**, click **Add Storage Zone**.
  2. Enter a Storage Zone Name (use as **Bucket**) and primary region (`de` for Falkenstein default, `ny`, `la`, `sg`, `syd`, `uk`, `se`).
  3. Create a linked **Pull Zone** under **CDN** and copy its hostname (e.g. `https://mystore.b-cdn.net`) into **Public CDN / Custom Domain URL**.
  4. In your Storage Zone → **FTP & API Access**, copy the read/write **Password** and paste it into **API Token / Storage Access Key**.

---

### 8. Google Cloud Storage (`gcs`)

- **Console**: https://console.cloud.google.com/storage/browser
- **Official Docs**: https://cloud.google.com/storage/docs/interoperability
- **Setup Steps**:
  1. In **Google Cloud Console → Cloud Storage → Buckets**, create your bucket and grant `allUsers` the `Storage Object Viewer` role for public storefront delivery.
  2. Go to **Cloud Storage → Settings → Interoperability** and generate an **HMAC Access Key** (`GOOG...`) and **Secret**.
  3. Select **Google Cloud Storage**, enter your **Bucket Name**, **Region** (e.g. `us-east1`), **Access Key ID**, and **Secret Access Key**.

---

### 9. MinIO / Custom S3 / Custom HTTP REST API (`minio`, `custom_s3`, `custom_api`)

- **MinIO (`minio`)**: Create a public bucket in your MinIO Console, generate an Access Key & Secret Key, and enter your MinIO server URL in **Custom API / S3 Endpoint URL** (e.g. `https://minio.yourdomain.com:9000`).
- **Custom S3 (`custom_s3`)**: Connect Scaleway, Hetzner, Linode/Akamai, Vultr, OVHcloud, or Ceph by entering your bucket name, region, custom S3 endpoint URL, Access Key ID, and Secret Access Key.
- **Custom HTTP REST API (`custom_api`)**: Sends `PUT`, `GET`, `HEAD`, and `DELETE` requests to `{endpoint}/{remote_path}` with `Authorization: Bearer {api_token}`, `X-Storage-Bucket: {bucket}`, and `X-Access-Key: {access_key}`.
