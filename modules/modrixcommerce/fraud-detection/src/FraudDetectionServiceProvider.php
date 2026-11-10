<?php

namespace EcoModular\FraudDetection;

use App\Domains\Orders\Events\OrderPlaced;
use EcoModular\FraudDetection\Console\SendDailyFraudReportCommand;
use EcoModular\FraudDetection\Listeners\HandleOrderPlaced;
use EcoModular\FraudDetection\Services\CustomerIntelligenceService;
use EcoModular\FraudDetection\Services\FraudDashboardService;
use EcoModular\FraudDetection\Services\FraudNotificationService;
use EcoModular\FraudDetection\Services\FraudReportService;
use EcoModular\FraudDetection\Services\RiskScoringEngine;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class FraudDetectionServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/fraud-detection';

    public function register(): void
    {
        $this->app->singleton(RiskScoringEngine::class);
        $this->app->singleton(FraudDashboardService::class);
        $this->app->singleton(FraudNotificationService::class);
        $this->app->singleton(FraudReportService::class);
        $this->app->singleton(CustomerIntelligenceService::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'fraud-detection');

        if ($this->app->runningInConsole()) {
            $this->commands([
                SendDailyFraudReportCommand::class,
            ]);

            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);
                $schedule->command('fraud:daily-report')->dailyAt('09:00')->withoutOverlapping();
            });
        }

        // Listen for new orders
        Event::listen(OrderPlaced::class, HandleOrderPlaced::class);
    }
}
