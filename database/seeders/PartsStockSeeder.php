<?php

namespace Database\Seeders;

use App\Models\Part;
use Illuminate\Database\Seeder;

/**
 * Test/seed data for the Parts & Consumables Stock module.
 *
 * The list is derived from the ACTUAL inventory_assets data (specs + printer
 * fleet) so it stays consistent with the real equipment — region NCR / RCMB.
 */
class PartsStockSeeder extends Seeder
{
    private const BRANCH = 'RCMB – NATIONAL CAPITAL REGION';

    public function run(): void
    {
        $parts = [
            // Memory
            ['RAM 16GB DDR4 3200MHz', 'pcs', 'Memory', 12, 5],
            ['RAM 8GB DDR4 3200MHz', 'pcs', 'Memory', 10, 5],
            ['RAM 8GB DDR3 1600MHz', 'pcs', 'Memory', 6, 3],
            ['RAM 32GB DDR4 3200MHz', 'pcs', 'Memory', 6, 3],
            // Storage
            ['SSD 512GB SATA 2.5"', 'pcs', 'Storage', 10, 4],
            ['SSD 1TB SATA 2.5"', 'pcs', 'Storage', 3, 5],
            ['SSD 256GB M.2 NVMe', 'pcs', 'Storage', 8, 4],
            ['SSD 2TB M.2 NVMe', 'pcs', 'Storage', 4, 2],
            ['HDD 1TB 7200RPM', 'pcs', 'Storage', 0, 5],
            // Print consumables
            ['Toner Brother TN-3480 (HL-5100DN)', 'pcs', 'Print Consumable', 6, 3],
            ['Toner Brother TN-2460 (HL-L2460DW)', 'pcs', 'Print Consumable', 1, 3],
            ['Toner HP 136A (LaserJet 107W)', 'pcs', 'Print Consumable', 5, 3],
            ['Toner HP 64A (LaserJet PRO 400)', 'pcs', 'Print Consumable', 4, 2],
            ['EPSON 003 Ink Bottle - Black', 'tube', 'Print Consumable', 2, 4],
            ['EPSON 003 Ink Bottle - Color', 'tube', 'Print Consumable', 6, 3],
            // Power
            ['UPS Battery 12V 9Ah (Back-UPS)', 'pcs', 'Power', 5, 2],
            // Peripherals
            ['Keyboard Logitech MK120', 'pcs', 'Peripheral', 8, 3],
            ['Mouse Logitech M105', 'pcs', 'Peripheral', 10, 3],
            // Hardware / cabling
            ['Screws M3 x 6mm (100/box)', 'box', 'Hardware', 4, 2],
            ['RJ45 Cat6 Patch Cable 1m', 'pc', 'Network', 15, 5],
        ];

        foreach ($parts as [$itemName, $unit, $category, $onHand, $reorder]) {
            Part::create([
                'item_name' => $itemName,
                'unit' => $unit,
                'category' => $category,
                'on_hand_qty' => $onHand,
                'reorder_level' => $reorder,
                'region' => 'NCR',
                'branch' => self::BRANCH,
                'is_active' => true,
            ]);
        }
    }
}