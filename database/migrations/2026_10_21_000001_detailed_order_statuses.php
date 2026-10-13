<?php

use App\Domains\Orders\Models\Order;
use App\Domains\Orders\OrderDisplayStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Detailed order statuses (ADR-044): "pending" now means "awaiting review",
 * so orders awaiting payment become "pending_payment"; paid orders not yet
 * in fulfilment become "confirmed". Every order gets its headline status.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'display_status')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('display_status', 24)->nullable()->after('fulfillment_status')->index();
            });
        }

        DB::table('orders')->where('status', 'pending')->update(['status' => 'pending_payment']);
        DB::table('orders')->where('status', 'processing')->where('fulfillment_status', 'unfulfilled')->update(['status' => 'confirmed']);
        DB::table('orders')->where('hold_previous_status', 'pending')->update(['hold_previous_status' => 'pending_payment']);
        DB::table('orders')->where('status', 'on_hold')->where('hold_previous_status', 'processing')
            ->whereIn('fulfillment_status', ['unfulfilled'])->update(['hold_previous_status' => 'confirmed']);

        Order::query()->select(['id', 'status', 'payment_status', 'fulfillment_status', 'hold_previous_status'])
            ->chunkById(500, function ($orders) {
                foreach ($orders as $order) {
                    DB::table('orders')->where('id', $order->id)->update(['display_status' => OrderDisplayStatus::for($order)->value]);
                }
            });
    }

    public function down(): void
    {
        DB::table('orders')->whereIn('status', ['confirmed'])->update(['status' => 'processing']);
        DB::table('orders')->where('status', 'pending')->update(['status' => 'processing']);
        DB::table('orders')->where('status', 'pending_payment')->update(['status' => 'pending']);
        DB::table('orders')->where('hold_previous_status', 'confirmed')->update(['hold_previous_status' => 'processing']);
        DB::table('orders')->where('hold_previous_status', 'pending_payment')->update(['hold_previous_status' => 'pending']);

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['display_status']);
            $table->dropColumn('display_status');
        });
    }
};
