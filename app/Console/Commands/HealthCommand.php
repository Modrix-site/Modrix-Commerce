<?php

namespace App\Console\Commands;

use App\Core\Operations\HealthCheck;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Health checks for cron-based monitoring. Exits 1 when a check failed,
 * 0 otherwise (warnings included), so it can drive an alert.
 */
#[Signature('ecomodular:health {--json : Machine-readable output}')]
#[Description('Run the health checks')]
class HealthCommand extends Command
{
    public function handle(HealthCheck $health): int
    {
        $checks = $health->run();
        $overall = HealthCheck::overall($checks);

        if ($this->option('json')) {
            $this->line((string) json_encode(['status' => $overall, 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(['Check', 'Status', 'Detail'], array_map(fn (array $c) => [$c['label'], $c['status'], $c['detail']], $checks));
            $this->line("Overall: {$overall}");
        }

        return $overall === HealthCheck::FAILED ? self::FAILURE : self::SUCCESS;
    }
}
