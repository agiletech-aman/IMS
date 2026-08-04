<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\AssetCategory;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComplaintManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asStaticUser();
    }

    public function test_complaint_moves_through_the_required_workflow_in_order(): void
    {
        AssetCategory::create([
            'name' => 'Hardware',
            'code' => 'CAT-HW',
            'status' => 'Active',
        ]);

        $this->post(route('complaints.store'), [
            'subject' => 'Conference room display is offline',
            'description' => 'The display does not power on.',
            'requester_name' => 'Aman Sharma',
            'requester_email' => 'aman@example.com',
            'category' => 'Hardware',
            'priority' => 'High',
        ])->assertRedirect()->assertSessionHas('success');

        $complaint = Complaint::firstOrFail();
        $this->assertSame('CMP-001', $complaint->complaint_number);
        $this->assertSame('Complaint Raised', $complaint->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'CREATE',
            'module' => 'Complaint Management',
            'description' => 'CMP-001 was created.',
            'auditable_id' => $complaint->id,
        ]);

        $engineer = User::factory()->create(['name' => 'Service Engineer', 'status' => 'Active']);
        $this->patch(route('complaints.assign', $complaint), [
            'engineer_id' => $engineer->id,
            'visit_scheduled_at' => '2026-08-04 10:00:00',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('complaints', [
            'id' => $complaint->id,
            'engineer_id' => $engineer->id,
            'status' => 'Engineer Assigned',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'UPDATE',
            'module' => 'Complaint Management',
            'auditable_id' => $complaint->id,
            'route_name' => 'complaints.assign',
        ]);

        foreach (['Engineer Visit', 'Work in Progress'] as $status) {
            $this->patch(route('complaints.status', $complaint), [
                'status' => $status,
                'note' => "Moved to {$status}",
            ])->assertRedirect()->assertSessionHas('success');
            $complaint->refresh();
        }

        $this->patch(route('complaints.status', $complaint), [
            'status' => 'Resolved',
            'resolution_notes' => 'Replaced the faulty power adapter and verified the display.',
        ])->assertRedirect()->assertSessionHas('success');
        $complaint->refresh();

        $this->patch(route('complaints.status', $complaint), [
            'status' => 'Closed',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('Closed', $complaint->fresh()->status);
        $this->assertDatabaseCount('complaint_activities', 6);
    }

    public function test_complaint_cannot_skip_stages_or_resolve_without_notes(): void
    {
        $engineer = User::factory()->create(['status' => 'Active']);
        $complaint = Complaint::create([
            'complaint_number' => 'CMP-001',
            'subject' => 'Network issue',
            'description' => 'No connectivity.',
            'requester_name' => 'Requester',
            'category' => 'Network',
            'priority' => 'Critical',
            'engineer_id' => $engineer->id,
            'status' => 'Engineer Assigned',
        ]);

        $this->patch(route('complaints.status', $complaint), ['status' => 'Resolved'])
            ->assertSessionHasErrors('status');
        $this->assertSame('Engineer Assigned', $complaint->fresh()->status);

        $complaint->update(['status' => 'Work in Progress']);
        $this->patch(route('complaints.status', $complaint), ['status' => 'Resolved'])
            ->assertSessionHasErrors('resolution_notes');
        $this->assertSame('Work in Progress', $complaint->fresh()->status);
    }

    public function test_complaint_module_is_visible_under_service_management(): void
    {
        $this->get(route('complaints.index'))
            ->assertOk()
            ->assertSee('Complaint Management')
            ->assertSee('Complaint Raised')
            ->assertSee('Engineer Visit')
            ->assertSee('Work in Progress');

        $this->get(route('audit-logs.index'))
            ->assertOk()
            ->assertSee('value="Complaint Management"', false);
    }

    public function test_complaint_category_comes_from_active_category_table(): void
    {
        AssetCategory::create([
            'name' => 'Printer Support',
            'code' => 'CAT-PRINT',
            'status' => 'Active',
        ]);
        AssetCategory::create([
            'name' => 'Retired Category',
            'code' => 'CAT-OLD',
            'status' => 'Inactive',
        ]);

        $this->get(route('complaints.create'))
            ->assertOk()
            ->assertSee('Printer Support')
            ->assertDontSee('Retired Category');

        $this->post(route('complaints.store'), [
            'subject' => 'Invalid category complaint',
            'description' => 'Testing category validation.',
            'requester_name' => 'Requester',
            'category' => 'Hardware',
            'priority' => 'Medium',
        ])->assertSessionHasErrors('category');
    }

    public function test_complaint_permissions_are_available_and_enforced_for_each_role(): void
    {
        $this->get(route('roles-permissions.index'))
            ->assertOk()
            ->assertSee('Complaint Management')
            ->assertSee('Assign');

        $expected = [
            'Asset Manager' => [true, true, true, false, true, true],
            'Sub admin' => [true, true, true, true, true, true],
            'Auditor' => [true, false, false, false, false, true],
            'Viewer' => [true, true, false, false, false, false],
        ];

        foreach ($expected as $role => [$view, $create, $update, $delete, $assign, $export]) {
            $permission = RolePermission::where('role', $role)
                ->where('module', 'complaints')
                ->firstOrFail();

            $this->assertSame($view, $permission->can_view);
            $this->assertSame($create, $permission->can_create);
            $this->assertSame($update, $permission->can_update);
            $this->assertSame($delete, $permission->can_delete);
            $this->assertSame($assign, $permission->can_assign);
            $this->assertSame($export, $permission->can_export);
        }

        $viewer = $this->withSession([
            'static_auth_user' => [
                'name' => 'Viewer Account',
                'email' => 'viewer@example.com',
                'role' => 'Viewer',
                'initials' => 'VA',
            ],
        ]);

        $viewer->get(route('complaints.index'))->assertOk()->assertSee('Complaint Management');
        $viewer->get(route('complaints.create'))->assertOk();

        $complaint = Complaint::create([
            'complaint_number' => 'CMP-900',
            'subject' => 'Permission check',
            'description' => 'Permission check.',
            'requester_name' => 'Viewer',
            'category' => 'Hardware',
            'priority' => 'Medium',
        ]);

        $viewer->patch(route('complaints.status', $complaint), [
            'status' => 'Engineer Assigned',
        ])->assertForbidden();
        $viewer->delete(route('complaints.destroy', $complaint))->assertForbidden();
        $viewer->get(route('complaints.export'))->assertForbidden();
    }

    public function test_complaints_can_be_exported_to_filtered_csv_and_the_export_is_audited(): void
    {
        Complaint::create([
            'complaint_number' => 'CMP-101',
            'subject' => 'Critical network outage',
            'description' => 'Network is unavailable.',
            'requester_name' => 'Network Team',
            'category' => 'Network',
            'priority' => 'Critical',
            'status' => 'Complaint Raised',
        ]);
        Complaint::create([
            'complaint_number' => 'CMP-102',
            'subject' => 'Low priority mouse issue',
            'description' => 'Mouse needs replacement.',
            'requester_name' => 'Design Team',
            'category' => 'Hardware',
            'priority' => 'Low',
            'status' => 'Closed',
        ]);

        $response = $this->get(route('complaints.export', [
            'priority' => 'Critical',
            'status' => 'Complaint Raised',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $csv = $response->streamedContent();
        $this->assertStringContainsString('CMP-101', $csv);
        $this->assertStringNotContainsString('CMP-102', $csv);
        $this->assertStringContainsString('Critical network outage', $csv);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'EXPORT',
            'module' => 'Complaint Management',
            'route_name' => 'complaints.export',
        ]);
    }
}
