<?php

namespace EcoModular\Wishlist\Listeners;

use EcoModular\Wishlist\WishlistManager;
use Illuminate\Auth\Events\Login;

class MergeWishlistOnLogin
{
    public function __construct(
        private readonly WishlistManager $wishlistManager,
    ) {}

    public function handle(Login $event): void
    {
        $session = request()->session();
        $token = $session->get(WishlistManager::SESSION_TOKEN);

        if (is_string($token) && $token !== '') {
            $this->wishlistManager->mergeGuestWishlist($token, $event->user);
            $session->forget(WishlistManager::SESSION_TOKEN);
        }
    }
}
