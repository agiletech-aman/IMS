<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetType;
use App\Models\Brand;
use App\Models\Department;
use App\Models\SubDepartment;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::firstOrCreate(['email' => 'test@example.com'], [
            'name' => 'Test User',
            'password' => 'password',
        ]);

        $it = Department::firstOrCreate(['code' => 'DEP-IT'], ['name' => 'IT Operations', 'head_name' => 'Arjun Sharma', 'status' => 'Active']);
        $design = Department::firstOrCreate(['code' => 'DEP-DSN'], ['name' => 'Design', 'head_name' => 'Riya Mehta', 'status' => 'Active']);
        $infra = SubDepartment::firstOrCreate(['code' => 'SUB-INFRA'], ['department_id' => $it->id, 'name' => 'Infrastructure', 'manager_name' => 'Vikram Singh', 'status' => 'Active']);
        SubDepartment::firstOrCreate(['code' => 'SUB-PD'], ['department_id' => $design->id, 'name' => 'Product Design', 'manager_name' => 'Riya Mehta', 'status' => 'Active']);

        $hardware = AssetCategory::firstOrCreate(['code' => 'CAT-HW'], ['name' => 'Hardware', 'description' => 'End-user computing devices', 'status' => 'Active']);
        $infrastructure = AssetCategory::firstOrCreate(['code' => 'CAT-INF'], ['name' => 'Infrastructure', 'description' => 'Core server and network assets', 'status' => 'Active']);
        $laptop = AssetType::firstOrCreate(['code' => 'TYPE-LT'], ['asset_category_id' => $hardware->id, 'name' => 'Laptop', 'status' => 'Active']);
        AssetType::firstOrCreate(['code' => 'TYPE-SV'], ['asset_category_id' => $infrastructure->id, 'name' => 'Server', 'status' => 'Active']);
        $apple = Brand::firstOrCreate(['code' => 'BR-APPLE'], ['name' => 'Apple', 'country' => 'United States', 'status' => 'Active']);
        Brand::firstOrCreate(['code' => 'BR-DELL'], ['name' => 'Dell', 'country' => 'United States', 'status' => 'Active']);

        Asset::firstOrCreate(['asset_tag' => 'AST-LT-2486'], [
            'name' => 'MacBook Pro 14”', 'asset_category_id' => $hardware->id, 'asset_type_id' => $laptop->id,
            'brand_id' => $apple->id, 'department_id' => $design->id, 'model' => 'MacBook Pro M3',
            'serial_number' => 'C02XG7H9MD6T', 'purchase_date' => '2025-01-18', 'installation_date' => '2025-01-22',
            'location' => 'Mumbai HQ', 'assigned_to' => 'Riya Mehta', 'status' => 'Active',
            'warranty_expiry' => '2028-01-17', 'amc_expiry' => '2027-01-17',
        ]);
    }
}
