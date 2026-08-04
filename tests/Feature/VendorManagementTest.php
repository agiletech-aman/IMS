<?php

namespace Tests\Feature;

use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asStaticUser();
    }

    public function test_vendor_oem_records_are_database_backed_and_manageable(): void
    {
        $response = $this->post(route('vendors.store'), [
            'name' => 'Agile Hardware Partner',
            'vendor_type' => 'OEM',
            'category' => 'Hardware',
            'contact_person' => 'Aman Sharma',
            'phone' => '9876543210',
            'email' => 'vendor@example.com',
            'website' => 'https://example.com',
            'amc_status' => 'Active',
            'contract_start' => '2026-01-01',
            'contract_end' => '2027-01-01',
            'preferred' => '1',
            'rating' => '4.8',
            'status' => 'Active',
        ]);

        $vendor = Vendor::where('name', 'Agile Hardware Partner')->firstOrFail();
        $response->assertRedirect()->assertSessionHas('success');
        $this->assertSame('VN-001', $vendor->code);
        $this->assertTrue($vendor->preferred);

        $this->put(route('vendors.update', $vendor), [
            'name' => 'Updated OEM Partner',
            'vendor_type' => 'Vendor & OEM',
            'category' => 'Network',
            'amc_status' => 'Renewal Due',
            'preferred' => '0',
            'status' => 'Inactive',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('vendors', [
            'id' => $vendor->id,
            'name' => 'Updated OEM Partner',
            'amc_status' => 'Renewal Due',
            'preferred' => false,
        ]);

        $this->delete(route('vendors.destroy', $vendor))
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('vendors', ['id' => $vendor->id]);
    }

    public function test_vendor_validation_rejects_invalid_values(): void
    {
        $this->post(route('vendors.store'), [
            'name' => 'Invalid Vendor',
            'vendor_type' => 'Unknown',
            'amc_status' => 'Invalid',
            'rating' => '7',
            'status' => 'Active',
        ])->assertSessionHasErrors(['vendor_type', 'amc_status', 'rating']);

        $this->assertDatabaseCount('vendors', 0);
    }
}
