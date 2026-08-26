<?php

namespace Tests\Feature;

use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_manage_the_dynamic_permission_matrix(): void
    {
        $this->asStaticUser()
            ->get(route('roles-permissions.index'))
            ->assertOk()
            ->assertSee('Save All Permissions')
            ->assertSee('Assign')
            ->assertSee('Import')
            ->assertSee('Export');

        $this->asStaticUser()
            ->post(route('roles-permissions.update'), [
                'permissions' => [
                    'Viewer' => [
                        'dashboard' => ['view' => '1'],
                        'vendors' => ['view' => '1', 'create' => '1'],
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('role_permissions', [
            'role' => 'Viewer',
            'module' => 'vendors',
            'can_view' => true,
            'can_create' => true,
            'can_update' => false,
            'can_delete' => false,
        ]);

        $this->assertDatabaseHas('role_permissions', [
            'role' => 'Viewer',
            'module' => 'dashboard',
            'can_view' => true,
        ]);
    }

    public function test_viewer_defaults_enforce_module_crud_import_export_and_sidebar_access(): void
    {
        $viewer = $this->roleSession('Viewer');

        $viewer->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Vendors / OEM')
            ->assertDontSee('Users & Access');

        $viewer->get(route('assets.index'))
            ->assertOk()
            ->assertDontSee('Add Asset')
            ->assertDontSee('Import CSV')
            ->assertDontSee('Export CSV');

        $viewer->get(route('assets.create'))->assertForbidden();
        $viewer->get(route('assets.export'))->assertForbidden();
        $viewer->get(route('assets.import-sample'))->assertForbidden();
        $viewer->get(route('vendors.index'))->assertForbidden();
        $viewer->get(route('users.index'))->assertForbidden();
        $viewer->get(route('settings.index'))
            ->assertOk()
            ->assertDontSee('Save General Settings')
            ->assertDontSee('Administrator Management');
        $viewer->get(route('audit-logs.index'))->assertForbidden();
        $viewer->post(route('roles-permissions.update'))->assertForbidden();
        $viewer->get(route('reports.export'))->assertSuccessful();
    }

    public function test_assign_permission_is_enforced_on_the_user_assignment_action(): void
    {
        $assignee = User::factory()->create([
            'login_enabled' => false,
            'status' => 'Active',
            'role' => 'Viewer',
        ]);

        $this->roleSession('Viewer')
            ->post(route('users.assign-asset', $assignee), [])
            ->assertForbidden();

        $this->assertTrue(
            RolePermission::where('role', 'Asset Manager')
                ->where('module', 'users')
                ->value('can_assign'),
        );

        $this->roleSession('Asset Manager')
            ->post(route('users.assign-asset', $assignee), [])
            ->assertRedirect();
    }

    public function test_permission_changes_take_effect_on_the_next_request(): void
    {
        $this->roleSession('Viewer')
            ->get(route('vendors.index'))
            ->assertForbidden();

        RolePermission::where('role', 'Viewer')
            ->where('module', 'vendors')
            ->update(['can_view' => true]);

        app(\App\Services\PermissionService::class)->clear();

        $this->roleSession('Viewer')
            ->get(route('vendors.index'))
            ->assertOk();
    }

    public function test_action_permission_cannot_bypass_module_access(): void
    {
        RolePermission::where('role', 'Viewer')
            ->where('module', 'vendors')
            ->update([
                'can_view' => false,
                'can_create' => true,
            ]);

        app(\App\Services\PermissionService::class)->clear();

        $this->roleSession('Viewer')
            ->post(route('vendors.store'), [])
            ->assertForbidden();

        $this->asStaticUser()
            ->post(route('roles-permissions.update'), [
                'permissions' => [
                    'Viewer' => [
                        'vendors' => ['create' => '1'],
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('role_permissions', [
            'role' => 'Viewer',
            'module' => 'vendors',
            'can_view' => false,
            'can_create' => false,
        ]);
    }

    public function test_settings_are_split_into_basic_and_advanced_access_groups(): void
    {
        $this->roleSession('Viewer')
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('General Settings')
            ->assertSee('Company Info')
            ->assertSee('Theme Settings')
            ->assertDontSee('SMTP Settings')
            ->assertDontSee('Notification Settings')
            ->assertDontSee('Security Settings');

        $this->roleSession('Viewer')
            ->post(route('settings.general.update'), [])
            ->assertForbidden();

        $this->roleSession('Viewer')
            ->post(route('settings.smtp.update'), [])
            ->assertForbidden();

        $this->roleSession('Sub admin')
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('General Settings')
            ->assertSee('SMTP Settings')
            ->assertSee('Notification Settings')
            ->assertSee('Security Settings')
            ->assertDontSee('Save General Settings')
            ->assertSee('Save SMTP Settings');

        $this->roleSession('Sub admin')
            ->post(route('settings.general.update'), [])
            ->assertForbidden();
    }

    public function test_only_super_admin_can_update_basic_settings(): void
    {
        $this->roleSession('Administrator')
            ->post(route('settings.general.update'), [
                'application_name' => 'IMS',
                'language' => 'en',
                'timezone' => 'Asia/Kolkata',
                'date_format' => 'd M Y',
            ])
            ->assertRedirect();

        foreach (['Asset Manager', 'Sub admin', 'Auditor', 'Viewer'] as $role) {
            $this->roleSession($role)
                ->post(route('settings.general.update'), [])
                ->assertForbidden();
        }
    }

    public function test_settings_feature_permission_still_requires_settings_module_access(): void
    {
        RolePermission::where('role', 'Viewer')
            ->where('module', 'settings')
            ->update(['can_view' => false]);
        RolePermission::where('role', 'Viewer')
            ->where('module', 'settings_basic')
            ->update(['can_view' => true, 'can_update' => true]);

        app(\App\Services\PermissionService::class)->clear();

        $this->roleSession('Viewer')
            ->get(route('settings.index'))
            ->assertForbidden();

        $this->roleSession('Viewer')
            ->post(route('settings.general.update'), [])
            ->assertForbidden();
    }

    private function roleSession(string $role): static
    {
        return $this->withSession([
            'static_auth_user' => [
                'name' => "{$role} Account",
                'email' => strtolower(str_replace(' ', '.', $role)).'@example.com',
                'role' => $role,
                'initials' => 'RA',
            ],
        ]);
    }
}
