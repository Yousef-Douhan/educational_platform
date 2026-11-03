<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Command;

class ExpirePendingOrders extends Command
{
    protected $signature = 'orders:expire-pending';

    protected $description = 'Fail abandoned pending orders and give their coupon uses back';

    public function handle(OrderService $orders): int
    {
        $count = $orders->expirePending();

        $this->info("Expired {$count} pending order(s).");

        return self::SUCCESS;
    }
}
