<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $next = (int) DB::table('orders')->max('id') + 1;
        DB::table('orders')->select(['id', 'number'])->orderBy('id')->chunkById(500, function ($orders) use (&$next) {
            foreach ($orders as $order) {
                if (preg_match('/(\d+)$/', $order->number ?? '', $match)) {
                    $next = max($next, (int) $match[1] + 1);
                }
            }
        });
        DB::table('document_counters')->insertOrIgnore(['name' => 'order', 'next' => $next]);
    }

    public function down(): void
    {
        DB::table('document_counters')->where('name', 'order')->delete();
    }
};
