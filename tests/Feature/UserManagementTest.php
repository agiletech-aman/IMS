<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asStaticUser();
    }

    public function test_user_can_be_created_with_profile_fields_and_generated_id(): void
    {
        Storage::fake('public');
        $image = UploadedFile::fake()->image('profile.jpg');

        $response = $this->post(route('users.store'), [
            'name' => 'Aman Sharma',
            'email' => 'aman@example.com',
            'contact' => '9876543210',
            'address' => 'Noida, Uttar Pradesh',
            'role' => 'Asset Manager',
            'status' => 'Active',
            'image' => $image,
        ]);

        $user = User::where('email', 'aman@example.com')->firstOrFail();
        $response->assertRedirect()->assertSessionHas('success');
        $this->assertSame('USR-001', $user->unique_id);
        $this->assertSame('9876543210', $user->contact);
        $this->assertSame('Noida, Uttar Pradesh', $user->address);
        Storage::disk('public')->assertExists($user->image_path);
    }

    public function test_user_can_be_updated_searched_and_deleted(): void
    {
        Storage::fake('public');
        $user = User::factory()->create([
            'unique_id' => 'USR-001',
            'name' => 'Old Name',
            'contact' => '1111111111',
            'status' => 'Active',
        ]);

        $this->put(route('users.update', $user), [
            'name' => 'Updated User',
            'email' => $user->email,
            'contact' => '9999999999',
            'address' => 'Delhi',
            'role' => 'Auditor',
            'status' => 'Inactive',
        ])->assertRedirect()->assertSessionHas('success');

        $this->get(route('users.index', ['search' => 'USR-001']))
            ->assertOk()
            ->assertSee('Updated User')
            ->assertSee('9999999999');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated User',
            'status' => 'Inactive',
        ]);

        $this->delete(route('users.destroy', $user))
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_user_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $this->post(route('users.store'), [
            'name' => 'Duplicate Email',
            'email' => 'existing@example.com',
            'role' => 'Viewer',
            'status' => 'Active',
        ])->assertSessionHasErrors('email');

        $this->assertSame(1, User::where('email', 'existing@example.com')->count());
    }

    public function test_available_asset_can_be_assigned_from_user_actions(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create([
            'name' => 'Asset Recipient',
            'status' => 'Active',
        ]);
        $asset = Asset::firstOrFail();
        $asset->update(['assigned_to' => null, 'status' => 'In Stock']);

        $this->get(route('users.index'))
            ->assertOk()
            ->assertSee('Assign asset')
            ->assertSee('Asset Type')
            ->assertSee(route('users.assign-asset', $user));

        $this->post(route('users.assign-asset', $user), [
            'asset_ids' => [$asset->id],
        ])->assertRedirect()->assertSessionHas('success');

        $asset->refresh();
        $this->assertSame('Asset Recipient', $asset->assigned_to);
        $this->assertSame('Active', $asset->status);
    }

    public function test_users_are_filtered_by_the_four_defined_roles(): void
    {
        User::factory()->create(['name' => 'Manager User', 'role' => 'Asset Manager', 'login_enabled' => true, 'status' => 'Active']);
        User::factory()->create(['name' => 'Viewer User', 'role' => 'Viewer', 'login_enabled' => true, 'status' => 'Active']);

        $this->get(route('users.index', ['role' => 'Asset Manager']))
            ->assertOk()
            ->assertSee('Asset Manager Access Accounts')
            ->assertSee('Manager User')
            ->assertDontSee('Viewer User');

        $this->get(route('users.index', ['role' => 'Viewer']))
            ->assertOk()
            ->assertSee('Viewer User')
            ->assertDontSee('Manager User');
    }

    public function test_only_the_four_defined_user_roles_can_be_saved(): void
    {
        $this->post(route('users.store'), [
            'name' => 'Sub Admin User',
            'email' => 'sub-admin@example.com',
            'role' => 'Sub admin',
            'login_enabled' => '1',
            'password' => 'secure-password',
            'status' => 'Active',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'email' => 'sub-admin@example.com',
            'role' => 'Sub admin',
        ]);

        $this->post(route('users.store'), [
            'name' => 'Invalid Role User',
            'email' => 'invalid-role@example.com',
            'role' => 'Administrator',
            'login_enabled' => '1',
            'password' => 'secure-password',
            'status' => 'Active',
        ])->assertSessionHasErrors('role');
    }
}
