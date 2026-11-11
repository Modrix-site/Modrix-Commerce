<?php

namespace EcoModular\ExternalStorage\Support;

/**
 * Structured configuration and setup documentation for every supported external storage provider.
 * Available automatically in the Admin UI as soon as the module is installed and enabled.
 */
final class ProviderSetupGuides
{
    /**
     * @return array<string, array{
     *     provider: string,
     *     name: string,
     *     protocol: string,
     *     summary: string,
     *     console_url: string,
     *     docs_url: string,
     *     required_fields: list<string>,
     *     example_region: string,
     *     example_endpoint: string,
     *     example_cdn: string,
     *     steps: list<string>,
     *     policy_snippet: string|null,
     *     cors_note: string
     * }>
     */
    public static function all(): array
    {
        return [
            'wasabi' => [
                'provider' => 'wasabi',
                'name' => 'Wasabi Hot Cloud Storage (S3)',
                'protocol' => 'AWS Signature V4 (S3 Compatible)',
                'summary' => 'High-performance S3-compatible cloud storage with zero egress fees and no API request charges.',
                'console_url' => 'https://console.wasabisys.com',
                'docs_url' => 'https://docs.wasabi.com/docs/creating-a-bucket',
                'required_fields' => ['Bucket Name', 'Region', 'Access Key ID', 'Secret Access Key'],
                'example_region' => 'us-east-1, us-west-1, eu-central-1, ap-southeast-1',
                'example_endpoint' => 'https://s3.eu-central-1.wasabisys.com (Auto-resolved if blank)',
                'example_cdn' => 'https://s3.eu-central-1.wasabisys.com/your-bucket',
                'steps' => [
                    'Sign in to the Wasabi Console (console.wasabisys.com) and go to Buckets → Create Bucket.',
                    'Enter a unique bucket name (e.g. "store-media") and select your closest storage region (e.g. "us-east-1" or "eu-central-1").',
                    'Open the bucket Settings → Policies and add the Public Read bucket policy below so product photos and videos can be viewed on your storefront.',
                    'Navigate to Access Keys → Create New Access Key and copy the Access Key ID and Secret Key.',
                    'In this page, select "Wasabi Hot Cloud Storage (S3)", fill in your Bucket Name, Region, Access Key ID, and Secret Access Key, then click "Save Storage Settings" and "Test Connection".',
                ],
                'policy_snippet' => <<<'JSON'
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
JSON,
                'cors_note' => 'Wasabi automatically serves public objects with standard HTTP range requests for HTML5 product video streaming.',
            ],

            's3' => [
                'provider' => 's3',
                'name' => 'Amazon Web Services (AWS S3)',
                'protocol' => 'AWS Signature V4 (Native S3)',
                'summary' => 'Amazon Simple Storage Service (S3) for durable global storage of product photos and videos, optionally paired with Amazon CloudFront.',
                'console_url' => 'https://s3.console.aws.amazon.com/s3/home',
                'docs_url' => 'https://docs.aws.amazon.com/AmazonS3/latest/userguide/GetStartedWithS3.html',
                'required_fields' => ['Bucket Name', 'Region', 'Access Key ID', 'Secret Access Key'],
                'example_region' => 'us-east-1, us-west-2, eu-west-1, me-central-1, ap-southeast-1',
                'example_endpoint' => 'https://s3.us-east-1.amazonaws.com (Auto-resolved if blank)',
                'example_cdn' => 'https://d111111abcdef8.cloudfront.net (Optional CloudFront URL)',
                'steps' => [
                    'Open the AWS S3 Console and click Create bucket. Choose a bucket name and your preferred AWS Region.',
                    'Under Block Public Access settings, uncheck "Block all public access" (or serve via an Amazon CloudFront distribution and enter its domain in Public CDN URL).',
                    'Open your bucket → Permissions → Bucket policy and paste the Public Read policy below (replace YOUR-BUCKET-NAME).',
                    'Open AWS IAM → Users → Create user, attach a policy granting s3:PutObject, s3:GetObject, s3:DeleteObject, and s3:ListBucket on your bucket, and generate an Access Key under Security credentials.',
                    'Enter your Bucket Name, AWS Region, Access Key ID, and Secret Access Key here, save settings, and click "Test Connection".',
                ],
                'policy_snippet' => <<<'JSON'
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "PublicReadGetObject",
      "Effect": "Allow",
      "Principal": "*",
      "Action": "s3:GetObject",
      "Resource": "arn:aws:s3:::YOUR-BUCKET-NAME/*"
    }
  ]
}
JSON,
                'cors_note' => 'If streaming product videos across domains with custom players, add a CORS rule in S3 Permissions allowing GET and HEAD from your store origin.',
            ],

            'cloudflare_r2' => [
                'provider' => 'cloudflare_r2',
                'name' => 'Cloudflare R2 Storage (S3 API)',
                'protocol' => 'AWS Signature V4 (Cloudflare R2 S3 Endpoint)',
                'summary' => 'Cloudflare R2 object storage using the S3-compatible endpoint with zero egress bandwidth fees.',
                'console_url' => 'https://dash.cloudflare.com/?to=/:account/r2',
                'docs_url' => 'https://developers.cloudflare.com/r2/api/s3/tokens/',
                'required_fields' => ['Bucket Name', 'Cloudflare Account ID', 'Access Key ID', 'Secret Access Key', 'Public CDN / Custom Domain URL'],
                'example_region' => 'auto',
                'example_endpoint' => 'https://<account_id>.r2.cloudflarestorage.com (Auto-resolved)',
                'example_cdn' => 'https://cdn.yourstore.com or https://pub-xxxx.r2.dev',
                'steps' => [
                    'Log in to the Cloudflare Dashboard → R2 Object Storage and click Create bucket.',
                    'Copy your 32-character Account ID from the top-right sidebar of the R2 overview page.',
                    'Open your R2 bucket → Settings → Public Access, and connect a Custom Domain (e.g. https://cdn.yourstore.com) or enable the public r2.dev subdomain. Paste that URL into "Public CDN / Custom Domain URL".',
                    'Go to R2 Overview → Manage R2 API Tokens → Create API token with "Object Read & Write" permission scoped to your bucket.',
                    'Copy the generated S3 "Access Key ID" and "Secret Access Key", enter them along with your Bucket Name and Cloudflare Account ID, and click "Save Storage Settings".',
                ],
                'policy_snippet' => null,
                'cors_note' => 'Public access in Cloudflare R2 is controlled by connecting a Custom Domain or enabling the r2.dev subdomain in Bucket Settings.',
            ],

            'cloudflare_api' => [
                'provider' => 'cloudflare_api',
                'name' => 'Cloudflare Storage via REST API',
                'protocol' => 'Cloudflare v4 Account REST API (Bearer Token)',
                'summary' => 'Upload product photos and videos directly through the Cloudflare v4 REST API using a single Bearer API Token.',
                'console_url' => 'https://dash.cloudflare.com/profile/api-tokens',
                'docs_url' => 'https://developers.cloudflare.com/api/resources/r2/subresources/buckets/',
                'required_fields' => ['Bucket Name', 'Cloudflare Account ID', 'API Token (Bearer Token)', 'Public CDN / Custom Domain URL'],
                'example_region' => 'auto',
                'example_endpoint' => 'https://api.cloudflare.com/client/v4/accounts/<account_id>/r2/buckets/<bucket>/objects',
                'example_cdn' => 'https://cdn.yourstore.com or https://pub-xxxx.r2.dev',
                'steps' => [
                    'In the Cloudflare Dashboard → R2 Object Storage, create your bucket and copy your 32-character Cloudflare Account ID.',
                    'In your R2 bucket Settings, connect a Custom Domain or enable the public r2.dev URL so storefront visitors can load uploaded photos and videos.',
                    'Go to Cloudflare Dashboard → My Profile → API Tokens → Create Token (or R2 → Manage R2 API Tokens).',
                    'Grant the token "Account → Workers R2 Storage → Edit" permission and copy the Bearer API Token.',
                    'Select "Cloudflare Storage via REST API", enter your Bucket Name, Cloudflare Account ID, Public CDN URL, and paste the token into "API Token / Storage Access Key".',
                ],
                'policy_snippet' => null,
                'cors_note' => 'Uses standard HTTPS port 443 calls to api.cloudflare.com with Authorization: Bearer <token>.',
            ],

            'digitalocean' => [
                'provider' => 'digitalocean',
                'name' => 'DigitalOcean Spaces',
                'protocol' => 'AWS Signature V4 (S3 Compatible)',
                'summary' => 'S3-compatible object storage with built-in global Edge CDN from DigitalOcean.',
                'console_url' => 'https://cloud.digitalocean.com/spaces',
                'docs_url' => 'https://docs.digitalocean.com/products/spaces/how-to/create/',
                'required_fields' => ['Bucket (Space Name)', 'Region', 'Access Key ID', 'Secret Access Key'],
                'example_region' => 'nyc3, ams3, sgp1, fra1, sfo3, lon1, syd1',
                'example_endpoint' => 'https://nyc3.digitaloceanspaces.com (Auto-resolved)',
                'example_cdn' => 'https://your-space.nyc3.cdn.digitaloceanspaces.com',
                'steps' => [
                    'In the DigitalOcean Control Panel, go to Spaces Object Storage → Create a Space.',
                    'Choose a datacenter region (e.g. nyc3, fra1, sgp1), optionally enable the built-in CDN, and name your Space.',
                    'Navigate to API → Spaces Keys → Generate New Key and copy both the Access Key and Secret Key.',
                    'In this module, select "DigitalOcean Spaces", enter the Space name in Bucket, datacenter code in Region (e.g. nyc3), and your Access Key ID and Secret Access Key.',
                    'If you enabled the Spaces CDN, paste the CDN endpoint into "Public CDN / Custom Domain URL".',
                ],
                'policy_snippet' => null,
                'cors_note' => 'Uploaded objects automatically include x-amz-acl: public-read so product photos and videos are immediately accessible.',
            ],

            'backblaze' => [
                'provider' => 'backblaze',
                'name' => 'Backblaze B2 Cloud Storage',
                'protocol' => 'AWS Signature V4 (Backblaze S3-Compatible API)',
                'summary' => 'Low-cost S3-compatible cloud storage ideal for large product video libraries and high-resolution photo catalogs.',
                'console_url' => 'https://secure.backblaze.com/b2_buckets.htm',
                'docs_url' => 'https://www.backblaze.com/docs/cloud-storage-s3-compatible-api',
                'required_fields' => ['Bucket Name', 'Region', 'Access Key ID (keyID)', 'Secret Access Key (applicationKey)'],
                'example_region' => 'us-west-004, us-east-005, eu-central-003',
                'example_endpoint' => 'https://s3.us-west-004.backblazeb2.com (Auto-resolved)',
                'example_cdn' => 'https://f004.backblazeb2.com/file/your-bucket',
                'steps' => [
                    'Sign in to Backblaze B2 Cloud Storage → Buckets → Create a Bucket, and set "Files in Bucket are:" to Public.',
                    'Note the S3 Endpoint displayed on your bucket card (e.g. s3.us-west-004.backblazeb2.com). The middle part (e.g. us-west-004) is your Region.',
                    'Open Application Keys → Add a New Application Key with Read and Write access to your bucket.',
                    'Copy the keyID (enter as Access Key ID) and applicationKey (enter as Secret Access Key).',
                    'Save your settings and click "Test Connection".',
                ],
                'policy_snippet' => null,
                'cors_note' => 'Pair Backblaze B2 with Cloudflare CDN (Bandwidth Alliance) for zero-egress media delivery by setting your custom domain in Public CDN URL.',
            ],

            'bunnycdn' => [
                'provider' => 'bunnycdn',
                'name' => 'BunnyCDN Edge Storage API',
                'protocol' => 'Bunny.net HTTP Storage API (AccessKey)',
                'summary' => 'High-speed global edge storage and Pull Zone CDN designed for ultra-fast e-commerce images and product videos.',
                'console_url' => 'https://dash.bunny.net/storage',
                'docs_url' => 'https://docs.bunny.net/reference/storage-api',
                'required_fields' => ['Bucket (Storage Zone Name)', 'Region', 'API Token (FTP & API Password)', 'Public CDN URL (Pull Zone URL)'],
                'example_region' => 'de (Falkenstein default), ny, la, sg, syd, uk, se',
                'example_endpoint' => 'https://storage.bunnycdn.com (or https://ny.storage.bunnycdn.com)',
                'example_cdn' => 'https://your-zone.b-cdn.net',
                'steps' => [
                    'Log in to the Bunny.net Dashboard → Storage → Add Storage Zone.',
                    'Enter your Storage Zone Name (use this as Bucket Name) and select your main storage region (e.g. de, ny, sg).',
                    'Go to CDN → Add Pull Zone linked to your Storage Zone, and copy its hostname (e.g. https://mystore.b-cdn.net) into "Public CDN / Custom Domain URL".',
                    'Open your Storage Zone → FTP & API Access and copy the read/write "Password" (this is your API AccessKey).',
                    'Select "BunnyCDN Edge Storage API", enter your Storage Zone Name in Bucket, region code in Region, Pull Zone URL in Public CDN URL, and paste the Password into "API Token / Storage Access Key".',
                ],
                'policy_snippet' => null,
                'cors_note' => 'In your BunnyCDN Pull Zone → Headers, enable "Add CORS Header" for seamless video and image loading.',
            ],

            'gcs' => [
                'provider' => 'gcs',
                'name' => 'Google Cloud Storage',
                'protocol' => 'AWS Signature V4 (GCS S3 Interoperability)',
                'summary' => 'Enterprise Google Cloud Storage buckets accessed via Google Cloud S3-interoperable HMAC credentials.',
                'console_url' => 'https://console.cloud.google.com/storage/browser',
                'docs_url' => 'https://cloud.google.com/storage/docs/interoperability',
                'required_fields' => ['Bucket Name', 'Region', 'Access Key ID (HMAC Key)', 'Secret Access Key (HMAC Secret)'],
                'example_region' => 'us-east1, us-central1, europe-west1, asia-east1',
                'example_endpoint' => 'https://storage.googleapis.com (Auto-resolved)',
                'example_cdn' => 'https://storage.googleapis.com/your-bucket',
                'steps' => [
                    'In Google Cloud Console → Cloud Storage → Buckets, create a bucket.',
                    'To allow public viewing of product photos and videos, open the bucket Permissions tab → Grant Access → add principal "allUsers" with role "Storage Object Viewer".',
                    'Navigate to Cloud Storage → Settings → Interoperability.',
                    'Under Access keys for service accounts, click "Create a key for a service account" (with Storage Object Admin role) and copy the Access Key (starts with GOOG) and Secret.',
                    'Select "Google Cloud Storage", enter your Bucket Name, Region, Access Key ID, and Secret Access Key, then click "Test Connection".',
                ],
                'policy_snippet' => null,
                'cors_note' => 'Google Cloud Storage XML API works seamlessly with S3 Signature V4.',
            ],

            'minio' => [
                'provider' => 'minio',
                'name' => 'MinIO Object Storage',
                'protocol' => 'AWS Signature V4 (Self-Hosted S3)',
                'summary' => 'Self-hosted S3-compatible object storage server for private cloud or dedicated media servers.',
                'console_url' => 'https://min.io/docs/minio/linux/administration/minio-console.html',
                'docs_url' => 'https://min.io/docs/minio/linux/Index.html',
                'required_fields' => ['Bucket Name', 'Custom API / S3 Endpoint URL', 'Access Key ID', 'Secret Access Key'],
                'example_region' => 'us-east-1',
                'example_endpoint' => 'https://minio.yourdomain.com:9000',
                'example_cdn' => 'https://minio.yourdomain.com:9000/your-bucket',
                'steps' => [
                    'Log in to your MinIO Console and create a bucket (e.g. "store-media").',
                    'Set the bucket Access Policy to "Public" (or "Download" for anonymous read) so product media can be loaded on the storefront.',
                    'Go to Access Keys → Create access key and copy the Access Key and Secret Key.',
                    'In EcoModular, select "MinIO Object Storage", enter your Bucket Name, your MinIO S3 API URL in "Custom API / S3 Endpoint URL", and your Access Key ID and Secret Access Key.',
                ],
                'policy_snippet' => null,
                'cors_note' => 'Ensure your MinIO S3 API port (usually 9000 or reverse-proxied via 443) is reachable via HTTPS.',
            ],

            'custom_s3' => [
                'provider' => 'custom_s3',
                'name' => 'Custom S3-Compatible Storage',
                'protocol' => 'AWS Signature V4 (Path-Style S3)',
                'summary' => 'Connect any S3-compatible cloud provider such as Scaleway, Hetzner, Linode/Akamai, Vultr, OVHcloud, Contabo, or Ceph.',
                'console_url' => 'https://s3.amazonaws.com',
                'docs_url' => 'https://docs.aws.amazon.com/AmazonS3/latest/API/Welcome.html',
                'required_fields' => ['Bucket Name', 'Region', 'Custom API / S3 Endpoint URL', 'Access Key ID', 'Secret Access Key'],
                'example_region' => 'fsn1, fr-par, nl-ams, us-southeast-1',
                'example_endpoint' => 'https://s3.fr-par.scw.cloud or https://fsn1.your-objectstorage.com',
                'example_cdn' => 'https://cdn.yourstore.com',
                'steps' => [
                    'Create a bucket in your S3-compatible storage provider dashboard and enable public read access for stored objects.',
                    'Locate your provider S3 Endpoint URL, Region identifier, Access Key ID, and Secret Access Key.',
                    'Select "Custom S3-Compatible Storage", enter your Bucket Name, Region, Custom API / S3 Endpoint URL, and credentials.',
                    'Optionally enter a "Public CDN / Custom Domain URL" if your bucket is fronted by a custom domain or CDN.',
                ],
                'policy_snippet' => null,
                'cors_note' => 'Uses standard path-style S3 requests signed with AWS4-HMAC-SHA256.',
            ],

            'custom_api' => [
                'provider' => 'custom_api',
                'name' => 'Custom HTTP REST Storage API',
                'protocol' => 'HTTP REST (PUT / GET / HEAD / DELETE + Bearer Token)',
                'summary' => 'Connect a custom media microservice or HTTP storage server via standard REST calls.',
                'console_url' => '',
                'docs_url' => '',
                'required_fields' => ['Custom API / S3 Endpoint URL', 'API Token (Optional)', 'Public CDN / Custom Domain URL (Optional)'],
                'example_region' => 'global',
                'example_endpoint' => 'https://storage-api.yourdomain.com/v1/files',
                'example_cdn' => 'https://cdn.yourdomain.com/files',
                'steps' => [
                    'Configure your HTTP server to accept PUT <endpoint>/<remote_path> with the raw binary file body and Content-Type header.',
                    'EcoModular includes "Authorization: Bearer <api_token>", "X-Storage-Bucket: <bucket>", and "X-Access-Key: <access_key>" headers on each request.',
                    'Support DELETE <endpoint>/<remote_path> for file removal and HEAD/GET <endpoint>/<remote_path> for existence/read checks.',
                    'Enter your endpoint URL in "Custom API / S3 Endpoint URL", your public delivery domain in "Public CDN URL", and your Bearer token in "API Token / Storage Access Key".',
                ],
                'policy_snippet' => null,
                'cors_note' => 'Ensure your public CDN or media server serves appropriate Content-Type and Accept-Ranges headers for video playback.',
            ],
        ];
    }
}
