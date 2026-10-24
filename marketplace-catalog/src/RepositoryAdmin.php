<?php

/** Repository management and moderated publisher submissions for the standalone catalog. */
function repositoryAdmin(string $path, Store $store, array $products): bool
{
    if ($path === '/admin/categories') {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            guard();
            try {
                $store->saveCategory($_POST);
                flash('Category saved. Hidden categories keep their package assignments.');
                catalog_redirect('/admin/categories');
            } catch (RuntimeException $exception) {
                flash($exception->getMessage());
            }
        }
        settings('Categories', categoryAdmin($store));

        return true;
    }
    if (preg_match('#^/admin/submissions/(\d+)/download$#D', $path, $match)) {
        foreach ($store->submissions() as $row) {
            if ((int) $row['id'] === (int) $match[1]) {
                sendCatalogPackage($row['product']['package'] ?? '', $row['product']['slug']);

                return true;
            }
        }
        http_response_code(404);

        return true;
    }
    if ($path === '/admin') {
        $pending = count(array_filter($store->submissions(), fn ($row) => $row['status'] === 'pending'));
        $themes = count(array_filter($products, fn ($row) => $row['type'] === 'theme'));
        $paid = count(array_filter($products, fn ($row) => $row['premium']));
        $stats = '';
        foreach (['Repository packages' => count($products), 'Themes' => $themes, 'Paid packages' => $paid, 'Awaiting review' => $pending] as $label => $value) {
            $stats .= '<div class="stat"><span>'.e($label).'</span><strong>'.$value.'</strong></div>';
        }
        $html = '<div class="welcome"><div><span class="eyebrow">YOUR MARKETPLACE, YOUR WAY</span><h2>A home for great store experiences.</h2><p>Curate your collection, support your publishers, and make every listing shine.</p><a class="button" href="/admin/repository">Manage repository →</a></div><span class="welcome-art" aria-hidden="true">✦</span></div><div class="stats">'.$stats.'</div>';
        $html .= '<div class="section-heading"><h2>Repository highlights</h2><a href="/admin/repository">View all →</a></div>'.grid(array_slice($products, 0, 6));
        settings('Overview', $html);

        return true;
    }
    if ($path === '/admin/repository') {
        $type = (string) ($_GET['type'] ?? 'all');
        $q = trim((string) ($_GET['q'] ?? ''));
        $visible = array_filter($products, fn ($p) => ($type === 'all' || $p['type'] === $type) && ($q === '' || stripos($p['name'].' '.$p['extension'], $q) !== false));
        $html = '<form class="search-row" method="get"><input name="q" aria-label="Search repository" placeholder="Search packages, names, or publishers…" value="'.e($q).'"><select name="type" aria-label="Package type">';
        foreach (['all' => 'All packages', 'theme' => 'Themes', 'module' => 'Extensions'] as $value => $label) {
            $html .= '<option value="'.$value.'"'.($type === $value ? ' selected' : '').'>'.$label.'</option>';
        }
        $html .= '</select><button>Search</button><a class="button secondary" href="/admin/repository/new">+ Add package</a></form><div class="table-scroll"><table><thead><tr><th>Package</th><th>Type / version</th><th>Price</th><th>Visibility</th><th></th></tr></thead><tbody>';
        foreach ($visible as $p) {
            $html .= '<tr><td><div class="package-cell">'.visual($p).'<div><strong>'.e($p['name']).'</strong><small>'.e($p['extension']).'</small></div></div></td><td>'.($p['type'] === 'theme' ? 'Theme' : 'Extension').'<small>v'.e($p['version']).'</small></td><td>'.e($p['price_label']).'</td><td><span class="status '.e($p['status']).'">'.e(ucfirst($p['status'])).'</span></td><td><a href="/admin/repository/'.e($p['slug']).'">Edit →</a></td></tr>';
        }
        if ($visible === []) {
            $html .= '<tr><td colspan="5" class="empty">No packages match your search.</td></tr>';
        }
        settings('Repository', $html.'</tbody></table></div>');

        return true;
    }
    if (preg_match('#^/admin/repository/([a-z0-9-]+)$#', $path, $match)) {
        $new = $match[1] === 'new';
        $product = null;
        foreach ($products as $p) {
            if ($p['slug'] === $match[1]) {
                $product = $p;
            }
        }
        if (! $new && $product === null) {
            http_response_code(404);
            settings('Package not found', '<p class="empty">This package does not exist.</p>');

            return true;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            guard();
            try {
                $data = listingInput($product);
                if ($new && in_array($data['slug'], array_column($products, 'slug'), true)) {
                    throw new RuntimeException('That package identifier already exists.');
                }
                $data['status'] = field('status') === 'published' ? 'published' : 'draft';
                $data['source'] = $product['source'] ?? 'manual';
                $store->db->beginTransaction();
                $store->saveListing($data);
                $store->saveOffers([['extension' => $data['extension'], 'tier' => $data['tier'], 'price' => $data['price']]]);
                $store->db->commit();
                flash('Package saved.');
                catalog_redirect('/admin/repository/'.$data['slug']);
            } catch (RuntimeException $exception) {
                if ($store->db->inTransaction()) {
                    $store->db->rollBack();
                }
                flash($exception->getMessage());
                // Keep entered values after validation errors.
                $product = array_replace($product ?? [], array_intersect_key($_POST, array_flip(['name', 'summary', 'extension', 'type', 'version', 'platform_version', 'author', 'license', 'repository_url', 'demo_url', 'categories', 'features_text', 'tier', 'price', 'status'])));
            }
        }
        settings($new ? 'Add package' : 'Edit package', listingForm($product, $path, true, ! $new));

        return true;
    }
    if ($path === '/admin/submissions') {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            guard();
            try {
                $published = $store->review((int) field('id'), field('decision'), field('review_note'), (int) $_SESSION['admin_id'], $products);
                if (is_array($published)) {
                    (new Licenses($store))->publishPackage($published);
                }
                flash('Submission '.field('decision').'.');
            } catch (RuntimeException $exception) {
                flash($exception->getMessage());
            }
            catalog_redirect('/admin/submissions');
        }
        $html = '<p class="lead">Review the source repository, listing information, price, and thumbnail before publishing. Submitted code is never installed automatically.</p>';
        foreach ($store->submissions() as $row) {
            $p = $row['product'];
            $kind = ($p['kind'] ?? '') === 'update' ? 'Update to v'.e($p['version']) : 'v'.e($p['version']);
            $html .= '<article class="review-card"><div class="review-preview">'.visual($p).'</div><div><div class="section-heading"><h2>'.e($p['name']).'</h2><span class="status '.e($row['status']).'">'.e(ucfirst($row['status'])).'</span></div><p>'.e($p['summary']).'</p>'.(! empty($p['changelog']) ? '<p>'.nl2br(e($p['changelog'])).'</p>' : '').'<p class="meta">'.e($p['extension']).' · '.$kind.' · '.($p['type'] === 'theme' ? 'Theme' : 'Extension').' · '.e(($p['tier'] ?? '') === 'premium' ? $store->money((string) $p['price']) : 'Free').'</p><p class="meta">Submitted by '.e($row['submitter']).' ('.e($row['email']).') on '.e(substr($row['created_at'], 0, 10)).'</p>';
            if ($p['repository_url'] !== '') {
                $html .= '<a href="'.e($p['repository_url']).'" target="_blank" rel="noopener noreferrer">Review source repository ↗</a> ';
            }
            if (! empty($p['demo_url'])) {
                $html .= '<a href="'.e($p['demo_url']).'" target="_blank" rel="noopener noreferrer">Review live demo ↗</a> ';
            }
            if (! empty($p['package'])) {
                $html .= '<a href="/admin/submissions/'.$row['id'].'/download">Download ZIP for review ↓</a>';
            }
            if ($row['status'] === 'pending') {
                $html .= '<form method="post" class="review-actions">'.csrf().'<input type="hidden" name="id" value="'.$row['id'].'"><label>Review note<textarea name="review_note" maxlength="2000" placeholder="Required when rejecting; visible to the publisher"></textarea></label><div class="actions"><button name="decision" value="approved">Approve & publish</button><button class="danger" name="decision" value="rejected">Reject submission</button></div></form>';
            } else {
                $html .= '<p class="meta">Reviewed '.e(substr((string) $row['reviewed_at'], 0, 10)).'</p><p>'.nl2br(e($row['review_note'])).'</p>';
            }
            $html .= '</div></article>';
        }
        if ($store->submissions() === []) {
            $html .= '<div class="empty-state"><h2>You’re all caught up</h2><p>New themes and extensions will appear here for your review.</p><a href="/submit">View publisher submission form →</a></div>';
        }
        settings('Submissions & approvals', $html);

        return true;
    }
    if ($path === '/admin/updates') {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            guard();
            try {
                $selected = null;
                foreach ($products as $product) {
                    if ($product['extension'] === field('extension')) {
                        $selected = $product;
                    }
                }
                if ($selected === null) {
                    throw new RuntimeException('Choose a theme or extension that is already in the marketplace.');
                }
                $published = $store->applyUpdate(updateInput($selected), $products);
                (new Licenses($store))->publishPackage($published);
                flash('Version '.$published['version'].' of '.$published['name'].' is published. Stores install it the next time they check for updates.');
            } catch (RuntimeException $exception) {
                flash($exception->getMessage());
            }
            catalog_redirect('/admin/updates');
        }
        settings('Submit an update', '<p class="lead">Upload a newer ZIP for a theme or extension that is already listed. The manifest version must match. Connected stores install it when they check for updates.</p>'.updateForm($products, '/admin/updates'));

        return true;
    }

    return false;
}

function listingInput(?array $existing = null): array
{
    global $store;
    $extension = $existing['extension'] ?? field('extension');
    if (strlen($extension) > 160 || ! preg_match('#^[a-z0-9]+(?:-[a-z0-9]+)*/[a-z0-9]+(?:-[a-z0-9]+)*$#D', $extension)) {
        throw new RuntimeException('Use a package identifier in publisher/package-name format.');
    }
    $name = field('name');
    $summary = field('summary');
    $type = $existing['type'] ?? field('type');
    $metadata = listingMetadata($store, $type, $existing);
    $version = field('version');
    $url = field('repository_url');
    $tier = field('tier');
    $price = field('price');
    if ($name === '' || strlen($name) > 120 || $summary === '' || strlen($summary) > 4000 || ! in_array($type, ['theme', 'module'], true)) {
        throw new RuntimeException('Provide a name (up to 120 characters), description (up to 4,000 characters), and a package type.');
    }
    if (! preg_match('/^\d+\.\d+\.\d+(?:-[a-zA-Z0-9.-]+)?$/D', $version)) {
        throw new RuntimeException('Use a version such as 1.0.0.');
    }
    if ($url !== '' && (! filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null)) {
        throw new RuntimeException('Use a valid HTTPS repository URL without embedded credentials.');
    }
    if (! in_array($tier, ['free', 'premium'], true) || ($tier === 'premium' && (! preg_match('/^\d{1,7}(?:\.\d{1,2})?$/D', $price) || (float) $price < 0.5))) {
        throw new RuntimeException('Select Free or Paid. Paid packages need a price of at least 0.50.');
    }
    $thumbnail = ($existing['thumbnail'] ?? '');
    if (field('remove_thumbnail') === '1') {
        $thumbnail = '';
    }
    $thumbnail = catalogUpload('thumbnail') ?? $thumbnail;
    $package = catalogPackage($extension, $type, $version) ?? ($existing['package'] ?? '');
    if ($url === '' && $package === '' && ($existing['source'] ?? '') !== 'local') {
        throw new RuntimeException('Provide a source repository URL or upload a package ZIP.');
    }

    return $metadata + ['extension' => $extension, 'slug' => str_replace('/', '-', $extension), 'name' => $name, 'summary' => $summary, 'type' => $type, 'version' => $version, 'platform_version' => substr(field('platform_version') ?: '^1.0', 0, 80), 'author' => substr(field('author'), 0, 120), 'license' => substr(field('license') ?: 'MIT', 0, 80), 'repository_url' => $url, 'thumbnail' => $thumbnail, 'package' => $package, 'screenshot' => $existing['screenshot'] ?? null, 'tier' => $tier, 'price' => $tier === 'premium' ? number_format((float) $price, 2, '.', '') : '0.00'];
}

/** Packages remain private and are never extracted or executed by the marketplace. */
function catalogPackage(string $extension, string $type, string $version): ?string
{
    $file = $_FILES['package'] ?? null;
    if (! is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 20 * 1024 * 1024 || ! is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Upload a ZIP up to 20 MB, within your PHP upload limits.');
    }
    $zip = new ZipArchive;
    if ($zip->open($file['tmp_name']) !== true) {
        throw new RuntimeException('The package is not a valid ZIP archive.');
    }
    try {
        $bytes = 0;
        if ($zip->numFiles > 10000) {
            throw new RuntimeException('The package contains too many files.');
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->statIndex($i);
            $name = str_replace('\\', '/', $entry['name']);
            $bytes += $entry['size'];
            $zip->getExternalAttributesIndex($i, $os, $attributes);
            $symlink = (($attributes >> 16) & 0170000) === 0120000;
            if ($symlink || str_starts_with($name, '/') || preg_match('#(^|/)\.\.(/|$)|^[a-zA-Z]:#', $name) || $bytes > 100 * 1024 * 1024) {
                throw new RuntimeException('The package contains unsafe paths or exceeds 100 MB unpacked.');
            }
        }
        $manifestName = $type === 'theme' ? 'theme.json' : 'module.json';
        $manifestStat = $zip->statName($manifestName);
        if (! $manifestStat || $manifestStat['size'] > 65536) {
            throw new RuntimeException('Include '.$manifestName.' at the ZIP root (up to 64 KB).');
        }
        $manifest = json_decode((string) $zip->getFromName($manifestName), true);
        if (! is_array($manifest) || ($manifest['name'] ?? '') !== $extension || ($manifest['version'] ?? '') !== $version) {
            throw new RuntimeException('The ZIP manifest package identifier and version must match the listing.');
        }
    } finally {
        $zip->close();
    }
    $directory = catalogStorage().'/packages';
    if (! is_dir($directory)) {
        mkdir($directory, 0775, true);
    }
    $filename = bin2hex(random_bytes(16)).'.zip';
    if (! move_uploaded_file($file['tmp_name'], $directory.'/'.$filename)) {
        throw new RuntimeException('The package could not be saved.');
    }

    return $filename;
}

/** Raster uploads are stored outside the public directory and served with a fixed MIME type. */
function catalogUpload(string $name): ?string
{
    $file = $_FILES[$name] ?? null;
    if (! is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 3 * 1024 * 1024 || ! is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Upload a PNG, JPEG, or WebP image smaller than 3 MB.');
    }
    $info = @getimagesize($file['tmp_name']);
    $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
    if (! is_array($info) || ! isset($extensions[$info['mime']]) || $info[0] > 8000 || $info[1] > 8000) {
        throw new RuntimeException('Use a PNG, JPEG, or WebP image no larger than 8000 × 8000 pixels.');
    }
    $directory = catalogStorage().'/uploads';
    if (! is_dir($directory)) {
        mkdir($directory, 0775, true);
    }
    $filename = bin2hex(random_bytes(16)).'.'.$extensions[$info['mime']];
    if (! move_uploaded_file($file['tmp_name'], $directory.'/'.$filename)) {
        throw new RuntimeException('The image could not be saved.');
    }

    return '/uploads/'.$filename;
}

function listingForm(?array $p, string $action, bool $admin, bool $locked = false): string
{
    global $store;
    $p ??= [];
    $p += ['name' => '', 'extension' => '', 'summary' => '', 'type' => 'theme', 'version' => '1.0.0', 'platform_version' => '^1.0', 'author' => '', 'license' => 'MIT', 'repository_url' => '', 'thumbnail' => '', 'status' => 'draft', 'tier' => ! empty($p['premium']) ? 'premium' : 'free', 'price' => '0.00'];
    $html = '<form class="panel wide" method="post" enctype="multipart/form-data" action="'.e($action).'">'.csrf().'<div class="form-heading"><h2>Package information</h2><p class="meta">Everything customers need to discover your work.</p></div><div class="form-grid">';
    foreach (['name' => 'Display name', 'extension' => 'Package identifier', 'version' => 'Version', 'platform_version' => 'Platform compatibility', 'author' => 'Publisher', 'license' => 'License'] as $key => $label) {
        $html .= '<label>'.e($label).'<input name="'.$key.'" value="'.e((string) $p[$key]).'" maxlength="'.($key === 'name' ? '120' : '160').'"'.(in_array($key, ['name', 'extension', 'version']) ? ' required' : '').($key === 'extension' && $locked ? ' readonly' : '').($key === 'extension' ? ' placeholder="publisher/package-name"' : '').'></label>';
    }
    $html .= '<label>Package type<select name="type"'.($locked ? ' disabled' : '').'><option value="theme"'.($p['type'] === 'theme' ? ' selected' : '').'>Theme</option><option value="module"'.($p['type'] === 'module' ? ' selected' : '').'>Extension</option></select></label><label>Access<select name="tier"><option value="free"'.($p['tier'] === 'free' ? ' selected' : '').'>Free</option><option value="premium"'.($p['tier'] === 'premium' ? ' selected' : '').'>Paid</option></select></label><label>Price <span class="meta">(ignored for free packages)</span><input type="number" min="0" max="9999999.99" step="0.01" name="price" value="'.e($p['price']).'"></label>';
    if ($admin) {
        $html .= '<label>Visibility<select name="status"><option value="draft"'.($p['status'] === 'draft' ? ' selected' : '').'>Draft — hidden from the marketplace</option><option value="published"'.($p['status'] === 'published' ? ' selected' : '').'>Published</option></select></label>';
    }
    $html .= '</div><label>Description<textarea name="summary" rows="4" maxlength="4000" required>'.e($p['summary']).'</textarea></label><label>Source repository URL<input type="url" name="repository_url" placeholder="https://github.com/publisher/package" value="'.e($p['repository_url']).'"></label><label>Package ZIP<input type="file" name="package" accept=".zip,application/zip"></label><p class="meta">Provide a repository URL or a ZIP (up to 20 MB within PHP upload limits). Put '.($p['type'] === 'theme' ? 'theme.json' : 'module.json').' at the ZIP root. Uploaded packages are available for download after approval.'.(! empty($p['package']) ? ' A package ZIP is saved; leave blank to keep it.' : '').'</p><div class="upload-box"><div><strong>Package thumbnail</strong><p class="meta">PNG, JPG or WebP · up to 3 MB · recommended 1200 × 750</p><input type="file" name="thumbnail" accept="image/png,image/jpeg,image/webp" aria-label="Package thumbnail"></div>';
    if ($p['thumbnail'] !== '') {
        $html .= '<img class="upload-preview" src="'.e($p['thumbnail']).'" alt="Current thumbnail"><label class="check"><input type="checkbox" name="remove_thumbnail" value="1"> Remove custom thumbnail</label>';
    }
    $html .= '</div>'.listingMetadataForm($store, $p, $admin).'<div class="actions"><button>'.($admin ? 'Save package' : 'Submit for review').'</button><a href="'.($admin ? '/admin/repository' : '/submissions').'">'.($admin ? 'Back to repository' : 'My submissions').'</a></div>';

    return $html.'</form>';
}

function updateInput(array $product): array
{
    $version = field('version');
    $changelog = trim(field('changelog'));
    if (! preg_match('/^\d+\.\d+\.\d+(?:-[a-zA-Z0-9.-]+)?$/D', $version)) {
        throw new RuntimeException('Use a version such as 1.2.0.');
    }
    if (version_compare($version, (string) $product['version'], '<=')) {
        throw new RuntimeException('The new version must be greater than v'.$product['version'].'.');
    }
    if ($changelog === '' || strlen($changelog) > 4000) {
        throw new RuntimeException('Describe what changed, in 4,000 characters or less.');
    }
    $package = catalogPackage($product['extension'], $product['type'], $version);
    if ($package === null) {
        throw new RuntimeException('Upload the new package ZIP.');
    }

    return [
        'extension' => $product['extension'],
        'slug' => $product['slug'],
        'name' => $product['name'],
        'summary' => $product['summary'],
        'type' => $product['type'],
        'version' => $version,
        'platform_version' => $product['platform_version'] ?? '^1.0',
        'package' => $package,
        'changelog' => $changelog,
        'kind' => 'update',
        'demo_url' => field('demo_url') === '' ? ($product['demo_url'] ?? '') : demoUrl(field('demo_url')),
        'tier' => ! empty($product['premium']) ? 'premium' : 'free',
        'price' => (string) ($product['price'] ?? '0.00'),
    ];
}

function updateForm(array $products, string $action): string
{
    if ($products === []) {
        return '<p class="empty">There are no packages to update yet.</p>';
    }
    $html = '<form class="panel wide" method="post" enctype="multipart/form-data" action="'.e($action).'">'.csrf().'<label>Package<select name="extension" required>';
    foreach ($products as $product) {
        $html .= '<option value="'.e($product['extension']).'">'.e($product['name']).' · '.($product['type'] === 'theme' ? 'Theme' : 'Extension').' · current v'.e($product['version']).'</option>';
    }
    $html .= '</select></label><label>Live demo URL (optional)<input type="url" name="demo_url" maxlength="2048" placeholder="Leave blank to keep the current demo"></label><label>New version<input name="version" placeholder="1.2.0" required></label><label>What changed<textarea name="changelog" rows="4" maxlength="4000" required></textarea></label><label>Package ZIP<input type="file" name="package" accept=".zip,application/zip" required></label><p class="meta">Put theme.json or module.json at the ZIP root. Its package identifier and version must match this form. Up to 20 MB.</p><div class="actions"><button>'.($action === '/admin/updates' ? 'Publish update' : 'Submit update for review').'</button></div></form>';

    return $html;
}

function publisherPages(string $path, Store $store, array $products): bool
{
    if (! in_array($path, ['/submit', '/submit/update', '/submissions'], true)) {
        return false;
    }
    $customer = currentCustomer($store);
    if ($customer === null) {
        catalog_redirect('/account/login?next='.rawurlencode($path));
    }
    if ($path === '/submit') {
        $values = null;
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            guard();
            try {
                $store->submit((int) $customer['id'], listingInput(), $products);
                flash('Submission received. Your package will appear in the marketplace after approval.');
                catalog_redirect('/submissions');
            } catch (RuntimeException $exception) {
                flash($exception->getMessage());
                $values = array_intersect_key($_POST, array_flip(['name', 'summary', 'extension', 'type', 'version', 'platform_version', 'author', 'license', 'repository_url', 'demo_url', 'categories', 'features_text', 'tier', 'price']));
            }
        }
        page('Submit a package', '<h1>Share your next great idea.</h1><p class="lead">Submit a theme or extension. Our team reviews every package before it goes live.</p><p><a href="/submit/update">Submitting a new version of a package that is already listed? →</a></p>'.listingForm($values, '/submit', false), false);
    } elseif ($path === '/submit/update') {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            guard();
            try {
                $selected = null;
                foreach ($products as $product) {
                    if ($product['extension'] === field('extension')) {
                        $selected = $product;
                    }
                }
                if ($selected === null) {
                    throw new RuntimeException('Choose a theme or extension that is already in the marketplace.');
                }
                $store->submitUpdate((int) $customer['id'], updateInput($selected), $products);
                flash('Update received. It goes live after approval, and stores can then install it.');
                catalog_redirect('/submissions');
            } catch (RuntimeException $exception) {
                flash($exception->getMessage());
            }
        }
        page('Submit an update', '<h1>Submit a new version.</h1><p class="lead">Upload the next version of a theme or extension that is already in the marketplace.</p>'.updateForm($products, '/submit/update'), false);
    } else {
        $html = '<div class="section-heading"><div><span class="eyebrow">PUBLISHER WORKSPACE</span><h1>My submissions</h1></div><span class="actions"><a class="button secondary" href="/submit/update">Submit an update</a> <a class="button" href="/submit">+ Submit package</a></span></div>';
        $rows = $store->submissions((int) $customer['id']);
        foreach ($rows as $row) {
            $html .= '<article class="panel wide"><div class="section-heading"><h2>'.e($row['product']['name']).'</h2><span class="status '.e($row['status']).'">'.e(ucfirst($row['status'])).'</span></div><p class="meta">'.e($row['extension']).' · Submitted '.e(substr($row['created_at'], 0, 10)).'</p><p>'.e($row['review_note'] ?: ($row['status'] === 'pending' ? 'Your submission is waiting for review.' : 'Your package has been approved.')).'</p>';
            if ($row['status'] === 'approved') {
                $html .= '<a href="/item/'.e($row['product']['slug']).'">View listing →</a>';
            }
            $html .= '</article>';
        }
        page('My submissions', $html.($rows === [] ? '<p class="empty">You haven’t submitted any packages yet.</p>' : ''), false);
    }

    return true;
}

function catalogStorage(): string
{
    $configured = getenv('CATALOG_STORAGE');
    $root = dirname(__DIR__);
    if (! is_string($configured) || $configured === '') {
        return $root.'/storage';
    }

    $configured = str_replace('\\', '/', $configured);
    if (! preg_match('#^(?:[A-Za-z]:/|/)#', $configured)) {
        return $root.'/'.ltrim($configured, '/');
    }

    return $configured;
}

function sendCatalogPackage(string $filename, string $slug): void
{
    $file = catalogStorage().'/packages/'.$filename;
    if (! preg_match('/^[a-f0-9]{32}\.zip$/D', $filename) || ! is_file($file)) {
        http_response_code(404);
        echo 'Package ZIP not available.';

        return;
    }
    header('Content-Type: application/zip');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    header('Content-Disposition: attachment; filename="'.$slug.'.zip"');
    header('Content-Length: '.filesize($file));
    readfile($file);
}
