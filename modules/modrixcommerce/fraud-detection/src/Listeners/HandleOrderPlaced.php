<?php

namespace EcoModular\FraudDetection\Listeners;

use App\Core\Extensions\ModuleSettingsSchema;
use App\Domains\Orders\Events\OrderPlaced;
use App\Domains\Orders\Models\Order;
use EcoModular\FraudDetection\FraudDetectionServiceProvider as Module;
use EcoModular\FraudDetection\Models\FraudAuditLog;
use EcoModular\FraudDetection\Services\CustomerIntelligenceService;
use EcoModular\FraudDetection\Services\FraudNotificationService;
use EcoModular\FraudDetection\Services\RiskScoringEngine;
use Throwable;

class HandleOrderPlaced
{
    public function __construct(
        private readonly RiskScoringEngine $scoringEngine,
        private readonly FraudNotificationService $notificationService,
        private readonly CustomerIntelligenceService $customerIntelligence,
        private readonly ModuleSettingsSchema $settings,
    ) {}

    public function handle(OrderPlaced $event): void
    {
        if (! (bool) $this->settings->of(Module::MODULE, 'enabled', true)) {
            return;
        }

        try {
            /** @var Order|null $order */
            $order = $event->order ?? Order::query()->find($event->orderId);
            if (! $order) {
                return;
            }

            // Build context from the event/request
            $context = [
                'ip_address' => request()->ip(),
                'is_vpn' => false,
                'is_proxy' => false,
                'failed_payment_attempts' => $event->failedPaymentAttempts ?? 0,
                'payment_country' => $event->paymentCountry ?? null,
            ];

            // Score the order
            $fraudScore = $this->scoringEngine->analyze($order, $context);

            // Update customer intelligence profile
            $this->customerIntelligence->updateProfile($fraudScore);

            // Send notifications for high-risk orders
            $this->notificationService->notifyHighRisk($fraudScore);

            // Audit log
            FraudAuditLog::record(
                'score.created',
                'fraud_score',
                $fraudScore->id,
                [
                    'order_id' => $order->id,
                    'total_score' => $fraudScore->total_score,
                    'risk_level' => $fraudScore->risk_level,
                    'review_status' => $fraudScore->review_status,
                ]
            );
        } catch (Throwable $e) {
            report($e);
        }
    }
}
