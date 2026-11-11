<?php

/*
| Loaded by the platform under /admin/m/modrixcommerce/newsletter with the admin
| middleware and the "admin.modrixcommerce.newsletter." name prefix.
*/

use EcoModular\Newsletter\Http\SubscriberAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:newsletter.manage')->group(function () {
    // Subscribers
    Route::get('/', [SubscriberAdminController::class, 'index'])->name('index');
    Route::post('subscribers', [SubscriberAdminController::class, 'storeSubscriber'])->name('subscribers.store');
    Route::match(['post', 'patch'], 'subscribers/{subscriber}/groups', [SubscriberAdminController::class, 'updateSubscriberGroups'])->name('subscribers.groups.update');
    Route::post('subscribers/bulk-group', [SubscriberAdminController::class, 'bulkGroup'])->name('subscribers.bulk_group');
    Route::delete('{subscriber}', [SubscriberAdminController::class, 'destroy'])->name('destroy');
    Route::get('export', [SubscriberAdminController::class, 'export'])->name('export');

    // Groups / Segments
    Route::post('groups', [SubscriberAdminController::class, 'storeGroup'])->name('groups.store');
    Route::delete('groups/{group}', [SubscriberAdminController::class, 'destroyGroup'])->name('groups.destroy');

    // Email Marketing Campaigns
    Route::post('campaigns', [SubscriberAdminController::class, 'storeCampaign'])->name('campaigns.store');
    Route::put('campaigns/{campaign}', [SubscriberAdminController::class, 'updateCampaign'])->name('campaigns.update');
    Route::delete('campaigns/{campaign}', [SubscriberAdminController::class, 'destroyCampaign'])->name('campaigns.destroy');
    Route::post('campaigns/{campaign}/send-test', [SubscriberAdminController::class, 'sendTestCampaign'])->name('campaigns.send_test');
    Route::post('campaigns/{campaign}/send', [SubscriberAdminController::class, 'sendCampaign'])->name('campaigns.send');

    // Core SMTP Connection & Test
    Route::post('smtp/save', [SubscriberAdminController::class, 'saveSmtp'])->name('smtp.save');
    Route::post('smtp/test', [SubscriberAdminController::class, 'testSmtp'])->name('smtp.test');
});
