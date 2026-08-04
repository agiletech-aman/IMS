<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetType;
use App\Models\Brand;
use App\Models\Department;
use App\Support\AssetCsv;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class AssetManagementCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asStaticUser();
    }

    public function test_master_records_can_be_created_updated_and_deleted(): void
    {
        $this->post(route('asset-management.departments.store'), [
            'name' => 'Finance', 'code' => 'DEP-FIN', 'head_name' => 'Kavita Joshi', 'status' => 'Active',
        ])->assertRedirect();
        $department = Department::where('name', 'Finance')->firstOrFail();
        $this->assertSame('DT-001', $department->code);

        $this->put(route('asset-management.departments.update', $department), [
            'name' => 'Finance & Accounts', 'code' => 'DEP-FIN', 'head_name' => 'Kavita Joshi', 'status' => 'Active',
        ])->assertRedirect();
        $this->assertDatabaseHas('departments', ['id' => $department->id, 'name' => 'Finance & Accounts']);

        $this->delete(route('asset-management.departments.destroy', $department))->assertRedirect();
        $this->assertDatabaseMissing('departments', ['id' => $department->id]);
    }

    public function test_brand_logo_and_asset_image_are_stored(): void
    {
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');

        $this->post(route('asset-management.brands.store'), [
            'name' => 'Lenovo', 'code' => 'BR-LENOVO', 'country' => 'China', 'status' => 'Active',
            'logo' => UploadedFile::fake()->createWithContent('lenovo.png', $png),
        ])->assertRedirect();
        $brand = Brand::where('name', 'Lenovo')->firstOrFail();
        $this->assertSame('BD-001', $brand->code);
        Storage::disk('public')->assertExists($brand->logo_path);

        $category = AssetCategory::where('code', 'CAT-HW')->firstOrFail();
        $type = AssetType::where('code', 'TYPE-LT')->firstOrFail();
        $department = Department::where('code', 'DEP-IT')->firstOrFail();
        $response = $this->post(route('assets.store'), [
            'asset_tag' => 'AST-LT-3000', 'name' => 'ThinkPad X1',
            'asset_category_id' => $category->id, 'asset_type_id' => $type->id,
            'brand_id' => $brand->id, 'department_id' => $department->id,
            'installation_date' => '2026-06-24', 'status' => 'In Stock',
            'image' => UploadedFile::fake()->createWithContent('asset.png', $png),
        ]);

        $asset = Asset::where('name', 'ThinkPad X1')->firstOrFail();
        $this->assertSame('AST-T1-001', $asset->asset_tag);
        $response->assertRedirect(route('assets.show', $asset));
        $this->assertFalse(Schema::hasColumn('assets', 'purchase_cost'));
        $this->assertSame('2026-06-24', $asset->installation_date->format('Y-m-d'));
        Storage::disk('public')->assertExists($asset->image_path);

        $this->post(route('assets.store'), [
            'name' => 'ThinkPad X1', 'asset_category_id' => $category->id,
            'asset_type_id' => $type->id, 'status' => 'In Stock',
        ])->assertRedirect();
        $this->assertDatabaseHas('assets', ['name' => 'ThinkPad X1', 'asset_tag' => 'AST-T1-002']);
    }

    public function test_all_master_codes_follow_the_defined_formats(): void
    {
        $this->post(route('asset-management.departments.store'), [
            'name' => 'Operations', 'code' => 'MANUAL', 'status' => 'Active',
        ])->assertRedirect();
        $department = Department::where('name', 'Operations')->firstOrFail();

        $this->post(route('asset-management.sub-departments.store'), [
            'department_id' => $department->id, 'name' => 'Support', 'code' => 'MANUAL', 'status' => 'Active',
        ])->assertRedirect();
        $this->post(route('asset-management.categories.store'), [
            'name' => 'Hardware', 'code' => 'MANUAL', 'status' => 'Active',
        ])->assertRedirect();
        $category = AssetCategory::where('name', 'Hardware')->firstOrFail();
        $this->post(route('asset-management.types.store'), [
            'asset_category_id' => $category->id, 'name' => 'Laptop', 'code' => 'MANUAL', 'status' => 'Active',
        ])->assertRedirect();

        $this->assertDatabaseHas('departments', ['name' => 'Operations', 'code' => 'DT-001']);
        $this->assertDatabaseHas('sub_departments', ['name' => 'Support', 'code' => 'ST-001']);
        $this->assertDatabaseHas('asset_categories', ['name' => 'Hardware', 'code' => 'CY-001']);
        $this->assertDatabaseHas('asset_types', ['name' => 'Laptop', 'code' => 'TE-001']);
    }

    public function test_asset_index_and_detail_render_the_selected_database_record(): void
    {
        $this->seed(DatabaseSeeder::class);
        $asset = Asset::firstOrFail();
        $asset->update(['name' => 'Database Driven Asset']);

        $this->get(route('assets.index'))
            ->assertOk()
            ->assertSee('Database Driven Asset')
            ->assertSee(route('assets.show', $asset));

        $this->get(route('assets.show', $asset))
            ->assertOk()
            ->assertSee('Database Driven Asset')
            ->assertSee($asset->asset_tag)
            ->assertSee('confirmationModal')
            ->assertSee('data-confirm', false)
            ->assertDontSee('confirm(', false);
    }

    public function test_asset_can_be_deleted_from_the_index_action(): void
    {
        $this->seed(DatabaseSeeder::class);
        $asset = Asset::firstOrFail();

        $this->get(route('assets.index'))
            ->assertOk()
            ->assertSee(route('assets.destroy', $asset))
            ->assertSee('Delete Asset?', false)
            ->assertSee('fa-trash-can', false);

        $this->delete(route('assets.destroy', $asset))
            ->assertRedirect(route('assets.index'))
            ->assertSessionHas('success', 'Asset deleted successfully.');

        $this->assertDatabaseMissing('assets', ['id' => $asset->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'DELETE',
            'module' => 'Assets',
            'auditable_id' => $asset->id,
        ]);
    }

    public function test_warranty_and_amc_are_optional_and_can_be_removed(): void
    {
        $this->seed(DatabaseSeeder::class);
        $asset = Asset::firstOrFail();
        $asset->update([
            'warranty_expiry' => '2029-06-24',
            'amc_expiry' => '2028-06-24',
        ]);

        $this->get(route('assets.edit', $asset))
            ->assertOk()
            ->assertSee('Optional — add coverage only when it applies to this asset.')
            ->assertSee('data-coverage-toggle', false);

        $this->put(route('assets.update', $asset), [
            'name' => $asset->name,
            'asset_category_id' => $asset->asset_category_id,
            'asset_type_id' => $asset->asset_type_id,
            'status' => $asset->status,
            'warranty_enabled' => '0',
            'warranty_expiry' => '',
            'amc_enabled' => '0',
            'amc_expiry' => '',
        ])->assertRedirect(route('assets.show', $asset));

        $asset->refresh();
        $this->assertNull($asset->warranty_expiry);
        $this->assertNull($asset->amc_expiry);
    }

    public function test_styled_excel_file_can_be_imported(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);

        $path = tempnam(sys_get_temp_dir(), 'asset_import_').'.xlsx';
        AssetCsv::exportStyledImportSample($path);
        $spreadsheet = IOFactory::load($path);
        $spreadsheet->getActiveSheet()->fromArray([
            'Test Import Laptop', 'Hardware', 'Laptop', 'Apple', 'IT Operations', '',
            'Model X', 'IMPORT-SERIAL-001', '2026-01-01', '2026-01-02', 'Mumbai',
            'Test User', 'Active', '2029-01-01', '2028-01-01', 'Imported from Excel',
        ], null, 'A2');
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);

        $response = $this->post(route('assets.import'), [
            'csv' => UploadedFile::fake()->createWithContent('assets.xlsx', file_get_contents($path)),
        ]);
        unlink($path);

        $response->assertRedirect()->assertSessionHas('importResult.inserted', 1);
        $this->assertDatabaseHas('assets', [
            'name' => 'Test Import Laptop',
            'serial_number' => 'IMPORT-SERIAL-001',
        ]);
        $this->assertNotEmpty(Asset::where('serial_number', 'IMPORT-SERIAL-001')->value('asset_tag'));
    }

    public function test_styled_import_sample_can_be_downloaded(): void
    {
        $response = $this->get(route('assets.import-sample'));

        $response->assertOk()->assertDownload('assets_import_sample.xlsx');
        $sheet = IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();

        $this->assertSame(AssetCsv::IMPORT_HEADINGS, $sheet->rangeToArray('A1:P1')[0]);
    }

    public function test_import_errors_are_returned_with_excel_row_details(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);

        $path = tempnam(sys_get_temp_dir(), 'invalid_asset_import_').'.xlsx';
        AssetCsv::exportStyledImportSample($path);
        $spreadsheet = IOFactory::load($path);
        $spreadsheet->getActiveSheet()->fromArray([
            'Invalid Laptop', 'Unknown Category', 'Laptop', 'Apple', 'IT Operations', '',
            'Model X', 'INVALID-SERIAL-001', '01/01/2026', '', 'Mumbai', '',
            'Wrong Status', '', '', '',
        ], null, 'A2');
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);

        $response = $this->post(route('assets.import'), [
            'csv' => UploadedFile::fake()->createWithContent('invalid-assets.xlsx', file_get_contents($path)),
        ]);
        unlink($path);

        $response->assertRedirect()
            ->assertSessionHas('openAssetImportModal', true)
            ->assertSessionHas('warning')
            ->assertSessionHas('importErrors.0.row', 2)
            ->assertSessionHas('importErrors.0.asset', 'Invalid Laptop');

        $messages = session('importErrors.0.messages');
        $this->assertContains('Asset category not found: Unknown Category', $messages);
        $this->assertContains('Purchase Date must use YYYY-MM-DD format.', $messages);
        $this->assertDatabaseMissing('assets', ['serial_number' => 'INVALID-SERIAL-001']);
    }

    public function test_duplicate_serial_number_is_not_updated_and_returns_warning(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        $existing = Asset::where('serial_number', 'C02XG7H9MD6T')->firstOrFail();
        $originalName = $existing->name;

        $path = tempnam(sys_get_temp_dir(), 'duplicate_asset_import_').'.xlsx';
        AssetCsv::exportStyledImportSample($path);
        $spreadsheet = IOFactory::load($path);
        $spreadsheet->getActiveSheet()->fromArray([
            'Changed Duplicate Name', 'Hardware', 'Laptop', 'Apple', 'IT Operations', '',
            'Model X', 'C02XG7H9MD6T', '2026-01-01', '', 'Mumbai', '',
            'Active', '', '', '',
        ], null, 'A2');
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);

        $response = $this->post(route('assets.import'), [
            'csv' => UploadedFile::fake()->createWithContent('duplicate-assets.xlsx', file_get_contents($path)),
        ]);
        unlink($path);

        $response->assertRedirect()
            ->assertSessionHas('warning')
            ->assertSessionHas('openAssetImportModal', true)
            ->assertSessionHas('importErrors.0.row', 2)
            ->assertSessionHas('importErrors.0.messages.0', "Serial Number 'C02XG7H9MD6T' already exists.");

        $this->assertSame($originalName, $existing->fresh()->name);
        $this->assertDatabaseMissing('assets', ['name' => 'Changed Duplicate Name']);
    }
}
