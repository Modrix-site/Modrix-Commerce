<?php

namespace EcoModular\BotProtection\Console;

use EcoModular\BotProtection\Services\BotReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateAttackReportCommand extends Command
{
    protected $signature = 'bot:daily-report {--date= : The date to generate for (YYYY-MM-DD)}';
    protected $description = 'Generate daily bot threat and attack analysis report';

    public function handle(BotReportService $reportService): int
    {
        $dateInput = $this->option('date');
        $date = $dateInput ? Carbon::parse($dateInput) : Carbon::today();

        $this->info("Generating Bot Protection report for {$date->toDateString()}...");

        $report = $reportService->generateReportForDate($date);

        $this->info("Report generated successfully. Total Requests: {$report->total_requests}, Bots Detected: {$report->bot_requests}, Blocked: {$report->blocked_requests}.");

        return self::SUCCESS;
    }
}
