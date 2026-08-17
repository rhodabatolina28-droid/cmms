<?php

namespace Database\Seeders;

use App\Models\Part;
use App\Models\PartUnit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PartsStockSampleSeeder extends Seeder
{
    /**
     * Sample test data para sa Parts & Consumables — per-unit serial/property/cost.
     * TEST ONLY — hindi pang-produksyon.
     */
    public function run(): void
    {
        $this->seedRam();
        $this->seedSsd();
    }

    protected function seedRam(): void
    {
        if (Part::where('item_name', 'RAM HPE16GB-P00922-B21')->exists()) {
            return;
        }

        $part = Part::create([
            'item_name' => 'RAM HPE16GB-P00922-B21',
            'unit' => 'pcs',
            'category' => 'Memory',
            'on_hand_qty' => 0,
            'reorder_level' => 3,
            'region' => 'NCR',
            'is_active' => true,
        ]);

        $this->addUnits($part, [
            ['KR8220Y2BS', '2022-01-02-0001', 22490.00],
            ['KR8220Y2BJ', '2022-01-02-002', 22490.00],
            ['KR8220Y2BR', '2022-01-02-003', 22490.00],
        ]);
    }

    protected function seedSsd(): void
    {
        if (Part::where('item_name', 'HDD-HPE 2TB SATA')->exists()) {
            return;
        }

        $part = Part::create([
            'item_name' => 'HDD-HPE 2TB SATA',
            'unit' => 'pcs',
            'category' => 'Storage',
            'on_hand_qty' => 0,
            'reorder_level' => 2,
            'region' => 'NCR',
            'is_active' => true,
        ]);

        $this->addUnits($part, [
            ['2EFRA0183G80PT', '2022-01-02-009', 34476.00],
            ['2EFRA0183G80UI', '2022-01-02-010', 34476.00],
        ]);
    }

    protected function addUnits(Part $part, array $rows): void
    {
        DB::transaction(function () use ($part, $rows) {
            foreach ($rows as [$serial, $property, $value]) {
                PartUnit::create([
                    'part_id' => $part->id,
                    'serial_number' => $serial,
                    'property_number' => $property,
                    'unit_value' => $value,
                    'status' => 'in_stock',
                ]);
            }
            $part->update(['on_hand_qty' => count($rows)]);
        });
    }
}