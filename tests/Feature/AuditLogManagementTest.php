<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_crud_and_alert_activity_is_recorded_with_actor_and_changes(): void
    {
        $this->asStaticUser()->post(route('vendors.store'), [
            'name' => 'Audit Vendor',
            'vendor_type' => 'OEM',
            'amc_status' => 'Active',
            'status' => 'Active',
        ])->assertRedirect();

        $vendor = Vendor::where('name', 'Audit Vendor')->firstOrFail();
        $this->assertDatabaseHas('audit_logs', [
            'actor_name' => 'Arjun Sharma',
            'action' => 'CREATE',
            'module' => 'Vendors',
            'auditable_id' => $vendor->id,
            'result' => 'Success',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'CREATE',
            'module' => 'Alerts',
        ]);

        $this->asStaticUser()->put(route('vendors.update', $vendor), [
            'name' => 'Audit Vendor Updated',
            'vendor_type' => 'OEM',
            'amc_status' => 'Renewal Due',
            'status' => 'Active',
        ])->assertRedirect();

        $updateLog = AuditLog::where('module', 'Vendors')->where('action', 'UPDATE')->latest()->firstOrFail();
        $this->assertSame('Audit Vendor', $updateLog->old_values['name']);
        $this->assertSame('Audit Vendor Updated', $updateLog->new_values['name']);

        $this->asStaticUser()->delete(route('vendors.destroy', $vendor))->assertRedirect();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'DELETE',
            'module' => 'Vendors',
            'auditable_id' => $vendor->id,
        ]);
    }

    public function test_assignment_login_failure_and_report_actions_are_recorded(): void
    {
        $this->post(route('login.attempt'), [
            'email' => 'invalid@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'LOGIN FAILED',
            'module' => 'Authentication',
            'result' => 'Blocked',
        ]);

        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create(['name' => 'Audit Recipient']);
        $asset = Asset::firstOrFail();
        $asset->update(['assigned_to' => null, 'status' => 'In Stock']);

        $this->asStaticUser()->post(route('users.assign-asset', $user), [
            'asset_type_id' => $asset->asset_type_id,
            'asset_id' => $asset->id,
        ])->assertRedirect();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ASSIGN',
            'module' => 'Assets',
            'auditable_id' => $asset->id,
        ]);

        $this->asStaticUser()->post(route('reports.generate'), [])
            ->assertRedirect();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'GENERATE',
            'module' => 'Reports',
        ]);
    }

    public function test_audit_page_filters_and_exports_real_logs(): void
    {
        AuditLog::create([
            'actor_name' => 'Arjun Sharma',
            'action' => 'UPDATE',
            'module' => 'Assets',
            'description' => 'AST-TEST was updated.',
            'result' => 'Success',
            'ip_address' => '127.0.0.1',
        ]);
        AuditLog::create([
            'actor_name' => 'System',
            'action' => 'CREATE',
            'module' => 'Alerts',
            'description' => 'Warranty alert created.',
            'result' => 'Success',
        ]);

        $this->asStaticUser()->get(route('audit-logs.index', [
            'module' => 'Assets',
            'action' => 'UPDATE',
            'search' => 'AST-TEST',
        ]))
            ->assertOk()
            ->assertSee('AST-TEST was updated.')
            ->assertDontSee('Warranty alert created.');

        $csv = $this->asStaticUser()->get(route('audit-logs.export', ['module' => 'Assets']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('AST-TEST was updated.', $csv);
        $this->assertStringNotContainsString('Warranty alert created.', $csv);
    }

    public function test_only_super_admin_can_clear_logs_after_a_csv_backup_warning(): void
    {
        AuditLog::create([
            'actor_name' => 'System',
            'action' => 'CREATE',
            'module' => 'Assets',
            'description' => 'First record to clear.',
            'result' => 'Success',
        ]);
        AuditLog::create([
            'actor_name' => 'System',
            'action' => 'UPDATE',
            'module' => 'Users',
            'description' => 'Second record to clear.',
            'result' => 'Success',
        ]);

        $this->asStaticUser()
            ->get(route('audit-logs.index'))
            ->assertOk()
            ->assertSee('Optimize Audit Data')
            ->assertSee('For database optimization only.')
            ->assertSee('data-bs-toggle="tooltip"', false)
            ->assertSee('Clear Logs for Data Optimization?', false);

        $this->withSession([
            'static_auth_user' => [
                'name' => 'Sub Admin',
                'email' => 'subadmin@example.com',
                'role' => 'Sub admin',
                'initials' => 'SA',
            ],
        ])->delete(route('audit-logs.clear'))->assertForbidden();

        $this->asStaticUser()
            ->delete(route('audit-logs.clear'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('audit_logs', ['description' => 'First record to clear.']);
        $this->assertDatabaseMissing('audit_logs', ['description' => 'Second record to clear.']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'CLEAR',
            'module' => 'Audit Logs',
            'actor_name' => 'Arjun Sharma',
        ]);
        $this->assertSame(1, AuditLog::count());
    }
}
