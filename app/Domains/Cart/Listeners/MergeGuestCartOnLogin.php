<?php

namespace App\Domains\Cart\Listeners;

use App\Domains\Cart\CartManager;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

final class MergeGuestCartOnLogin
{
    public function __construct(
        private readonly CartManager $carts,
        private readonly Request $request,
    ) {}

    public function handle(Login $event): void
    {
        if ($event->user instanceof User && $this->request->hasSession()) {
            $this->carts->mergeGuestCart($this->request->session(), $event->user);
        }
    }
}
