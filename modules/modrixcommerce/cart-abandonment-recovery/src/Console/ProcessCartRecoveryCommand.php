<?php

namespace EcoModular\CartAbandonmentRecovery\Console;

use EcoModular\CartAbandonmentRecovery\Services\CartRecoveryService;
use Illuminate\Console\Command;

class ProcessCartRecoveryCommand extends Command
{
    protected $signature = 'cart-recovery:process';

    protected $description = 'Scans for abandoned shopping carts and dispatches scheduled recovery email sequences';

    public function handle(CartRecoveryService $service): int
    {
        $this->info('Starting Cart Abandonment Recovery processing...');

        $detected = $service->detectAndSyncAbandonedCarts();
        $this->info("Detected and synced {$detected} newly abandoned cart(s).");

        $sent = $service->processScheduledEmails();
        $this->info("Dispatched {$sent} recovery email(s).");

        $this->info('Cart recovery processing completed successfully.');

        return Command::SUCCESS;
    }
}
