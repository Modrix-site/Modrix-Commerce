<?php

use App\Core\Marketplace\Signing;

// Standalone integration test. Uses a temporary database; never touches catalog storage.
require dirname(__DIR__).'/src/Store.php';
require dirname(__DIR__).'/src/Licenses.php';

$directory = (getenv('CATALOG_TEST_TMP') ?: sys_get_temp_dir()).'/ecomodular-catalog-'.bin2hex(random_bytes(5));
mkdir($directory, 0775, true);
$store = new Store($directory);
$store->createAdmin('admin@example.test', 'Marketplace-test-8092!');
$listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
$address = stream_socket_get_name($listener, false);
$port = (int) substr($address, strrpos($address, ':') + 1);
fclose($listener);
$root = dirname(__DIR__).'/public';
$environment = array_merge(getenv(), ['CATALOG_STORAGE' => $directory]);
$process = proc_open([PHP_BINARY, '-S', '127.0.0.1:'.$port, '-t', $root, $root.'/router.php'], [0 => ['pipe', 'r'], 1 => ['file', $directory.'/server.log', 'a'], 2 => ['file', $directory.'/server.log', 'a']], $pipes, null, $environment);
if (! is_resource($process)) {
    throw new RuntimeException('Could not start the test server.');
}
$cookies = $directory.'/cookies';
$assertions = 0;

function check(bool $condition, string $message): void
{
    global $assertions;
    if (! $condition) {
        throw new RuntimeException($message);
    }
    $assertions++;
}

function request(string $path, ?array $data = null, bool $json = false): array
{
    global $port, $cookies;
    $curl = curl_init('http://127.0.0.1:'.$port.$path);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $cookies, CURLOPT_COOKIEJAR => $cookies, CURLOPT_TIMEOUT => 10, CURLOPT_HEADER => true]);
    if ($data !== null) {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $json ? json_encode($data) : $data);
        if ($json) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }
    }
    $result = curl_exec($curl);
    if (! is_string($result)) {
        throw new RuntimeException(curl_error($curl));
    }
    $size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $response = ['status' => curl_getinfo($curl, CURLINFO_RESPONSE_CODE), 'headers' => substr($result, 0, $size), 'body' => substr($result, $size)];
    curl_close($curl);

    return $response;
}

function form(string $path): string
{
    $response = request($path);
    check($response['status'] === 200, 'GET '.$path.' should render');
    preg_match('/name="_token" value="([a-f0-9]+)"/', $response['body'], $match);

    return $match[1] ?? throw new RuntimeException('Missing CSRF token on '.$path);
}

try {
    check($store->currency() === 'USD', 'New marketplaces default to USD');
    for ($i = 0; $i < 40; $i++) {
        $socket = @fsockopen('127.0.0.1', $port, $errno, $error, .1);
        if ($socket) {
            fclose($socket);
            break;
        }
        usleep(100000);
    }
    check(request('/admin/repository')['status'] === 302, 'Admin repository requires login');
    check(request('/admin/categories')['status'] === 302, 'Category management requires admin login');
    check(request('/submit')['status'] === 302, 'Publisher submissions require login');
    check(request('/admin/login', ['email' => 'admin@example.test', 'password' => 'Marketplace-test-8092!'])['status'] === 419, 'Missing CSRF rejected');
    $token = form('/admin/login');
    check(request('/admin/login', ['_token' => $token, 'email' => 'admin@example.test', 'password' => 'Marketplace-test-8092!'])['status'] === 302, 'Admin login works');
    foreach (['/admin', '/admin/repository', '/admin/submissions', '/admin/general', '/admin/catalog'] as $path) {
        check(request($path)['status'] === 200, $path.' should render');
    }
    $categoryToken = form('/admin/categories');
    check(request('/admin/categories', ['_token' => $categoryToken, 'slug' => 'test-design', 'name' => 'Test design', 'type' => 'theme', 'description' => 'Themes for test stores.', 'position' => '1', 'enabled' => '1'])['status'] === 302, 'Admin creates categories');
    check(in_array('test-design', array_column($store->categories(), 'slug'), true), 'Created category is available for submissions');
    check(str_contains(request('/admin/repository/new')['body'], 'name="demo_url"'), 'Admin listing editor includes demo URL');
    check(str_contains(request('/submit')['body'], 'Live demo URL') === false, 'Unauthenticated publishers cannot access submission fields');
    $general = request('/admin/general')['body'];
    check(str_contains($general, 'name="logo"') && ! str_contains($general, 'name="smtp_host"') && ! str_contains($general, 'name="stripe_secret"'), 'Default settings show only the branding tab');
    $payments = request('/admin/general?tab=payments')['body'];
    check(str_contains($payments, 'name="stripe_secret"') && ! str_contains($payments, 'name="logo"'), 'Payments tab renders only payment settings');
    $email = request('/admin/general?tab=email')['body'];
    check(str_contains($email, 'name="smtp_host"') && ! str_contains($email, 'name="stripe_secret"'), 'Email tab renders only email settings');
    $licensing = request('/admin/general?tab=licensing')['body'];
    check(str_contains($licensing, 'Public signing key') && ! str_contains($licensing, 'name="smtp_host"'), 'Licensing tab renders only license settings');
    check(str_contains(request('/admin/general?tab=invalid')['body'], 'data-settings-panel="branding"'), 'Unknown settings tabs fall back to branding');
    $store->put('stripe_secret', 'sk_test_private_fixture');
    $store->put('smtp_password', 'private-fixture');
    check(! str_contains(request('/admin/general?tab=payments')['body'], 'private_fixture') && ! str_contains(request('/admin/general?tab=email')['body'], 'private-fixture'), 'Saved secrets never rendered');
    $token = form('/admin/general');
    $paymentSave = request('/admin/stripe', ['_token' => $token, 'stripe_publishable' => 'pk_test_fixture', 'stripe_secret' => '', 'stripe_webhook_secret' => '']);
    check(str_contains($paymentSave['headers'], '/admin/general?tab=payments'), 'Saving payment settings returns to the payments tab');
    check($store->setting('stripe_secret') === 'sk_test_private_fixture', 'Blank secret preserves existing value');
    $badImage = $directory.'/bad.svg';
    file_put_contents($badImage, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
    request('/admin/general', ['_token' => $token, 'catalog_name' => 'Fixture marketplace', 'currency' => 'USD', 'logo' => new CURLFile($badImage, 'image/svg+xml', 'logo.svg')]);
    check($store->setting('logo') === '', 'Executable SVG upload rejected');
    $image = $directory.'/preview.png';
    file_put_contents($image, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
    request('/admin/general', ['_token' => $token, 'catalog_name' => 'Fixture marketplace', 'currency' => 'USD', 'logo' => new CURLFile($image, 'image/png', 'logo.png')]);
    check(str_starts_with($store->setting('logo'), '/uploads/'), 'Logo upload persists');
    check(request($store->setting('logo'))['status'] === 200, 'Logo is served');
    request('/admin/logout', ['_token' => $token]);
    $token = form('/account/register');
    $registered = request('/account/register', ['_token' => $token, 'name' => 'Fixture publisher', 'email' => 'publisher@example.test', 'password' => 'Publisher-test-8092!', 'password_confirmation' => 'does not match']);
    check(str_contains($registered['headers'], '/account/register'), 'Registration requires matching passwords');
    request('/account/register', ['_token' => $token, 'name' => 'Fixture publisher', 'email' => 'publisher@example.test', 'password' => 'Publisher-test-8092!', 'password_confirmation' => 'Publisher-test-8092!']);
    $licenseService = new Licenses($store);
    $token = form('/account');
    $account = request('/account?product=ecomodular-essence')['body'];
    check(str_contains($account, 'data-license-search required'), 'License selector supports search with a required native fallback');
    check(str_contains($account, 'value="ecomodular-essence" selected'), 'Catalog links preselect the requested eligible package');
    check(request('/account/licenses', ['product' => 'ecomodular-essence'])['status'] === 419, 'License generation requires CSRF');
    request('/account/licenses', ['_token' => $token, 'product' => 'ecomodular-essence']);
    $license = $licenseService->forCustomer(1)[0];
    check((bool) preg_match('/^EMK-(?:[A-Z2-9]{5}-){3}[A-Z2-9]{5}$/', $license['license_key']), 'Free package license generated');
    request('/account/licenses', ['_token' => $token, 'product' => 'ecomodular-essence']);
    check(count($licenseService->forCustomer(1)) === 1 && $licenseService->forCustomer(1)[0]['license_key'] === $license['license_key'], 'Repeated generation returns stable key');
    check(! str_contains(request('/account')['body'], '<option value="ecomodular-essence"'), 'Already licensed packages are excluded from search choices');
    $store->saveOffers([['extension' => 'modrixcommerce/circuit', 'tier' => 'premium', 'price' => '149.00']]);
    request('/account/licenses', ['_token' => $token, 'product' => 'ecomodular-circuit']);
    check(count($licenseService->forCustomer(1)) === 1, 'Unpurchased paid license refused');
    $paidDetail = request('/item/ecomodular-circuit')['body'];
    check(str_contains($paidDetail, '>Buy now</button>') && ! str_contains($paidDetail, 'Buy with Stripe'), 'Paid items use provider-neutral Buy now checkout');
    check(str_contains($paidDetail, 'Purchase this item first to get a license key in your account.'), 'Paid item details explain the purchase requirement for licenses');
    check(str_contains(request('/?tier=premium')['body'], 'Purchase first to get a license key.'), 'Paid catalog cards explain the license purchase requirement');
    check(! str_contains(request('/item/ecomodular-essence')['body'], 'Purchase this item first'), 'Free item details do not require a purchase');
    check(! str_contains(request('/account')['body'], '<option value="ecomodular-circuit"'), 'Unpurchased paid packages are excluded from search choices');
    $apiInput = ['key' => $license['license_key'], 'instance_id' => 'test-store-instance-1111', 'domain' => 'shop.example.test'];
    check(request('/api/marketplace/v1/licenses/activate')['status'] === 405, 'License API requires POST');
    $response = request('/api/marketplace/v1/licenses/activate', $apiInput, true);
    $signed = json_decode($response['body'], true);
    check($response['status'] === 200 && $signed['token']['activated'], 'Store can activate account-generated key');
    check(Signing::verify($signed['token'], $signed['signature'], [$licenseService->publicKey()]), 'Store verifier accepts license signature');
    $tampered = $signed['token'];
    $tampered['extension'] = 'other/package';
    check(! Signing::verify($tampered, $signed['signature'], [$licenseService->publicKey()]), 'Tampered token rejected');
    check($signed['token']['instance_id'] === $apiInput['instance_id'] && $signed['token']['extension'] === 'modrixcommerce/essence', 'Token binds correct package and store');
    check(request('/api/marketplace/v1/licenses/activate', $apiInput, true)['status'] === 200, 'Reactivation on same store is idempotent');
    $otherStore = array_replace($apiInput, ['instance_id' => 'test-store-instance-2222']);
    check(request('/api/marketplace/v1/licenses/activate', $otherStore, true)['status'] === 403, 'Second store exceeds activation limit');
    check(json_decode(request('/api/marketplace/v1/licenses/check', $apiInput, true)['body'], true)['token']['activated'], 'Check confirms active installation');
    $update = json_decode(request('/api/marketplace/v1/updates', $apiInput, true)['body'], true);
    check(($update['available'] ?? false) === true && Signing::verify($update['release']['verification']['metadata'], $update['release']['verification']['signature'], [$licenseService->publicKey()]), 'Activated license receives a signed installable release');
    $download = json_decode(request('/api/marketplace/v1/downloads', $apiInput + ['release_id' => $update['release']['id']], true)['body'], true);
    $downloadPath = parse_url((string) ($download['url'] ?? ''), PHP_URL_PATH);
    check(is_string($downloadPath) && request($downloadPath)['status'] === 200, 'Store can pull the signed package');
    check(request($downloadPath)['status'] === 403, 'Package pull link works once');
    check(request('/api/marketplace/v1/licenses/check', array_replace($apiInput, ['key' => 'EMK-invalid']), true)['status'] === 404, 'Unknown key refused');
    request('/account/licenses/release', ['_token' => $token, 'license_id' => $license['id']]);
    check(! json_decode(request('/api/marketplace/v1/licenses/check', $apiInput, true)['body'], true)['token']['activated'], 'Owner can release store connection');
    check(request('/api/marketplace/v1/licenses/activate', $otherStore, true)['status'] === 200, 'Released license can move to another store');
    $token = form('/submit');
    $data = ['_token' => $token, 'name' => 'Workflow Theme', 'extension' => 'test/workflow', 'summary' => 'A tested theme submission.', 'type' => 'theme', 'version' => '1.0.0', 'platform_version' => '^1.0', 'author' => 'Fixture publisher', 'license' => 'MIT', 'tier' => 'premium', 'price' => '29.50', 'repository_url' => 'https://github.com/example/workflow', 'demo_url' => 'https://demo.example.test/workflow', 'categories[0]' => 'test-design', 'features_text' => "Flexible layouts\nMobile-friendly storefront"];
    check(str_contains(request('/submit')['body'], 'name="demo_url"'), 'Publisher submission includes demo URL');
    $invalidDemo = array_replace($data, ['demo_url' => 'javascript:alert(1)']);
    check(request('/submit', $invalidDemo)['status'] === 200 && count($store->submissions()) === 0, 'Unsafe demo URL is rejected');
    $invalidDemo['demo_url'] = 'https://user:pass@example.test/demo';
    check(request('/submit', $invalidDemo)['status'] === 200 && count($store->submissions()) === 0, 'Demo URLs cannot embed credentials');
    check(request('/submit', array_replace($data, ['categories[0]' => 'payments']))['status'] === 200 && count($store->submissions()) === 0, 'Theme cannot use extension-only categories');
    $archive = $directory.'/package.zip';
    $zip = new ZipArchive;
    $zip->open($archive, ZipArchive::CREATE);
    $zip->addFromString('theme.json', json_encode(['name' => 'test/workflow', 'version' => '1.0.0']));
    $zip->addFromString('../unsafe.php', '<?php echo "unsafe";');
    $zip->close();
    request('/submit', $data + ['package' => new CURLFile($archive, 'application/zip', 'package.zip')]);
    check(count($store->submissions()) === 0, 'ZIP traversal path rejected');
    $zip->open($archive);
    $zip->deleteName('../unsafe.php');
    $zip->close();
    $invalid = $data;
    $invalid['repository_url'] = 'javascript:alert(1)';
    check(request('/submit', $invalid)['status'] === 200, 'Invalid source URL stays on form');
    check(count($store->submissions()) === 0, 'Invalid submission not saved');
    $invalid = $data;
    $invalid['price'] = '-4';
    request('/submit', $invalid);
    check(count($store->submissions()) === 0, 'Negative paid price rejected');
    check(request('/submit', $data + ['thumbnail' => new CURLFile($image, 'image/png', 'preview.png'), 'package' => new CURLFile($archive, 'application/zip', 'package.zip')])['status'] === 302, 'Valid submission accepted');
    $row = $store->submissions()[0];
    check($row['status'] === 'pending', 'Submission awaits approval');
    check(! str_contains(request('/api/marketplace/v1/catalog')['body'], 'test/workflow'), 'Pending submission hidden from public API');
    check(request('/download/test-workflow')['status'] === 404, 'Pending package cannot be downloaded');
    check(request('/admin/submissions/'.$row['id'].'/download')['status'] === 302, 'Review downloads require admin');
    request('/submit', $data);
    check(count($store->submissions()) === 1, 'Duplicate pending identifier rejected');
    check(str_contains(request('/submissions')['body'], 'Workflow Theme'), 'Publisher sees own submission');
    request('/account/logout', ['_token' => $token]);
    $token = form('/account/register');
    request('/account/register', ['_token' => $token, 'name' => 'Other publisher', 'email' => 'other@example.test', 'password' => 'Publisher-test-8092!', 'password_confirmation' => 'Publisher-test-8092!']);
    check(! str_contains(request('/submissions')['body'], 'Workflow Theme'), 'Other publishers cannot see submission');
    check(! str_contains(request('/account')['body'], $license['license_key']), 'Other accounts cannot view keys');
    request('/account/licenses/release', ['_token' => $token, 'license_id' => $license['id']]);
    check(json_decode(request('/api/marketplace/v1/licenses/check', $otherStore, true)['body'], true)['token']['activated'], 'Other account cannot release license');
    check(json_decode(request('/api/marketplace/v1/licenses/deactivate', $otherStore, true)['body'], true)['deactivated'], 'Store API deactivates its own connection');
    check(! json_decode(request('/api/marketplace/v1/licenses/check', $otherStore, true)['body'], true)['token']['activated'], 'Deactivation reflected in signed token');
    $token = form('/admin/login');
    request('/admin/login', ['_token' => $token, 'email' => 'admin@example.test', 'password' => 'Marketplace-test-8092!']);
    $token = form('/admin/submissions');
    check(request('/admin/submissions/'.$row['id'].'/download')['status'] === 200, 'Admin can download ZIP for review');
    request('/admin/submissions', ['_token' => $token, 'id' => $row['id'], 'decision' => 'rejected', 'review_note' => '']);
    check($store->submissions()[0]['status'] === 'pending', 'Rejection requires a reason');
    request('/admin/submissions', ['_token' => $token, 'id' => $row['id'], 'decision' => 'approved', 'review_note' => 'Approved after source review.']);
    check($store->submissions()[0]['status'] === 'approved', 'Approval recorded');
    $api = json_decode(request('/api/marketplace/v1/catalog')['body'], true);
    $product = array_values(array_filter($api['products'], fn ($p) => $p['extension'] === 'test/workflow'))[0] ?? null;
    check($product !== null && $product['price']['amount'] === '29.50' && $product['free'] === false, 'Approved paid listing published with correct price');
    check(str_contains($product['thumbnail'], '/uploads/'), 'Thumbnail available in catalog API');
    check($product['demo_url'] === $data['demo_url'] && $product['categories'][0]['slug'] === 'test-design', 'Approval publishes demo and category metadata to API');
    $detailPage = request('/item/test-workflow')['body'];
    check(str_contains($detailPage, 'View live demo') && str_contains($detailPage, 'https://demo.example.test/workflow'), 'Package details expose the published demo');
    check(str_contains($detailPage, 'Flexible layouts') && str_contains($detailPage, 'Mobile-friendly storefront'), 'Package details show submitted feature list');
    check(str_contains($detailPage, 'More themes to explore'), 'Package details show related packages');
    check(str_contains(request('/?category=test-design&demo=1')['body'], 'Workflow Theme') && ! str_contains(request('/?category=test-design&demo=1')['body'], 'AI Content Assistant'), 'Category and demo filters work together');
    $categoryToken = form('/admin/categories');
    request('/admin/categories', ['_token' => $categoryToken, 'original_slug' => 'test-design', 'slug' => 'test-design', 'name' => 'Renamed design', 'type' => 'theme', 'position' => '1', 'enabled' => '1']);
    check(str_contains(request('/?category=test-design')['body'], 'Renamed design'), 'Admin category name changes reach the catalog');
    request('/admin/categories', ['_token' => $categoryToken, 'original_slug' => 'test-design', 'slug' => 'test-design', 'name' => 'Renamed design', 'type' => 'theme', 'position' => '1']);
    check(! str_contains(request('/item/test-workflow')['body'], 'Renamed design'), 'Hidden categories disappear from public package tags');
    request('/admin/categories', ['_token' => $categoryToken, 'original_slug' => 'test-design', 'slug' => 'test-design', 'name' => 'Renamed design', 'type' => 'theme', 'position' => '1', 'enabled' => '1']);
    check(str_contains(request('/?category=test-design')['body'], 'Workflow Theme'), 'Restoring category restores its existing assignments');
    check(request('/download/test-workflow')['status'] === 403, 'Paid download denied without ownership');
    $paidOrder = $store->fulfill(2, 'test/workflow', 'Workflow Theme', '29.50', 'USD', 'cs_test_fixture');
    check(request('/api/marketplace/v1/licenses/activate', array_replace($apiInput, ['key' => $paidOrder['license_key']]), true)['status'] === 200, 'Existing paid order key activates before visiting account');
    request('/account/licenses', ['_token' => $token, 'product' => 'test-workflow']);
    check($licenseService->forCustomer(2)[0]['license_key'] === $paidOrder['license_key'], 'Paid license reuses purchased key');
    check(request('/api/marketplace/v1/licenses/activate', array_replace($apiInput, ['key' => $paidOrder['license_key']]), true)['status'] === 200, 'Paid key works with store activation');
    check(request('/download/test-workflow')['status'] === 200, 'Paid download available to owner');
    request('/admin/submissions', ['_token' => $token, 'id' => $row['id'], 'decision' => 'rejected', 'review_note' => 'Second review']);
    check($store->submissions()[0]['status'] === 'approved', 'Reviewed submission cannot be processed twice');
    $data['status'] = 'draft';
    request('/admin/repository/test-workflow', $data);
    check(! str_contains(request('/api/marketplace/v1/catalog')['body'], 'test/workflow'), 'Draft removed from API');
    check(request('/item/test-workflow')['status'] === 404, 'Draft detail hidden');
    $data['tier'] = 'free';
    $data['status'] = 'published';
    request('/admin/repository/test-workflow', $data);
    $api = json_decode(request('/api/marketplace/v1/catalog')['body'], true);
    $product = array_values(array_filter($api['products'], fn ($p) => $p['extension'] === 'test/workflow'))[0];
    check($product['free'] && $product['price']['amount'] === '0.00', 'Paid listing can be converted to free');
    request('/account/logout', ['_token' => $token]);
    check(request('/download/test-workflow')['status'] === 200, 'Free ZIP available without purchase');
    check(str_contains(request('/?q=Workflow')['body'], 'Workflow Theme') && ! str_contains(request('/?q=Workflow')['body'], 'AI Content Assistant'), 'Public search filters results');
    $categoryToken = form('/admin/categories');
    request('/admin/categories', ['_token' => $categoryToken, 'slug' => 'test-design', 'name' => 'Duplicate', 'type' => 'theme', 'position' => '1', 'enabled' => '1']);
    check(array_column($store->categories(), 'name', 'slug')['test-design'] === 'Renamed design', 'Duplicate category slug does not overwrite an existing category');
    check(str_contains(request('/?q=Workflow&type=module')['body'], 'No matching packages'), 'Package type filtering composes with search');
    $published = array_values(array_filter($store->repository([]), fn ($p) => $p['extension'] === 'test/workflow'))[0];
    $published = array_replace($published, ['featured' => true]);
    $store->saveListing($published);
    check(str_contains(request('/?q=Workflow')['body'], 'featured-badge'), 'Admins can feature packages in discovery');
    $metadataUpdate = $store->applyUpdate(['extension' => 'test/workflow', 'version' => '1.1.0', 'package' => $published['package'], 'changelog' => 'Metadata retention fixture.'], [$published]);
    check($metadataUpdate['demo_url'] === $data['demo_url'] && in_array('test-design', $metadataUpdate['categories'], true) && count($metadataUpdate['features']) === 2 && $metadataUpdate['featured'], 'Version updates retain demo, categories, featured status and key features');
    $data['extension'] = 'test/rejected';
    $id = $store->submit(1, array_replace($row['product'], ['extension' => 'test/rejected', 'slug' => 'test-rejected']), $store->repository([]));
    $store->review($id, 'rejected', 'Please add documentation.', 1, $store->repository([]));
    check($store->submissions()[0]['status'] === 'rejected', 'Rejection recorded');
    check(! str_contains(request('/api/marketplace/v1/catalog')['body'], 'test/rejected'), 'Rejected package never published');
    echo $assertions." assertions passed.\n";
} finally {
    proc_terminate($process);
    proc_close($process);
    // Leave isolated fixture files available for diagnosing a failing test.
    echo 'Test storage: '.$directory."\n";
}
