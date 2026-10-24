<?php

function marketplaceAuth(bool $register, string $action = '/account/login'): string
{
    $admin = str_starts_with($action, '/admin');
    $next = safeNext((string) ($_GET['next'] ?? '/account'));
    $values = $_SESSION['auth_input'] ?? [];
    unset($_SESSION['auth_input']);
    $heading = $register ? 'Create your account' : ($admin ? 'Welcome to your studio' : 'Good to see you again');
    $other = ($register ? '/account/login' : '/account/register').'?next='.rawurlencode($next);
    $html = '<div class="auth-layout"><aside class="auth-story"><span class="eyebrow">THE ECOMODULAR MARKETPLACE</span><h2>A little inspiration.<br>A lot of possibility<span>.</span></h2><p>Your next great storefront starts here. Discover themes, find powerful extensions, and bring it all together.</p><div class="auth-art" aria-hidden="true"><div class="auth-art-card art-back"><span>✦</span><strong>Made for your next idea.</strong></div><div class="auth-art-card art-front"><div class="mini-nav"><i></i><i></i><i></i></div><div class="mini-hero"><span>Make it<br>yours.</span><b>✦</b></div><div class="mini-products"><i></i><i></i><i></i></div></div><span class="art-label">YOUR STORE. REIMAGINED.</span></div><div class="auth-benefits"><span>✓ Free & paid collections</span><span>✓ All your licenses in one place</span></div></aside><section class="auth-form-section"><a class="auth-back" href="/">← Back to marketplace</a><div class="auth-form-heading"><span class="eyebrow">'.($admin ? 'ADMIN WORKSPACE' : 'YOUR NEXT CHAPTER').'</span><h1>'.$heading.'</h1><p>'.($register ? 'One account for your purchases, licenses, and creations.' : ($admin ? 'Sign in to manage your marketplace.' : 'Sign in to access your collection and manage your licenses.')).'</p></div><form method="post" action="'.e($action).'" class="auth-form">'.csrf().'<input type="hidden" name="next" value="'.e($next).'">';
    if ($register) {
        $html .= '<label>Full name<input name="name" autocomplete="name" placeholder="Your name" maxlength="120" value="'.e((string) ($values['name'] ?? '')).'" required></label>';
    }
    $html .= '<label>Email address<input name="email" type="email" autocomplete="email" placeholder="you@example.com" maxlength="254" value="'.e((string) ($values['email'] ?? '')).'" required></label><label>Password<span class="password-input"><input id="auth-password" name="password" type="password" autocomplete="'.($register ? 'new-password' : 'current-password').'" placeholder="'.($register ? 'At least 8 characters' : 'Enter your password').'"'.($register ? ' minlength="8"' : '').' required><button class="password-toggle" type="button" data-password-toggle="auth-password" aria-label="Show password" aria-pressed="false">Show</button></span></label>';
    if ($register) {
        $html .= '<label>Confirm password<span class="password-input"><input id="auth-confirm" name="password_confirmation" type="password" autocomplete="new-password" placeholder="Re-enter your password" minlength="8" required><button class="password-toggle" type="button" data-password-toggle="auth-confirm" aria-label="Show confirmation password" aria-pressed="false">Show</button></span></label>';
    }
    $html .= '<button class="auth-submit" type="submit">'.($register ? 'Create account' : 'Sign in').' <span aria-hidden="true">→</span></button></form>';
    if (! $admin) {
        $html .= '<p class="auth-switch">'.($register ? 'Already part of the community?' : 'New to the marketplace?').' <a href="'.e($other).'">'.($register ? 'Sign in' : 'Create an account').'</a></p>';
    }

    return $html.'<div class="auth-note">'.($admin ? 'Your marketplace admin account is separate from your store account.' : 'Your marketplace account keeps your purchases and licenses together.').'</div></section></div>';
}

function marketplaceAccount(Store $store, array $customer, array $products, Licenses $licenses): string
{
    $id = (int) $customer['id'];
    $keys = $licenses->forCustomer($id);
    $purchases = $store->purchases($id);
    $owned = array_column($purchases, null, 'extension');
    $keyed = array_column($keys, null, 'extension');
    $connected = array_unique(array_column(array_filter($keys, fn ($key) => $key['instance_id'] !== null), 'instance_id'));
    $options = '<option value="">Choose a theme or extension…</option>';
    foreach ($products as $p) {
        if (! isset($keyed[$p['extension']]) && (! $p['premium'] || isset($owned[$p['extension']]))) {
            $options .= '<option value="'.e($p['slug']).'"'.(($_GET['product'] ?? '') === $p['slug'] ? ' selected' : '').'>'.e($p['name']).' · '.($p['premium'] ? 'Purchased' : 'Free').'</option>';
        }
    }
    $html = '<div class="account-heading"><div><span class="eyebrow">YOUR MARKETPLACE</span><h1>Welcome, '.e($customer['name']).'.</h1><p class="meta">Your collection, your licenses, your next great idea.</p></div><div class="actions"><a class="button secondary" href="/submissions">My submissions</a><form method="post" action="/account/logout">'.csrf().'<button class="secondary">Log out</button></form></div></div><div class="stats account-stats"><div class="stat"><span>Your licenses</span><strong>'.count($keys).'</strong></div><div class="stat"><span>Connected stores</span><strong>'.count($connected).'</strong></div><div class="stat"><span>Paid purchases</span><strong>'.count($purchases).'</strong></div></div><section class="license-generator"><div><span class="eyebrow">READY FOR YOUR STORE</span><h2>Generate a license</h2><p>Choose a free package or one you’ve purchased. Each license connects to one store at a time.</p></div><form method="post" action="/account/licenses">'.csrf().'<label for="license-product">Theme or extension</label><select id="license-product" name="product" data-license-search required>'.$options.'</select><button>Generate license key <span aria-hidden="true">↗</span></button></form></section><div class="section-heading"><h2>Your licenses</h2><a href="/">Explore the marketplace →</a></div><div class="license-grid">';
    foreach ($keys as $key) {
        $active = $key['instance_id'] !== null;
        $html .= '<article class="license-card"><div class="section-heading"><span class="pill">'.($key['type'] === 'theme' ? 'Theme' : 'Extension').'</span><span class="status '.($active ? 'published' : 'draft').'">'.($active ? 'Connected' : 'Ready to activate').'</span></div><h3>'.e($key['product_name']).'</h3><p class="meta">'.e($key['extension']).'</p><label>License key<div class="license-key-row"><input id="license-'.$key['id'].'" readonly value="'.e($key['license_key']).'" spellcheck="false" aria-label="'.e($key['product_name']).' license key"><button class="secondary" type="button" data-copy-target="license-'.$key['id'].'" aria-label="Copy '.e($key['product_name']).' license key">Copy</button></div></label><div class="license-card-footer"><span class="meta">'.e($active ? ($key['domain'] ?: 'Connected store') : 'One store · No expiry').'</span>';
        if ($active) {
            $html .= '<form method="post" action="/account/licenses/release">'.csrf().'<input type="hidden" name="license_id" value="'.$key['id'].'"><button class="text-button">Release store</button></form>';
        }
        $html .= '</div></article>';
    }
    if ($keys === []) {
        $html .= '<div class="empty-state"><span class="empty-symbol" aria-hidden="true">◇</span><h2>Your collection starts here</h2><p>Generate a key for a free package above, or purchase a paid package to unlock its license.</p></div>';
    }
    $html .= '</div><div class="license-help"><span aria-hidden="true">↗</span><div><strong>Connect a license to your store</strong><p>Copy your key, open your store admin → Extensions → Marketplace → Add license, then activate it. To move to another store, release the current store here first.</p></div></div><p class="copy-feedback" role="status" aria-live="polite"></p>';
    if ($purchases !== []) {
        $html .= '<section class="purchase-history"><div class="section-heading"><h2>Purchase history</h2></div><div class="table-scroll"><table><thead><tr><th>Package</th><th>Amount</th><th>Purchased</th><th>License</th></tr></thead><tbody>';
        foreach ($purchases as $order) {
            $html .= '<tr><td>'.e($order['product_name']).'</td><td>'.e($order['currency'].' '.$order['amount']).'</td><td>'.e(substr($order['created_at'], 0, 10)).'</td><td>'.(isset($keyed[$order['extension']]) ? 'Generated above' : 'Choose this package above to retrieve your key').'</td></tr>';
        }
        $html .= '</tbody></table></div></section>';
    }

    return $html;
}

function licenseSettings(Licenses $licenses): string
{
    global $base;

    return '<section id="licensing"><h2>Store license connection</h2><div class="panel wide"><p class="meta">Store administrators must trust this public key before activating marketplace licenses. The private signing key stays in the marketplace storage.</p><label>Marketplace URL<input readonly value="'.e($base).'"></label><label>Public signing key<input readonly value="'.e($licenses->publicKey()).'"></label><p class="meta">Set ECOMODULAR_MARKETPLACE_URL to the marketplace URL and ECOMODULAR_MARKETPLACE_TRUSTED_KEYS to this public key in the merchant store environment, then clear its configuration cache. Keep existing trusted keys when adding this one.</p></div></section>';
}
