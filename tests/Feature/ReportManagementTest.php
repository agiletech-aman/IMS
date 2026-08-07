<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\SubDepartment;
use App\Models\SystemNotification;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asStaticUser();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_report_generation_and_csv_export_apply_all_asset_filters(): void
    {
        $asset = Asset::firstOrFail();
        $subDepartment = SubDepartment::where('department_id', $asset->department_id)->firstOrFail();
        $asset->update([
            'sub_department_id' => $subDepartment->id,
            'warranty_expiry' => today()->addYear(),
            'amc_expiry' => today()->addMonths(6),
        ]);

Asset::create([
            'asset_tag' => 'AST-UN-001',
            'name' => 'Unassigned Test Asset',
            'asset_type_id' => $asset->asset_type_id,
            'brand_id' => $asset->brand_id,
            'status' => 'In Stock',
        ]);

        $filters = [
            'asset_type_id' => $asset->asset_type_id,
            'brand_id' => $asset->brand_id,
            'department_id' => $asset->department_id,
            'sub_department_id' => $subDepartment->id,
            'assigned_to' => $asset->assigned_to,
            'status' => 'Active',
            'warranty' => 'available',
            'amc' => 'available',
        ];

        $this->post(route('reports.generate'), $filters)
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertDatabaseHas('system_notifications', ['event_type' => 'report_generated']);

        $response = $this->get(route('reports.export', $filters));
        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString($asset->asset_tag, $csv);
        $this->assertStringNotContainsString('AST-UN-001', $csv);
    }

    public function test_unassigned_and_no_coverage_filters_return_only_matching_assets(): void
    {
        $template = Asset::firstOrFail();
Asset::create([
            'asset_tag' => 'AST-NC-001',
            'name' => 'No Coverage Asset',
            'asset_type_id' => $template->asset_type_id,
            'status' => 'In Stock',
            'assigned_to' => null,
            'warranty_expiry' => null,
            'amc_expiry' => null,
        ]);

        $response = $this->get(route('reports.export', [
            'assigned_to' => '__unassigned__',
            'status' => 'In Stock',
            'warranty' => 'none',
            'amc' => 'none',
        ]));

        $csv = $response->streamedContent();
        $this->assertStringContainsString('AST-NC-001', $csv);
        $this->assertStringNotContainsString($template->asset_tag, $csv);
    }

    public function test_invalid_related_filters_are_rejected(): void
    {
        $asset = Asset::firstOrFail();
        $otherSubDepartment = SubDepartment::where('department_id', '!=', $asset->department_id)->firstOrFail();

        $this->post(route('reports.generate'), [
            'department_id' => $asset->department_id,
            'sub_department_id' => $otherSubDepartment->id,
            'warranty' => 'unknown',
        ])->assertSessionHasErrors(['sub_department_id', 'warranty']);
    }
}
