<?php

namespace App\Console\Commands;

use App\Actions\Inventory\PartsStock\CheckLowStockAction;
use Illuminate\Console\Command;

class CheckPartsLowStock extends Command
{
    protected $signature = 'parts:check-low-stock
                            {--dry-run : Ulat lang — hindi mag-e-email o magse-save ng flags}';

    protected $description = 'Mag-notify sa supply staff ng combined summary kapag low/critical ang stock ng parts & consumables';

    public function handle(CheckLowStockAction $action): int
    {
        $result = $action->execute((bool) $this->option('dry-run'));

        $verb = $this->option('dry-run') ? 'Matatanggap sana ang notif' : 'Naipadala ang notif';
        $this->info("Parts low-stock check tapos: {$verb} — {$result['notified']} notification(s); {$result['low']} low, {$result['critical']} critical.");

        return Command::SUCCESS;
    }
}