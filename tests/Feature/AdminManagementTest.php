<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_administrator_uses_separate_table_and_can_login(): void
    {
        $admin = Admin::where('email', 'admin@nexacore.com')->firstOrFail();
        $this->assertSame('Arjun Sharma', $admin->name);
        $this->assertTrue(Hash::check('password', $admin->password));

        $this->post(route('login.attempt'), [
            'email' => 'admin@nexacore.com',
            'password' => 'password',
        ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('static_auth_user.admin_id', $admin->id)
            ->assertSessionHas('static_auth_user.role', 'Administrator');

        $this->assertNotNull($admin->fresh()->last_login_at);
    }

    public function test_administrators_can_be_created_updated_and_deleted_from_settings(): void
    {
        $this->asStaticUser()->post(route('settings.admins.store'), [
            'name' => 'Second Admin',
            'email' => 'second-admin@example.com',
            'password' => 'strong-password',
            'phone' => '9876543210',
            'designation' => 'IT Administrator',
            'address' => 'Noida',
            'status' => 'Active',
        ])->assertRedirect()->assertSessionHas('success');

        $admin = Admin::where('email', 'second-admin@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('strong-password', $admin->password));
        $this->assertSame('IT Administrator', $admin->designation);

        $this->asStaticUser()->put(route('settings.admins.update', $admin), [
            'name' => 'Updated Admin',
            'email' => 'second-admin@example.com',
            'phone' => '9999999999',
            'designation' => 'Senior Administrator',
            'address' => 'Delhi',
            'status' => 'Inactive',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('admins', [
            'id' => $admin->id,
            'name' => 'Updated Admin',
            'designation' => 'Senior Administrator',
            'status' => 'Inactive',
        ]);

        $this->asStaticUser()->delete(route('settings.admins.destroy', $admin))
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('admins', ['id' => $admin->id]);
    }

    public function test_settings_page_contains_admin_management_section(): void
    {
        $this->asStaticUser()->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Administrator Management')
            ->assertSee('admin@nexacore.com')
            ->assertSee('Add Administrator');
    }

    public function test_non_administrator_cannot_view_or_manage_admin_accounts(): void
    {
        $viewerSession = [
            'static_auth_user' => [
                'name' => 'Dashboard Viewer',
                'email' => 'viewer@example.com',
                'role' => 'Viewer',
                'initials' => 'DV',
            ],
        ];

        $this->withSession($viewerSession)->get(route('settings.index'))
            ->assertOk()
            ->assertDontSee('Administrator Management')
            ->assertDontSee('admin@nexacore.com');

        $this->withSession($viewerSession)->post(route('settings.admins.store'), [
            'name' => 'Unauthorized Admin',
            'email' => 'unauthorized@example.com',
            'password' => 'strong-password',
            'status' => 'Active',
        ])->assertForbidden();

        $this->assertDatabaseMissing('admins', ['email' => 'unauthorized@example.com']);
    }
}
