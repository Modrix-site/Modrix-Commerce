<?php

/*
| Inbound webhooks. Registered in bootstrap/app.php without the `web`
| middleware group: no session, cookies or CSRF. Each provider authenticates
| its deliveries with a signature that the gateway verifies.
*/

use App\Http\Controllers\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('payments/{provider}', PaymentWebhookController::class)
    ->where('provider', '[a-z0-9_-]{1,32}')
    ->middleware('throttle:webhooks')
    ->name('payments');
