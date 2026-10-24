# Deploying the marketplace

The marketplace is a standard Laravel 13 application. It runs on any host
with PHP 8.3+ and one of: MySQL 5.7+, MariaDB 10.3+ or SQLite 3.35+. It has
its own database and does not share anything with merchant stores. Stores
talk to it over HTTPS.

PHP extensions: `ctype`, `curl`, `fileinfo`, `mbstring`, `openssl`, `pdo`
(with `pdo_mysql` or `pdo_sqlite`), `tokenizer`, `xml` and `zip`. `sodium`
is used when present; otherwise the bundled `paragonie/sodium_compat`
does the signing.

Set PHP `upload_max_filesize` and `post_max_size` to at least `25M`; package
ZIPs may be up to 20 MB.

## Shared hosting (cPanel, Plesk, DirectAdmin)

1. **Database.** Create a MySQL/MariaDB database and user in the control
   panel (or use SQLite and skip this step).
2. **Upload.** Upload the `marketplace-catalog` folder *outside*
   `public_html`, for example to `/home/you/marketplace`.
3. **Document root.** Point the domain or subdomain (for example
   `marketplace.example.com`) at `/home/you/marketplace/public`. If the panel
   cannot change the document root, make `public_html` a symlink to the
   `public` folder. Never expose the app root: `.env` and `storage/` must not
   be reachable from the web.
4. **Configuration.** Copy `.env.example` to `.env` and set:
   - `APP_URL=https://marketplace.example.com`
   - `DB_CONNECTION=mysql` (or `mariadb`), `DB_HOST`, `DB_DATABASE`,
     `DB_USERNAME`, `DB_PASSWORD`. For SQLite keep `DB_CONNECTION=sqlite` and
     `DB_DATABASE=database/marketplace.sqlite`, then create that empty file.
5. **Install.** In the panel's terminal (or over SSH), from the app folder:

   ```bash
   composer install --no-dev --optimize-autoloader
   php artisan key:generate
   php artisan migrate --force --seed
   php artisan marketplace:install
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

   `marketplace:install` asks for the first administrator's name, email
   and password and prints the store signing public key. Without a terminal,
   skip it and open `https://marketplace.example.com/admin/setup` once to
   create the administrator.

   No Composer on the host? Run `composer install --no-dev` on your computer
   and upload the `vendor/` folder too.
6. **Cron.** Add one cron job that runs every minute:

   ```
   * * * * * cd /home/you/marketplace && php artisan schedule:run >> /dev/null 2>&1
   ```

   It processes queued email (license keys after purchase) and removes
   expired download links. Without cron, set `QUEUE_CONNECTION=sync` in
   `.env` so email is sent during the request.
7. **Settings.** Sign in at `/admin/login` and fill in Settings:
   marketplace name and currency, Stripe keys, SMTP. Use **Send a test
   email** to check delivery.

## VPS (Nginx + PHP-FPM)

Install the app as above (for example in `/var/www/marketplace`, owned by
the PHP-FPM user), then:

```nginx
server {
    listen 80;
    server_name marketplace.example.com;
    root /var/www/marketplace/public;
    index index.php;
    client_max_body_size 25m;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known) {
        deny all;
    }
}
```

Add HTTPS with Certbot (`certbot --nginx -d marketplace.example.com`);
stores send license keys to this server, so always serve it over HTTPS.
The scheduler cron (above) also covers the queue; a busy server can run a
worker under systemd or Supervisor instead
(`php artisan queue:work --sleep=3 --tries=3`).

Apache works too: point `DocumentRoot` at `public/`. The bundled
`public/.htaccess` handles the rewrites when `AllowOverride All` is set.

## Stripe

1. In the Stripe dashboard → Developers → API keys, copy the publishable
   and secret keys into Admin → Settings → Payments.
2. Developers → Webhooks → Add endpoint:
   `https://marketplace.example.com/stripe/webhook`, events
   `checkout.session.completed`, `checkout.session.expired` and
   `charge.refunded`. Copy the signing secret (`whsec_…`) into the same
   settings page.

A paid order is completed by the webhook, not by the browser returning from
Stripe, so the webhook must be reachable from the internet.

## Connecting stores

Admin → Settings → Licensing shows the Ed25519 public key. In each
merchant store's `.env`:

```
ECOMODULAR_MARKETPLACE_URL=https://marketplace.example.com
ECOMODULAR_MARKETPLACE_TRUSTED_KEYS=<public key>
```

Keep any keys already listed (comma separated), then run
`php artisan config:clear` in the store. Customers paste their license key
in the store's Admin → Marketplace page.

## Moving from the old catalog app

The previous catalog app kept everything in `storage/catalog.sqlite`. Copy
that file (and its `storage/` folder, which holds release ZIPs and uploads)
to the server. Import **before the site is public**: until then anyone
could register an old customer's email or open `/admin/setup`. Keep the
app in maintenance mode while you do it:

```bash
php artisan down
php artisan migrate --force --seed
php artisan marketplace:import-legacy /path/to/old/storage/catalog.sqlite
php artisan up
```

It copies accounts (passwords keep working), packages, categories, licenses,
store activations, orders, submissions, the logo, uploads and release ZIPs.
The signing key is kept, so stores connected before keep working without a
new trusted key. Skip `marketplace:install` when importing; the old
administrators come along.

The command prints warnings for anything it could not copy. An email that
already has an account here with a different password is not merged (its
licenses and orders are skipped), and values longer than the new columns are
shortened or dropped.

Running it again adds only what is new. Settings changed in the new admin
(Stripe keys, SMTP, currency, ...) are kept; only settings that do not exist
yet are copied.

## Publishing from the platform repository

`php artisan marketplace:publish vendor/package` zips
`modules/vendor/package` or `themes/vendor/package` from `CATALOG_ROOT`
(default: the folder above this app) and publishes it as a signed release.
A new package is listed as published; an existing listing only gets the new
version.

## Backups

Back up two things, daily:

- the database (`mysqldump`, or the SQLite file), which holds accounts,
  licenses, orders, settings and the signing key (encrypted with `APP_KEY`);
- `storage/app/private` (or `MARKETPLACE_STORAGE`), which holds release
  ZIPs, submissions, thumbnails and the logo.

Keep a copy of `.env` somewhere safe. Without its `APP_KEY` the stored
secrets (Stripe, SMTP, signing key) cannot be decrypted, and stores would
need a new trusted key.

## Updating the marketplace

Upload the new code (keeping `.env` and `storage/`), then:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize
```
