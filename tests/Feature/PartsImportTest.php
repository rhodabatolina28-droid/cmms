<?php

namespace Tests\Feature;

use App\Models\Part;
use App\Models\PartUnit;
use App\Models\User;
use App\Services\PartsCsvImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PartsImportTest extends TestCase
{
    use RefreshDatabase;

    private $counter = 0;

    private function makeUser(array $attrs = [])
    {
        $this->counter++;
        return User::create(array_merge([
            'full_name' => 'Import User ' . $this->counter,
            'email' => 'import' . $this->counter . '@test.com',
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

    private function supplyOfficer()
    {
        return $this->makeUser(['role' => 'supply_officer']);
    }

    private function csvContent(): string
    {
        return implode("\n", [
            ',,,,,,,,,,,,',
            ',ARTICLE,DESCRIPTION,DATE OF ACQ.,SERIAL NO.,PROPERTY NUMBER,UNIT OF MEASURE,UNIT VALUE,QTY. PER PROPERTY CARD,QTY. PER PHYSICAL COUNT,SHORTAGE/OVERAGE,,REMARKS,',
            '"","","","","","","","Quantity","Value","","","","",""',
            '"","","","","","","","","","","","","RESPONSIBLE OFFICER","LOCATION"',
            ',INTANGIBLE ASSET,,,,,,,,,,,,',
            ',MEMORY,HPE16GB-P00922-B21,8/12/2022,KR8220Y2BS,2022-01-02-0001,1,"  22,490.00 ",1,0,  -   ,OFFICER-A,LOC-X',
            ',MEMORY,HPE16GB-P00922-B21,8/12/2022,KR8220Y2BJ,2022-01-02-002,1,"  22,490.00 ",1,0,  -   ,OFFICER-A,LOC-X',
            ',SSD,SSD 1TB,8/12/2022,SER-1,2022-01-02-009,1,"  34,476.00 ",1,1,,OFFICER-B,LOC-Y',
        ]);
    }

    public function test_service_parses_csv_rows()
    {
        $path = tempnam(sys_get_temp_dir(), 'parts') . '.csv';
        file_put_contents($path, $this->csvContent());

        $rows = (new PartsCsvImportService)->importableRows($path);
        unlink($path);

        $this->assertCount(3, $rows, 'Dapat 3 data rows, hindi kasama ang headers/section.');
        $this->assertSame('HPE16GB-P00922-B21', $rows[0]['item_name']);
        $this->assertSame('MEMORY', $rows[0]['category']);
        $this->assertSame('KR8220Y2BS', $rows[0]['serial']);
        $this->assertSame('2022-01-02-0001', $rows[0]['property']);
        $this->assertEqualsWithDelta(22490.0, $rows[0]['unit_value'], 0.01);
    }

    public function test_preview_returns_summary_and_token()
    {
        $this->actingAs($this->supplyOfficer());

        $resp = $this->call('POST', route('inventory.parts.import.preview'), [], [], [
            'file' => UploadedFile::fake()->createWithContent('parts.csv', $this->csvContent()),
        ], ['Accept' => 'application/json']);

        $resp->assertStatus(200)->assertJsonPath('success', true);
        $data = $resp->json();
        $this->assertNotEmpty($data['token']);
        $this->assertSame(3, $data['summary']['rows']);
        $this->assertSame(2, $data['summary']['distinct_parts']);
        $this->assertSame(0, $data['summary']['duplicate_serials']);
    }

    public function test_commit_creates_parts_units_and_on_hand()
    {
        $this->actingAs($this->supplyOfficer());

        $preview = $this->call('POST', route('inventory.parts.import.preview'), [], [], [
            'file' => UploadedFile::fake()->createWithContent('parts.csv', $this->csvContent()),
        ], ['Accept' => 'application/json'])->json();

        $resp = $this->postJson(route('inventory.parts.import.commit'), ['token' => $preview['token']]);
        $resp->assertStatus(200)->assertJsonPath('success', true)
            ->assertJsonPath('created_parts', 2)
            ->assertJsonPath('created_units', 3);

        $this->assertSame(1, Part::where('item_name', 'HPE16GB-P00922-B21')->count(), 'isa lang ang HPE16GB part');
        $ram = Part::where('item_name', 'HPE16GB-P00922-B21')->first();
        $this->assertNotNull($ram);
        $this->assertSame(2, $ram->on_hand_qty);
        $this->assertSame(2, PartUnit::where('part_id', $ram->id)->where('status', 'in_stock')->count());

        // Consistency: on_hand == count(in_stock units)
        foreach (Part::all() as $p) {
            $this->assertSame($p->on_hand_qty, PartUnit::where('part_id', $p->id)->where('status', 'in_stock')->count());
        }
    }

    public function test_commit_skips_duplicate_serials()
    {
        $this->actingAs($this->supplyOfficer());

        $preview = $this->call('POST', route('inventory.parts.import.preview'), [], [], [
            'file' => UploadedFile::fake()->createWithContent('parts.csv', $this->csvContent()),
        ], ['Accept' => 'application/json'])->json();

        $this->postJson(route('inventory.parts.import.commit'), ['token' => $preview['token']])->assertStatus(200);

        // I-commit ulit — dapat lahat ng serial ay skip (walang dagdag na unit).
        $preview2 = $this->call('POST', route('inventory.parts.import.preview'), [], [], [
            'file' => UploadedFile::fake()->createWithContent('parts.csv', $this->csvContent()),
        ], ['Accept' => 'application/json'])->json();

        $resp2 = $this->postJson(route('inventory.parts.import.commit'), ['token' => $preview2['token']]);
        $resp2->assertStatus(200)->assertJsonPath('created_units', 0)
            ->assertJsonPath('skipped_duplicates', 3);

        $this->assertSame(3, PartUnit::count());
    }

    public function test_non_supply_cannot_preview_import()
    {
        $this->actingAs($this->makeUser(['role' => 'admin', 'can_supply' => false]));

        $resp = $this->call('POST', route('inventory.parts.import.preview'), [], [], [
            'file' => UploadedFile::fake()->createWithContent('parts.csv', 'x'),
        ], ['Accept' => 'application/json']);

        $resp->assertStatus(403);
    }
}