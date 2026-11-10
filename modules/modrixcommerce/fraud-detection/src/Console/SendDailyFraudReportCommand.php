<?php

namespace EcoModular\FraudDetection\Console;

use EcoModular\FraudDetection\Services\FraudNotificationService;
use Illuminate\Console\Command;

class SendDailyFraudReportCommand extends Command
{
    protected $signature = 'fraud:daily-report';

    protected $description = 'Send the daily fraud detection summary report';

    public function handle(FraudNotificationService $notificationService): int
    {
        $this->info('Sending daily fraud report...');

        $notificationService->sendDailyReport();

        $this->info('Daily fraud report sent successfully.');

        return self::SUCCESS;
    }
}
