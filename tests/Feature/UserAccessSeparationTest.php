<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAccessSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_directory_creates_a_user_record(): void
    {
        $this->asStaticUser()->post(route('users.store'), [
            'name' => 'New Asset Assignee',
            'email' => 'assignee@example.com',
            'contact' => '9876543210',
            'role' => 'Viewer',
            'login_enabled' => '0',
            'status' => 'Active',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'email' => 'assignee@example.com',
            'login_enabled' => false,
            'status' => 'Active',
        ]);
    }

    public function test_user_cannot_login_but_viewer_can_open_dashboard(): void
    {
        $user = User::factory()->create([
            'name' => 'External Assignee',
            'email' => 'external@example.com',
            'role' => 'Viewer',
            'login_enabled' => false,
            'status' => 'Active',
        ]);
        $viewer = User::factory()->create([
            'name' => 'Dashboard Viewer',
            'email' => 'viewer@example.com',
            'role' => 'Viewer',
            'login_enabled' => true,
            'status' => 'Active',
        ]);

        $this->post(route('login.attempt'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->post(route('login.attempt'), [
            'email' => $viewer->email,
            'password' => 'password',
        ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('static_auth_user.role', 'Viewer');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Dashboard');
    }

    public function test_directories_keep_users_and_login_accounts_separate(): void
    {
        User::factory()->create([
            'name' => 'Asset Assignee',
            'login_enabled' => false,
            'role' => 'Viewer',
            'status' => 'Active',
        ]);
        User::factory()->create([
            'name' => 'Viewer Account',
            'login_enabled' => true,
            'role' => 'Viewer',
            'status' => 'Active',
        ]);

        $this->asStaticUser()->get(route('users.index'))
            ->assertOk()
            ->assertSee('Asset Assignee')
            ->assertDontSee('Viewer Account');

        $this->asStaticUser()->get(route('users.index', ['role' => 'Viewer']))
            ->assertOk()
            ->assertSee('Viewer Account')
            ->assertDontSee('Asset Assignee');
    }
}
