<?php

require dirname(__DIR__).'/src/env.php';
catalog_load_env(dirname(__DIR__).'/.env');

$catalogAutoload = dirname(__DIR__).'/vendor/autoload.php';
if (is_file($catalogAutoload)) {
    require_once $catalogAutoload;
}

require dirname(__DIR__).'/src/Catalog.php';
require dirname(__DIR__).'/src/Store.php';
require dirname(__DIR__).'/src/Mailer.php';
require dirname(__DIR__).'/src/Stripe.php';
require dirname(__DIR__).'/src/RepositoryAdmin.php';
require dirname(__DIR__).'/src/Licenses.php';
require dirname(__DIR__).'/src/AccountPages.php';
require dirname(__DIR__).'/src/MarketplacePages.php';

// Isolate this app (and test instances) from the merchant store and other catalogs.
$sessionPath = dirname(__DIR__).'/storage/framework/sessions';
if (! is_dir($sessionPath)) {
    mkdir($sessionPath, 0775, true);
}
session_save_path($sessionPath);
session_name('EMCATALOG_'.substr(hash('sha256', catalogStorage()), 0, 12));
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
session_start();
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

$store = new Store(catalogStorage());
$catalog = new Catalog(getenv('CATALOG_ROOT') ?: dirname(__DIR__, 2));
$mailer = new Mailer($store);
$stripe = new Stripe($store);
$licenses = new Licenses($store);
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$path = rtrim($path, '/') ?: '/';
$base = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http').'://'.($_SERVER['HTTP_HOST'] ?? '127.0.0.1:8090');
$GLOBALS['store'] = $store;
$GLOBALS['licenses'] = $licenses;
$GLOBALS['base'] = $base;

if (preg_match('#^/marketplace/download/([A-Za-z0-9]{64})$#D', $path, $download)) {
    try {
        $file = (new PackageRelease($store))->redeem($download[1]);
        header('Content-Type: application/zip');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        header('Content-Disposition: attachment; filename="'.basename($file['name']).'.zip"');
        header('Content-Length: '.(string) filesize($file['path']));
        readfile($file['path']);
    } catch (RuntimeException $exception) {
        http_response_code(in_array($exception->getCode(), [403, 404], true) ? $exception->getCode() : 404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'download_expired', 'message' => $exception->getMessage()]);
    }

    return;
}

if (preg_match('#^/api/marketplace/v1/(licenses/activate|licenses/check|licenses/deactivate|updates|downloads)$#D', $path, $action)) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        echo json_encode(['error' => 'method_not_allowed', 'message' => 'Use POST.']);

        return;
    }
    $body = (string) file_get_contents('php://input');
    $input = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') ? json_decode($body, true) : $_POST;
    try {
        if (! is_array($input) || strlen($body) > 16384) {
            throw new RuntimeException('Provide a valid request body up to 16 KB.', 422);
        }
        echo json_encode($licenses->api($action[1], $input, $base), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    } catch (PDOException) {
        http_response_code(503);
        echo json_encode(['error' => 'unavailable', 'message' => 'Licensing is temporarily unavailable. Please try again.']);
    } catch (RuntimeException $exception) {
        $status = in_array($exception->getCode(), [403, 404, 422], true) ? $exception->getCode() : 503;
        http_response_code($status);
        echo json_encode(['error' => match ($status) {
            404 => 'invalid_key', 403 => 'license_refused', 422 => 'invalid_request', default => 'unavailable'
        }, 'message' => $status === 503 ? 'Licensing is temporarily unavailable.' : $exception->getMessage()]);
    }

    return;
}

if (preg_match('#^/uploads/([a-f0-9]{32}\.(png|jpg|webp))$#D', $path, $upload)) {
    $file = catalogStorage().'/uploads/'.$upload[1];
    if (! is_file($file)) {
        http_response_code(404);

        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('Content-Type: '.['png' => 'image/png', 'jpg' => 'image/jpeg', 'webp' => 'image/webp'][$upload[2]]);
    readfile($file);

    return;
}

if ($path === '/stripe/webhook') {
    webhook($store, $mailer);

    return;
}

if ($path === '/api/marketplace/v1/catalog') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($catalog->api($base, $store->repository($catalog->products(), true), $store->currency()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return;
}

if (preg_match('#^/media/([a-z0-9-]+)$#', $path, $match) === 1) {
    $product = $catalog->find($match[1]);
    $file = is_array($product) ? ($product['screenshot'] ?? null) : null;
    if (! is_string($file) || ! is_file($file)) {
        http_response_code(404);
        echo 'Not found';

        return;
    }

    $type = match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
        'jpg', 'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        default => 'image/png',
    };
    header('Content-Type: '.$type);
    header('Content-Length: '.(string) filesize($file));
    readfile($file);

    return;
}

$repository = $store->repository($catalog->products());
$products = array_values(array_filter($repository, fn ($product) => $product['status'] === 'published'));
$bySlug = [];
foreach ($products as $product) {
    $bySlug[$product['slug']] = $product;
}

if (preg_match('#^/download/([a-z0-9-]+)$#D', $path, $match)) {
    $product = $bySlug[$match[1]] ?? null;
    if ($product === null) {
        http_response_code(404);
        echo 'Package not found.';

        return;
    }
    $customer = currentCustomer($store);
    if ($product['premium'] && ($customer === null || ! $store->owns((int) $customer['id'], $product['extension']))) {
        http_response_code(403);
        page('Purchase required', '<h1>Purchase required</h1><p>This package is available to customers who own it.</p><a href="/item/'.e($product['slug']).'">View package</a>', false);

        return;
    }
    sendCatalogPackage($product['package'] ?? '', $product['slug']);

    return;
}

if ($path === '/admin/setup') {
    if ($store->hasAdmin()) {
        catalog_redirect('/admin/login');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        guard();
        $email = strtolower(field('email'));
        $password = field('password');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            flash('Use a valid email and a password of at least 8 characters.');
            catalog_redirect('/admin/setup');
        }
        $store->createAdmin($email, $password);
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $store->admin($email)['id'];
        catalog_redirect('/admin');
    }
    page('Create the catalog admin', setupForm(), true);

    return;
}

if ($path === '/admin/login') {
    if (! $store->hasAdmin()) {
        catalog_redirect('/admin/setup');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        guard();
        $admin = $store->admin(strtolower(field('email')));
        if (! is_array($admin) || ! password_verify(field('password'), $admin['password_hash'])) {
            flash('Those catalog admin details do not match.');
            catalog_redirect('/admin/login');
        }
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $admin['id'];
        catalog_redirect('/admin');
    }
    page('Catalog admin', loginForm('Catalog admin', '/admin/login', 'Sign in to the catalog settings.'), true);

    return;
}

if ($path === '/admin/logout' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    guard();
    unset($_SESSION['admin_id']);
    catalog_redirect('/');
}

if (str_starts_with($path, '/admin')) {
    if (! $store->hasAdmin()) {
        catalog_redirect('/admin/setup');
    }
    if (! isset($_SESSION['admin_id'])) {
        catalog_redirect('/admin/login');
    }
    if (! repositoryAdmin($path, $store, $repository)) {
        admin($path, $store, $mailer, $repository);
    }

    return;
}

if (publisherPages($path, $store, $repository)) {
    return;
}

if ($path === '/account/register') {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        guard();
        $name = field('name');
        $email = strtolower(field('email'));
        $password = field('password');
        $_SESSION['auth_input'] = ['name' => $name, 'email' => $email];
        $next = safeNext(field('next'));
        if ($name === '' || strlen($name) > 120 || strlen($email) > 254 || ! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8 || $password !== field('password_confirmation')) {
            flash('Enter your name, a valid email, and matching passwords of at least 8 characters.');
            catalog_redirect('/account/register?next='.rawurlencode($next));
        }
        if ($store->customerByEmail($email) !== null) {
            flash('An account with that email already exists. Sign in instead.');
            catalog_redirect('/account/login?next='.rawurlencode($next));
        }
        $store->createCustomer($name, $email, $password);
        session_regenerate_id(true);
        $_SESSION['customer_id'] = (int) $store->customerByEmail($email)['id'];
        unset($_SESSION['auth_input']);
        catalog_redirect($next);
    }
    page('Create an account', registerForm(), true);

    return;
}

if ($path === '/account/login') {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        guard();
        $customer = $store->customerByEmail(strtolower(field('email')));
        if (! is_array($customer) || ! password_verify(field('password'), $customer['password_hash'])) {
            $_SESSION['auth_input'] = ['email' => field('email')];
            flash('Those account details do not match.');
            catalog_redirect('/account/login?next='.rawurlencode(safeNext(field('next'))));
        }
        session_regenerate_id(true);
        $_SESSION['customer_id'] = (int) $customer['id'];
        unset($_SESSION['auth_input']);
        catalog_redirect(safeNext(field('next')));
    }
    page('Sign in', loginForm('Your catalog account', '/account/login', 'Sign in to buy a premium theme or extension.'), true);

    return;
}

if ($path === '/account/logout' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    guard();
    unset($_SESSION['customer_id']);
    catalog_redirect('/');
}

if ($path === '/account') {
    header('Cache-Control: private, no-store');
    $customer = currentCustomer($store);
    if ($customer === null) {
        catalog_redirect('/account/login');
    }
    page('Your account', marketplaceAccount($store, $customer, $products, $licenses), false);

    return;
}

if (in_array($path, ['/account/licenses', '/account/licenses/release'], true)) {
    header('Cache-Control: private, no-store');
    $customer = currentCustomer($store);
    if ($customer === null) {
        catalog_redirect('/account/login');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');

        return;
    }
    guard();
    try {
        if ($path === '/account/licenses/release') {
            $licenses->release((int) $customer['id'], (int) field('license_id'));
            flash('Store connection released. Your key can now be activated on another store.');
        } else {
            $product = $bySlug[field('product')] ?? null;
            if (! is_array($product)) {
                throw new RuntimeException('Choose a published theme or extension.');
            }
            $licenses->generate((int) $customer['id'], $product);
            flash('Your license key is ready. Copy it below to activate it on your store.');
        }
    } catch (RuntimeException $exception) {
        flash($exception->getMessage());
    }
    catalog_redirect('/account');
}

if (preg_match('#^/buy/([a-z0-9-]+)$#', $path, $match) === 1 && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    guard();
    $customer = currentCustomer($store);
    $product = $bySlug[$match[1]] ?? null;
    if ($customer === null) {
        catalog_redirect('/account/login?next='.rawurlencode('/item/'.$match[1]));
    }
    if (! is_array($product) || $product['premium'] !== true) {
        flash('That product is not for sale.');
        catalog_redirect('/');
    }
    if ($store->owns((int) $customer['id'], $product['extension'])) {
        flash('You already own this product.');
        catalog_redirect('/account');
    }
    if (! $stripe->configured()) {
        flash('Checkout is currently unavailable. Please try again later.');
        catalog_redirect('/item/'.$product['slug']);
    }
    try {
        $url = $stripe->checkout(
            $product,
            $customer,
            $base.'/orders/success?session_id={CHECKOUT_SESSION_ID}',
            $base.'/item/'.$product['slug'],
        );
    } catch (RuntimeException $exception) {
        flash($exception->getMessage());
        catalog_redirect('/item/'.$product['slug']);
    }
    catalog_redirect($url);
}

if ($path === '/orders/success') {
    $customer = currentCustomer($store);
    $sessionId = (string) ($_GET['session_id'] ?? '');
    if ($customer === null) {
        catalog_redirect('/account/login');
    }
    try {
        $session = $stripe->session($sessionId);
        $metadata = is_array($session['metadata'] ?? null) ? $session['metadata'] : [];
        $extension = (string) ($metadata['extension'] ?? '');
        $product = null;
        foreach ($products as $candidate) {
            if ($candidate['extension'] === $extension) {
                $product = $candidate;
                break;
            }
        }
        if (($session['payment_status'] ?? '') !== 'paid' || (string) ($metadata['customer_id'] ?? '') !== (string) $customer['id'] || $product === null) {
            throw new RuntimeException('This payment is not complete.');
        }
        $fresh = ! $store->owns((int) $customer['id'], $extension);
        $order = $store->fulfill(
            (int) $customer['id'],
            $extension,
            $product['name'],
            number_format(((int) ($session['amount_total'] ?? 0)) / 100, 2, '.', ''),
            strtoupper((string) ($session['currency'] ?? $store->currency())),
            $sessionId,
        );
        if ($fresh) {
            receipt($mailer, $customer, $order);
        }
    } catch (RuntimeException $exception) {
        flash($exception->getMessage());
        catalog_redirect('/account');
    }
    page('Purchase complete', '<h1>Purchase complete</h1><p>Your license key is <strong>'.e((string) $order['license_key']).'</strong>.</p><p><a class="back" href="/account">View your purchases</a></p>', false);

    return;
}

$type = $_GET['type'] ?? 'all';
if (! in_array($type, ['all', 'theme', 'module'], true)) {
    $type = 'all';
}
$tier = $_GET['tier'] ?? 'all';
if (! in_array($tier, ['all', 'free', 'premium'], true)) {
    $tier = 'all';
}
$counts = [
    'all' => count($products),
    'theme' => count(array_filter($products, fn (array $product) => $product['type'] === 'theme')),
    'module' => count(array_filter($products, fn (array $product) => $product['type'] === 'module')),
];

if (preg_match('#^/item/([a-z0-9-]+)$#', $path, $match) === 1) {
    $product = $bySlug[$match[1]] ?? null;
    if ($product === null) {
        http_response_code(404);
        page('Not found', '<p class="empty">That product is not in the catalog.</p>', false, $counts, $type);

        return;
    }
    page($product['name'], detail($product, $store, $products), false, $counts, $product['type']);

    return;
}

if ($path !== '/') {
    http_response_code(404);
    page('Not found', '<p class="empty">That page is not in the catalog.</p>', false, $counts, $type);

    return;
}

page('Marketplace', marketplaceBrowse($store, $products, $type, $tier), false, $counts, $type);

/**
 * @param  list<array<string, mixed>>  $products
 */
function admin(string $path, Store $store, Mailer $mailer, array $products): void
{
    global $licenses;
    if ($path === '/admin' || $path === '/admin/general') {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            guard();
            $name = field('catalog_name');
            $currency = strtoupper(field('currency'));
            $email = field('support_email');
            if ($name === '' || strlen($name) > 120 || strlen(field('tagline')) > 500 || ! in_array($currency, ['AED', 'USD', 'EUR', 'GBP', 'SAR'], true)) {
                flash('Enter a catalog name and a supported currency.');
                catalog_redirect('/admin/general');
            }
            if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('The support email is not valid.');
                catalog_redirect('/admin/general');
            }
            try {
                $logo = catalogUpload('logo');
                if ($logo !== null || field('remove_logo') === '1') {
                    $store->put('logo', $logo ?? '');
                }
            } catch (RuntimeException $exception) {
                flash($exception->getMessage());
                catalog_redirect('/admin/general');
            }
            $store->put('catalog_name', $name);
            $store->put('tagline', field('tagline'));
            $store->put('currency', $currency);
            $store->put('support_email', $email);
            flash('General settings saved.');
            catalog_redirect('/admin/general');
        }
        settings('General settings', generalSettingsTabs($store, $licenses));

        return;
    }

    if ($path === '/admin/catalog') {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            guard();
            $posted = $_POST['offers'] ?? [];
            $rows = [];
            if (is_array($posted)) {
                foreach ($products as $product) {
                    $offer = $posted[$product['extension']] ?? null;
                    if (! is_array($offer)) {
                        continue;
                    }
                    $tier = ($offer['tier'] ?? '') === 'premium' ? 'premium' : 'free';
                    $inputPrice = is_string($offer['price'] ?? null) ? $offer['price'] : '';
                    $price = number_format((float) $inputPrice, 2, '.', '');
                    if ($tier === 'premium' && (! preg_match('/^\d{1,7}(?:\.\d{1,2})?$/D', $inputPrice) || (float) $price < 0.5)) {
                        flash($product['name'].' needs a premium price of at least 0.50.');
                        catalog_redirect('/admin/catalog');
                    }
                    $rows[] = ['extension' => $product['extension'], 'tier' => $tier, 'price' => $tier === 'premium' ? $price : '0.00'];
                }
            }
            $store->saveOffers($rows);
            flash('Catalog prices saved.');
            catalog_redirect('/admin/catalog');
        }
        settings('Catalog', catalogForm($store, $products));

        return;
    }

    if ($path === '/admin/stripe') {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            guard();
            $publishable = field('stripe_publishable');
            $secret = field('stripe_secret');
            $webhook = field('stripe_webhook_secret');
            if ($publishable !== '' && ! str_starts_with($publishable, 'pk_')) {
                flash('The publishable key should start with pk_.');
                catalog_redirect('/admin/general?tab=payments');
            }
            if ($secret !== '' && ! str_starts_with($secret, 'sk_')) {
                flash('The secret key should start with sk_.');
                catalog_redirect('/admin/general?tab=payments');
            }
            if ($webhook !== '' && ! str_starts_with($webhook, 'whsec_')) {
                flash('The webhook secret should start with whsec_.');
                catalog_redirect('/admin/general?tab=payments');
            }
            $store->put('stripe_publishable', $publishable);
            if ($secret !== '') {
                $store->put('stripe_secret', $secret);
            }
            if ($webhook !== '') {
                $store->put('stripe_webhook_secret', $webhook);
            }
            flash('Stripe settings saved.');
            catalog_redirect('/admin/general?tab=payments');
        }
        catalog_redirect('/admin/general?tab=payments');
    }

    if ($path === '/admin/smtp') {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && field('action') === 'test') {
            guard();
            $to = field('test_email');
            if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
                flash('Enter an email address for the test.');
                catalog_redirect('/admin/general?tab=email');
            }
            try {
                $mailer->send($to, 'Catalog mail test', 'This is a test from '.$store->catalogName().".\nIf you can read it, SMTP is working.");
                flash('Test email sent to '.$to.'.');
            } catch (RuntimeException $exception) {
                flash($exception->getMessage());
            }
            catalog_redirect('/admin/general?tab=email');
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            guard();
            $from = field('mail_from');
            $port = (int) field('smtp_port');
            $encryption = field('smtp_encryption');
            if ($from !== '' && ! filter_var($from, FILTER_VALIDATE_EMAIL)) {
                flash('The from address is not valid.');
                catalog_redirect('/admin/general?tab=email');
            }
            if (! in_array($encryption, ['tls', 'ssl', 'none'], true) || $port < 1 || $port > 65535) {
                flash('Check the port and encryption.');
                catalog_redirect('/admin/general?tab=email');
            }
            $store->put('smtp_host', field('smtp_host'));
            $store->put('smtp_port', (string) $port);
            $store->put('smtp_encryption', $encryption);
            $store->put('smtp_username', field('smtp_username'));
            if (field('smtp_password') !== '') {
                $store->put('smtp_password', field('smtp_password'));
            }
            $store->put('mail_from', $from);
            $store->put('mail_from_name', field('mail_from_name'));
            flash('Mail settings saved.');
            catalog_redirect('/admin/general?tab=email');
        }
        catalog_redirect('/admin/general?tab=email');
    }

    http_response_code(404);
    settings('Not found', '<p class="empty">That settings page does not exist.</p>');
}

function webhook(Store $store, Mailer $mailer): void
{
    $payload = (string) file_get_contents('php://input');
    $secret = $store->setting('stripe_webhook_secret');
    $header = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
    if ($secret === '' || ! stripeSignature($payload, $header, $secret)) {
        http_response_code(400);
        echo 'Invalid signature';

        return;
    }

    $event = json_decode($payload, true);
    $session = is_array($event) ? ($event['data']['object'] ?? null) : null;
    if (! is_array($session) || ($event['type'] ?? '') !== 'checkout.session.completed' || ($session['payment_status'] ?? '') !== 'paid') {
        http_response_code(200);
        echo 'Ignored';

        return;
    }

    $metadata = is_array($session['metadata'] ?? null) ? $session['metadata'] : [];
    $customerId = (int) ($metadata['customer_id'] ?? 0);
    $customer = $store->customer($customerId);
    $extension = (string) ($metadata['extension'] ?? '');
    $sessionId = (string) ($session['id'] ?? '');
    if ($customer === null || $extension === '' || ! str_starts_with($sessionId, 'cs_')) {
        http_response_code(200);
        echo 'Ignored';

        return;
    }

    $fresh = ! $store->owns($customerId, $extension);
    $order = $store->fulfill(
        $customerId,
        $extension,
        (string) ($session['metadata']['product_name'] ?? $extension),
        number_format(((int) ($session['amount_total'] ?? 0)) / 100, 2, '.', ''),
        strtoupper((string) ($session['currency'] ?? $store->currency())),
        $sessionId,
    );
    if ($fresh) {
        receipt($mailer, $customer, $order);
    }
    http_response_code(200);
    echo 'OK';
}

function stripeSignature(string $payload, string $header, string $secret): bool
{
    $parts = [];
    foreach (explode(',', $header) as $item) {
        [$key, $value] = array_pad(explode('=', trim($item), 2), 2, '');
        $parts[$key][] = $value;
    }
    $timestamp = $parts['t'][0] ?? '';
    if ($timestamp === '' || abs(time() - (int) $timestamp) > 300) {
        return false;
    }
    $signed = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
    foreach ($parts['v1'] ?? [] as $candidate) {
        if (hash_equals($signed, $candidate)) {
            return true;
        }
    }

    return false;
}

/**
 * @param  array<string, mixed>  $customer
 * @param  array<string, mixed>  $order
 */
function receipt(Mailer $mailer, array $customer, array $order): void
{
    if (! $mailer->configured()) {
        return;
    }
    try {
        $mailer->send(
            $customer['email'],
            'Your '.$order['product_name'].' license',
            "Thank you for your purchase.\n\n".$order['product_name']."\nLicense key: ".$order['license_key']."\nAmount: ".$order['currency'].' '.$order['amount']."\n",
        );
    } catch (RuntimeException) {
        flash('The purchase is saved. The receipt email could not be sent.');
    }
}

function currentCustomer(Store $store): ?array
{
    $id = $_SESSION['customer_id'] ?? null;

    return is_int($id) || (is_string($id) && ctype_digit($id)) ? $store->customer((int) $id) : null;
}

function field(string $name): string
{
    return is_string($_POST[$name] ?? null) ? trim($_POST[$name]) : '';
}

function guard(): void
{
    $token = field('_token');
    $known = (string) ($_SESSION['csrf'] ?? '');
    if ($known === '' || ! hash_equals($known, $token)) {
        http_response_code(419);
        echo 'The form expired. Go back and try again.';
        exit;
    }
}

function token(): string
{
    if (! isset($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }

    return $_SESSION['csrf'];
}

function flash(?string $message = null): string
{
    if ($message !== null) {
        $_SESSION['flash'] = $message;

        return '';
    }
    $current = (string) ($_SESSION['flash'] ?? '');
    unset($_SESSION['flash']);

    return $current;
}

function catalog_redirect(string $url): never
{
    if (! preg_match('#^https://#', $url) && ! str_starts_with($url, '/')) {
        $url = '/';
    }
    header('Location: '.$url, true, 302);
    exit;
}

function safeNext(string $next): string
{
    return str_starts_with($next, '/') && ! str_starts_with($next, '//') ? $next : '/account';
}

/**
 * @param  array{all: int, theme: int, module: int}|null  $counts
 */
function page(string $title, string $body, bool $bare, ?array $counts = null, string $type = 'all'): void
{
    global $store;
    header('Content-Type: text/html; charset=utf-8');
    $name = $store->catalogName();
    $customer = currentCustomer($store);
    $notice = flash();
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>'.e($title).' · '.e($name).'</title>';
    echo '<style>
        :root { color-scheme: light; --ink:#12141a; --muted:#5c6170; --line:#e6e8ee; --card:#fff; --bg:#f6f7fb; --accent:#1d4ed8; }
        * { box-sizing: border-box; }
        body { margin:0; font: 15px/1.5 "Segoe UI", sans-serif; color: var(--ink); background: var(--bg); }
        header { background:#12141a; color:#fff; }
        .wrap { width:min(1120px, calc(100% - 2rem)); margin:0 auto; }
        .bar { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:1rem 0; }
        .brand { color:#fff; font-weight:650; text-decoration:none; letter-spacing:-0.02em; }
        .brand span { color:#93c5fd; }
        nav { display:flex; gap:0.4rem; flex-wrap:wrap; }
        nav a, .ghost { color:#d7dbe7; text-decoration:none; border-radius:999px; padding:0.35rem 0.75rem; }
        nav a[aria-current="page"] { background:#fff; color:#12141a; }
        main { padding:2rem 0 4rem; }
        h1 { margin:0 0 0.4rem; font-size:2rem; letter-spacing:-0.03em; }
        .lead, .meta, .empty { color: var(--muted); }
        .lead { margin:0 0 1.5rem; max-width:42rem; }
        .grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap:1rem; list-style:none; padding:0; }
        .card { display:grid; gap:0.7rem; align-content:start; background:var(--card); border:1px solid var(--line); border-radius:16px; overflow:hidden; color:inherit; text-decoration:none; }
        .card img, .swatch { width:100%; height:160px; object-fit:cover; background:#eceff5; display:block; }
        .swatch { display:grid; place-items:center; font-size:0.8rem; letter-spacing:0.08em; text-transform:uppercase; color:#fff; }
        .card div, .panel { padding:1rem; }
        .card div { padding:0 1rem 1rem; display:grid; gap:0.35rem; }
        .card strong { font-size:1.05rem; }
        .pill { justify-self:start; border:1px solid var(--line); border-radius:999px; padding:0.1rem 0.5rem; font-size:0.75rem; }
        .price { font-weight:650; }
        .detail { display:grid; gap:1rem; grid-template-columns: minmax(0, 280px) 1fr; }
        .detail img, .detail .swatch { border-radius:16px; height:220px; }
        .back { color: var(--accent); }
        .panel { background:#fff; border:1px solid var(--line); border-radius:16px; display:grid; gap:0.8rem; max-width:32rem; }
        label { display:grid; gap:0.25rem; font-weight:600; }
        input, select { font:inherit; padding:0.55rem 0.7rem; border:1px solid var(--line); border-radius:10px; }
        button, .button { font:inherit; background:var(--accent); color:#fff; border:0; border-radius:999px; padding:0.55rem 1rem; cursor:pointer; text-decoration:none; display:inline-block; }
        .notice { background:#eff6ff; border:1px solid #bfdbfe; border-radius:12px; padding:0.7rem 0.9rem; margin:0 0 1rem; }
        table { width:100%; border-collapse:collapse; background:#fff; border:1px solid var(--line); border-radius:16px; overflow:hidden; }
        th, td { text-align:left; padding:0.65rem 0.8rem; border-bottom:1px solid var(--line); vertical-align:middle; }
        .tabs { display:flex; gap:0.5rem; margin:0 0 1rem; flex-wrap:wrap; }
        .tabs a { text-decoration:none; color:inherit; border:1px solid var(--line); background:#fff; border-radius:999px; padding:0.35rem 0.75rem; }
        .tabs a[aria-current="page"] { background:#12141a; color:#fff; }
        .filters { display:flex; gap:0.5rem; margin:0 0 1rem; }
        .filters a { color:inherit; text-decoration:none; border:1px solid var(--line); background:#fff; border-radius:999px; padding:0.3rem 0.7rem; }
        .filters a[aria-current="page"] { background:#12141a; color:#fff; }
        @media (max-width: 700px) { .detail { grid-template-columns: 1fr; } .bar { align-items:flex-start; flex-direction:column; } }
    </style><link rel="stylesheet" href="/marketplace.css"><link rel="stylesheet" href="/account.css"><link rel="stylesheet" href="/catalog.css"><script src="/account.js" defer></script><script src="/catalog.js" defer></script></head><body'.(str_starts_with($title, 'Admin · ') ? ' class="admin-body"' : (in_array($title, ['Sign in', 'Create an account', 'Catalog admin'], true) ? ' class="auth-body"' : ' class="marketplace-body"')).'>';
    $brand = $store->setting('logo') !== '' ? '<img class="brand-image" src="'.e($store->setting('logo')).'" alt="'.e($name).'">' : '<span class="brand-mark" aria-hidden="true">e</span>'.e($name);
    echo '<header><div class="wrap bar"><a class="brand" href="/">'.$brand.'</a><nav>';
    if (! $bare && $counts !== null) {
        foreach (['all' => 'All', 'theme' => 'Themes', 'module' => 'Extensions'] as $key => $label) {
            $current = $type === $key ? ' aria-current="page"' : '';
            echo '<a href="/'.($key === 'all' ? '' : '?type='.$key).'"'.$current.'>'.e($label).' '.$counts[$key].'</a>';
        }
    }
    if ($customer !== null) {
        echo '<a href="/account">Account</a>';
    } elseif (! $bare) {
        echo '<a href="/account/login">Log in</a>';
    }
    if (isset($_SESSION['admin_id'])) {
        echo '<a href="/admin">Admin workspace</a>';
    }
    if (! $bare) {
        echo '<a href="/submit">Submit a package ↗</a><a href="/submit/update">Submit an update ↗</a>';
    }
    echo '</nav></div></header><main class="wrap">';
    if ($notice !== '') {
        echo '<p class="notice">'.e($notice).'</p>';
    }
    if ($title === 'Marketplace') {
        $tagline = $store->setting('tagline', 'The repository for Modrix Commerce. Choose a storefront theme or an extension, then run it on the core platform.');
        echo marketplaceHero($store, $counts, $type);
    }
    echo $body;
    echo '</main><footer class="site-footer wrap"><a href="/">'.e($name).'</a><span>The building blocks of a better store.</span><a href="/admin">Marketplace admin ↗</a></footer></body></html>';
}

function settings(string $title, string $body): void
{
    $current = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    $tabs = '';
    foreach (['/admin' => '◈ Overview', '/admin/repository' => '▦ Repository', '/admin/updates' => '↑ Submit an update', '/admin/submissions' => '☷ Submissions & approvals', '/admin/categories' => '▤ Categories', '/admin/catalog' => '◇ Pricing', '/admin/general' => '⚙ General settings'] as $href => $label) {
        $active = $current === $href || ($href === '/admin/repository' && str_starts_with($current, $href.'/')) || ($href === '/admin/general' && in_array($current, ['/admin/stripe', '/admin/smtp'], true));
        $tabs .= '<a href="'.$href.'"'.($active ? ' aria-current="page"' : '').'>'.e($label).'</a>';
    }
    page('Admin · '.$title, '<div class="admin-shell"><aside class="admin-nav"><div><span class="eyebrow">MARKETPLACE STUDIO</span><strong>Build something great.</strong></div><nav>'.$tabs.'</nav><div class="admin-nav-bottom"><a href="/">↗ Visit marketplace</a><form method="post" action="/admin/logout">'.csrf().'<button class="secondary" type="submit">Log out</button></form></div></aside><div class="admin-main"><div class="section-heading"><div><span class="eyebrow">WORKSPACE / '.e(strtoupper($title)).'</span><h1>'.e($title).'</h1></div><span class="workspace-badge">● Marketplace admin</span></div>'.$body.'</div></div>', true);
}

function csrf(): string
{
    return '<input type="hidden" name="_token" value="'.e(token()).'">';
}

function setupForm(): string
{
    return '<form class="panel" method="post" action="/admin/setup"><h1>Create the catalog admin</h1><p class="lead">This login is only for the catalog. It is separate from the store admin.</p>'.csrf()
        .'<label>Email<input type="email" name="email" required></label>'
        .'<label>Password<input type="password" name="password" minlength="8" required></label>'
        .'<button type="submit">Create admin</button></form>';
}

function loginForm(string $heading, string $action, string $lead): string
{
    return marketplaceAuth(false, $action);
}

function registerForm(): string
{
    return marketplaceAuth(true, '/account/register');
}

function generalSettingsTabs(Store $store, Licenses $licenses): string
{
    $tabs = ['branding' => 'Brand & general', 'payments' => 'Stripe payments', 'email' => 'SMTP email', 'licensing' => 'Store licenses'];
    $selected = is_string($_GET['tab'] ?? null) && isset($tabs[$_GET['tab']]) ? $_GET['tab'] : 'branding';
    $html = '<div class="general-settings"><nav class="settings-tabs" aria-label="Settings sections">';
    foreach ($tabs as $key => $label) {
        $html .= '<a href="/admin/general?tab='.$key.'"'.($key === $selected ? ' aria-current="page"' : '').'>'.e($label).'</a>';
    }
    $content = match ($selected) {
        'payments' => '<h2>Stripe payments</h2><p class="settings-description">Connect checkout and manage payment credentials.</p>'.stripeForm($store),
        'email' => '<h2>SMTP email</h2><p class="settings-description">Configure outgoing mail and send a test message.</p><div class="mail-settings-layout">'.smtpForm($store).'</div>',
        'licensing' => licenseSettings($licenses),
        default => '<h2>Brand & general</h2><p class="settings-description">Manage your marketplace identity and regional preferences.</p>'.generalForm($store),
    };

    return $html.'</nav><div class="settings-pane" data-settings-panel="'.$selected.'">'.$content.'</div></div>';
}

function generalForm(Store $store): string
{
    $currency = $store->currency();
    $options = '';
    foreach (['AED', 'USD', 'EUR', 'GBP', 'SAR'] as $code) {
        $options .= '<option value="'.$code.'"'.($currency === $code ? ' selected' : '').'>'.$code.'</option>';
    }

    return '<form class="panel wide" method="post" enctype="multipart/form-data" action="/admin/general">'.csrf()
        .'<label>Catalog name<input name="catalog_name" maxlength="120" value="'.e($store->catalogName()).'" required></label>'
        .'<label>Tagline<input name="tagline" maxlength="500" value="'.e($store->setting('tagline')).'"></label>'
        .'<label>Currency<select name="currency">'.$options.'</select></label>'
        .'<label>Support email<input type="email" name="support_email" value="'.e($store->setting('support_email')).'"></label>'
        .'<div class="logo-field"><label>Custom logo<input type="file" name="logo" accept="image/png,image/jpeg,image/webp"></label><p class="meta">PNG, JPG or WebP, up to 3 MB. Used in the marketplace header.</p></div>'
        .($store->setting('logo') !== '' ? '<div class="logo-controls"><img class="logo-preview" src="'.e($store->setting('logo')).'" alt="Current logo"><label class="check"><input type="checkbox" name="remove_logo" value="1"> Remove custom logo</label></div>' : '')
        .'<button type="submit">Save general settings</button></form>';
}

/**
 * @param  list<array<string, mixed>>  $products
 */
function catalogForm(Store $store, array $products): string
{
    $html = '<form method="post" action="/admin/catalog">'.csrf().'<div class="table-scroll"><table><thead><tr><th>Product</th><th>Type</th><th>Access</th><th>Price</th></tr></thead><tbody>';
    foreach ($products as $product) {
        $premium = $product['premium'] === true;
        $html .= '<tr><td>'.e($product['name']).'<br><span class="meta">'.e($product['extension']).'</span></td><td>'.e($product['type'] === 'theme' ? 'Theme' : 'Extension').'</td><td><select name="offers['.e($product['extension']).'][tier]">';
        $html .= '<option value="free"'.($premium ? '' : ' selected').'>Free</option>';
        $html .= '<option value="premium"'.($premium ? ' selected' : '').'>Premium</option></select></td>';
        $html .= '<td><input name="offers['.e($product['extension']).'][price]" type="number" min="0" step="0.01" value="'.e($premium ? $product['price'] : '0.00').'"></td></tr>';
    }

    return $html.'</tbody></table></div><p><button type="submit">Save catalog</button></p><p class="meta">Prices use '.$store->currency().'. Free products stay available without payment. Premium products are sold with Stripe.</p></form>';
}

function stripeForm(Store $store): string
{
    global $base;
    $secret = $store->setting('stripe_secret');
    $webhook = $store->setting('stripe_webhook_secret');

    return '<form class="panel" method="post" action="/admin/stripe">'.csrf()
        .'<label>Publishable key<input name="stripe_publishable" value="'.e($store->setting('stripe_publishable')).'" autocomplete="off"></label>'
        .'<label>Secret key<input name="stripe_secret" type="password" autocomplete="new-password" placeholder="'.($secret !== '' ? 'Saved. Leave blank to keep it.' : 'sk_...').'"></label>'
        .'<label>Webhook secret<input name="stripe_webhook_secret" type="password" autocomplete="new-password" placeholder="'.($webhook !== '' ? 'Saved. Leave blank to keep it.' : 'whsec_...').'"></label>'
        .'<p class="meta">Webhook URL<br>'.e($base.'/stripe/webhook').'<br>Listen for checkout.session.completed.</p>'
        .'<button type="submit">Save Stripe</button></form>';
}

function smtpForm(Store $store): string
{
    $encryption = $store->setting('smtp_encryption', 'tls');
    $options = '';
    foreach (['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None'] as $value => $label) {
        $options .= '<option value="'.$value.'"'.($encryption === $value ? ' selected' : '').'>'.$label.'</option>';
    }
    $password = $store->setting('smtp_password');

    return '<form class="panel" method="post" action="/admin/smtp">'.csrf()
        .'<label>Host<input name="smtp_host" value="'.e($store->setting('smtp_host')).'"></label>'
        .'<label>Port<input name="smtp_port" type="number" value="'.e($store->setting('smtp_port', '587')).'"></label>'
        .'<label>Encryption<select name="smtp_encryption">'.$options.'</select></label>'
        .'<label>Username<input name="smtp_username" value="'.e($store->setting('smtp_username')).'"></label>'
        .'<label>Password<input name="smtp_password" type="password" autocomplete="new-password" placeholder="'.($password !== '' ? 'Saved. Leave blank to keep it.' : '').'"></label>'
        .'<label>From address<input type="email" name="mail_from" value="'.e($store->setting('mail_from')).'"></label>'
        .'<label>From name<input name="mail_from_name" value="'.e($store->setting('mail_from_name', $store->catalogName())).'"></label>'
        .'<button type="submit">Save mail settings</button></form>'
        .'<form class="panel" method="post" action="/admin/smtp">'.csrf().'<input type="hidden" name="action" value="test">'
        .'<label>Send a test to<input type="email" name="test_email" required></label>'
        .'<button type="submit">Send test email</button></form>';
}

function filters(string $tier, string $type): string
{
    $html = '<div class="filters">';
    foreach (['all' => 'All prices', 'free' => 'Free', 'premium' => 'Premium'] as $key => $label) {
        $params = [];
        if (trim((string) ($_GET['q'] ?? '')) !== '') {
            $params['q'] = (string) $_GET['q'];
        }
        if ($type !== 'all') {
            $params['type'] = $type;
        }
        if ($key !== 'all') {
            $params['tier'] = $key;
        }
        $query = $params === [] ? '/' : '/?'.http_build_query($params);
        $html .= '<a href="'.$query.'"'.($tier === $key ? ' aria-current="page"' : '').'>'.e($label).'</a>';
    }

    return $html.'</div>';
}

/**
 * @param  array<string, mixed>  $product
 */
function visual(array $product): string
{
    if (($product['thumbnail'] ?? '') !== '') {
        return '<img src="'.e($product['thumbnail']).'" alt="'.e($product['name']).' preview" loading="lazy">';
    }
    if (is_string($product['screenshot'] ?? null)) {
        return '<img src="/media/'.e($product['slug']).'" alt="'.e($product['name']).' preview" loading="lazy">';
    }

    $hue = hexdec(substr(md5($product['slug']), 0, 2)) % 360;

    $theme = $product['type'] === 'theme';

    return '<span class="swatch '.($theme ? 'theme-preview' : 'extension-preview').'" style="--preview-hue:'.$hue.'"><span class="preview-symbol" aria-hidden="true">'.($theme ? '▦' : '✦').'</span><span class="preview-name">'.e($product['name']).'</span><span class="preview-label">'.($theme ? 'STOREFRONT THEME' : 'ECOMODULAR EXTENSION').'</span></span>';
}

if (! function_exists('e')) {
    function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
