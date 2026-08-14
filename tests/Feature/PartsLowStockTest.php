<?php

namespace Tests\Feature;

use App\Actions\Inventory\PartsStock\CheckLowStockAction;
use App\Models\Notification;
use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartsLowStockTest extends TestCase
{
    use RefreshDatabase;

    private $counter = 0;

    private function makeUser(array $attrs = [])
    {
        $this->counter++;
        return User::create(array_merge([
            'full_name' => 'LowStock User ' . $this->counter,
            'email' => 'low' . $this->counter . '@test.com',
            'password' => bcrypt('password'),
            'role' => 'user',
            'is_active' => true,
            'can_supply' => false,
            'region' => 'NCR',
            'branch' => null,
            'office' => null,
            'department' => null,
        ], $attrs));
    }

    private function supplyOfficer($region = 'NCR')
    {
        return $this->makeUser(['role' => 'supply_officer', 'region' => $region]);
    }

    public function test_combined_low_critical_send_one_summary_per_supply_user()
    {
        $supply = $this->supplyOfficer();

        Part::create(['item_name' => 'RAM 16GB DDR4', 'unit' => 'pcs', 'category' => 'Memory', 'on_hand_qty' => 2, 'reorder_level' => 5, 'region' => 'NCR', 'is_active' => true]);
        Part::create(['item_name' => 'Toner HP', 'unit' => 'pc', 'on_hand_qty' => 3, 'reorder_level' => 6, 'region' => 'NCR', 'is_active' => true]);
        Part::create(['item_name' => 'SSD 1TB', 'unit' => 'pcs', 'on_hand_qty' => 0, 'reorder_level' => 2, 'region' => 'NCR', 'is_active' => true]);

        $result = (new CheckLowStockAction)->execute();

        $this->assertEquals(2, $result['low']);
        $this->assertEquals(1, $result['critical']);
        $this->assertEquals(1, $result['notified']);

        // ISANG combined notification lamang (hindi per-item).
        $notifs = Notification::where('user_id', $supply->id)->get();
        $this->assertCount(1, $notifs);
        $this->assertEquals('Parts Low Stock Alert', $notifs->first()->type);
        $this->assertStringContainsString('RAM 16GB DDR4', $notifs->first()->message);
        $this->assertStringContainsString('Toner HP', $notifs->first()->message);
        $this->assertStringContainsString('SSD 1TB', $notifs->first()->message);

        // Data-driven: na-flag ang mga bagong-alert.
        $this->assertNotNull(Part::where('item_name', 'RAM 16GB DDR4')->first()->low_notified_at);
        $this->assertNotNull(Part::where('item_name', 'SSD 1TB')->first()->critical_notified_at);
    }

    public function test_no_duplicate_notification_before_recover()
    {
        $supply = $this->supplyOfficer();
        Part::create(['item_name' => 'RAM 16GB DDR4', 'unit' => 'pcs', 'on_hand_qty' => 2, 'reorder_level' => 5, 'region' => 'NCR', 'is_active' => true]);

        (new CheckLowStockAction)->execute();
        $this->assertEquals(1, Notification::where('user_id', $supply->id)->count());

        // Habang hindi pa healthy ang item, hindi na dapat ma-notify ulit.
        (new CheckLowStockAction)->execute();
        $this->assertEquals(1, Notification::where('user_id', $supply->id)->count());
    }

    public function test_recover_resets_flag_then_alerts_again()
    {
        $supply = $this->supplyOfficer();
        $part = Part::create(['item_name' => 'Toner HP', 'unit' => 'pc', 'on_hand_qty' => 2, 'reorder_level' => 5, 'region' => 'NCR', 'is_active' => true]);

        (new CheckLowStockAction)->execute();
        $this->assertEquals(1, Notification::where('user_id', $supply->id)->count());
        $this->assertNotNull($part->refresh()->low_notified_at);

        // Replenish sa healthy → self-heal magre-reset ng flag.
        $part->update(['on_hand_qty' => 6]);
        (new CheckLowStockAction)->execute();
        $this->assertNull($part->refresh()->low_notified_at);

        // Bumaba muli → dapat ma-alert ulit.
        $part->update(['on_hand_qty' => 1]);
        (new CheckLowStockAction)->execute();
        $this->assertEquals(2, Notification::where('user_id', $supply->id)->count());
    }

    public function test_dry_run_does_not_notify_or_set_flags()
    {
        $supply = $this->supplyOfficer();
        $part = Part::create(['item_name' => 'SSD 1TB', 'unit' => 'pcs', 'on_hand_qty' => 0, 'region' => 'NCR', 'is_active' => true]);

        $result = (new CheckLowStockAction)->execute(true);

        // Dry-run: i-uulat lang ang magiging tatanggap — walang email, walang flags.
        $this->assertEquals(1, $result['notified']);
        $this->assertEquals(1, $result['critical']);
        $this->assertEquals(0, Notification::where('user_id', $supply->id)->count());
        $this->assertNull($part->refresh()->critical_notified_at);
    }
}