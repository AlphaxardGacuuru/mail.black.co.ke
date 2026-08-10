<?php

namespace App\Console\Commands;

use App\Http\Services\ContractService;
use Illuminate\Console\Command;

class ExpireContractsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contracts:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire contracts that have passed their end date and auto-renew where applicable';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $service = new ContractService();
        $count = $service->expireContracts();

        $this->info("Expired {$count} contract(s).");

        return Command::SUCCESS;
    }
}
