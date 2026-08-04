<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\SystemNotification;
use App\Models\Vendor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asStaticUser();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_dashboard_uses_live_current_module_data(): void
    {
        Vendor::create([
            'code' => 'VN-DASH',
            'name' => 'Dashboard Vendor',
            'vendor_type' => 'OEM',
            'amc_status' => 'Renewal Due',
            'contract_end' => today()->addDays(20),
            'status' => 'Active',
        ]);
        SystemNotification::create([
            'event_type' => 'warranty_expiry',
            'title' => 'Dashboard warranty alert',
            'message' => 'Coverage requires attention.',
            'severity' => 'warning',
            'module' => 'Assets',
            'in_app_visible' => true,
        ]);
        AuditLog::create([
            'actor_name' => 'Arjun Sharma',
            'action' => 'UPDATE',
            'module' => 'Assets',
            'description' => 'Dashboard asset updated.',
            'result' => 'Success',
        ]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Total Assets')
            ->assertSee('Users')
            ->assertSee('Access Accounts')
            ->assertSee('Active Vendors')
            ->assertSee('Dashboard Vendor')
            ->assertSee('Dashboard warranty alert')
            ->assertSee('Dashboard asset updated.')
            ->assertSee('AST-LT-2486')
            ->assertDontSee('Recent Tickets')
            ->assertDontSee('License Expiry');
    }

    public function test_dashboard_activity_period_and_custom_range_are_validated(): void
    {
        $this->get(route('dashboard', ['period' => 'week']))
            ->assertOk()
            ->assertSee('System Activity');

        $this->get(route('dashboard', [
            'from' => today()->subDays(3)->format('Y-m-d'),
            'to' => today()->format('Y-m-d'),
        ]))->assertOk();

        $this->get(route('dashboard', [
            'from' => today()->format('Y-m-d'),
            'to' => today()->subDay()->format('Y-m-d'),
        ]))->assertSessionHasErrors('to');
    }
}
