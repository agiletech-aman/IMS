<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserAccessSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_directory_creates_a_user_record(): void
    {
        $this->asStaticUser()->withSession(['selected_centre' => 'lucknow'])->post(route('users.store'), [
            'name' => 'New Asset Assignee',
            'email' => 'assignee@example.com',
            'contact' => '9876543210',
            'role' => 'Viewer',
            'login_enabled' => '0',
            'status' => 'Active',
            'department_id' => DB::table('departments')->insertGetId([
                'name' => 'Faculty Department',
                'code' => 'DEP-FAC',
                'status' => 'Active',
                'centre' => 'lucknow',
                'created_at' => now(),
                'updated_at' => now(),
            ]),
            'fb_type' => 'New',
            'room_number' => '101',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('faculties', [
            'email' => 'assignee@example.com',
            'status' => 'Active',
        ]);
    }

    public function test_user_import_creates_a_user_without_login_role(): void
    {
        DB::table('departments')->insert([
            'name' => 'IT Operations',
            'code' => 'DEP-IT',
            'status' => 'Active',
            'centre' => 'lucknow',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->asStaticUser()
            ->withSession(['selected_centre' => 'lucknow'])
            ->post(route('users.import'), [
                'csv' => UploadedFile::fake()->createWithContent(
                    'users.csv',
                    "Name,Email,Contact,Address,Department,FB Type,Room Number,Remark,Status\nImported User,imported@example.com,9876543210,Lucknow,IT Operations,New,101,Imported faculty,Active\n",
                ),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('faculties', [
            'email' => 'imported@example.com',
            'centre' => 'lucknow',
            'fb_type' => 'New',
            'room_number' => '101',
            'remark' => 'Imported faculty',
        ]);
    }

    public function test_user_import_rejects_unknown_department(): void
    {
        $response = $this->asStaticUser()
            ->withSession(['selected_centre' => 'lucknow'])
            ->post(route('users.import'), [
                'csv' => UploadedFile::fake()->createWithContent(
                    'users.csv',
                    "Name,Email,Contact,Address,Department,FB Type,Room Number,Remark,Status\nUnknown User,unknown@example.com,,,,New,101,,Active\n",
                ),
            ]);

        $response->assertRedirect()->assertSessionHas('userImportErrors');
        $this->assertDatabaseMissing('faculties', ['email' => 'unknown@example.com']);
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

        $this->asStaticUser()->withSession(['selected_centre' => 'lucknow'])->get(route('users.index'))
            ->assertOk()
            ->assertSee('Asset Assignee')
            ->assertDontSee('Viewer Account');

        $this->asStaticUser()->withSession(['selected_centre' => 'lucknow'])->get(route('users.index', ['role' => 'Viewer']))
            ->assertOk()
            ->assertSee('Viewer Account')
            ->assertDontSee('Asset Assignee');
    }
}
