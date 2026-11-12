<?php

namespace App\Console\Commands;

use App\Domains\Payments\Gateway\GatewayException;
use App\Domains\Payments\Models\Payment;
use App\Domains\Payments\PaymentReconciler;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Safety net for missed or delayed webhooks: asks the provider about open
 * gateway payments and applies the answer through the same idempotent path.
 * Scheduled every five minutes.
 */
#[Signature('ecomodular:payments:reconcile {--limit=100 : Maximum payments to check}')]
#[Description('Ask payment providers about open online payments and apply their current state')]
class ReconcilePaymentsCommand extends Command
{
    public function handle(PaymentReconciler $reconciler): int
    {
        $payments = Payment::query()
            ->whereIn('status', Payment::OPEN)
            ->where('provider', '!=', 'manual')
            ->whereNotNull('provider_reference')
            // Give the webhook a chance to arrive first.
            ->where('created_at', '<=', now()->subMinutes(2))
            ->orderBy('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        $failed = 0;

        foreach ($payments as $payment) {
            try {
                $reconciler->reconcile($payment);
            } catch (GatewayException $e) {
                $failed++;
                $this->components->warn("Payment {$payment->id}: {$e->getMessage()}");
            }
        }

        $this->components->info("Checked {$payments->count()} open payment(s); {$failed} could not be checked.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
