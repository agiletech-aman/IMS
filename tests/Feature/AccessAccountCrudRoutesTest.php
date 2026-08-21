<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessAccountCrudRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_access_account_page_uses_access_account_crud_actions(): void
    {
        $account = $this->viewerAccount();

        $response = $this->asAdministratorAtLucknow()
            ->get(route('access-accounts.index', ['role' => 'Viewer']));

        $response
            ->assertOk()
            ->assertSee('Viewer Access Accounts')
            ->assertSee(
                'action="'.route('access-accounts.store', ['role' => 'Viewer']).'"',
                false,
            )
            ->assertSee(
                'action="'.route('access-accounts.update', [
                    'accessAccount' => $account,
                    'role' => 'Viewer',
                ]).'"',
                false,
            )
            ->assertSee(
                'action="'.route('access-accounts.destroy', [
                    'accessAccount' => $account,
                    'role' => 'Viewer',
                ]).'"',
                false,
            )
            ->assertDontSee(
                'action="'.route('users.update', $account).'"',
                false,
            );
    }

    public function test_viewer_access_account_crud_preserves_the_selected_role(): void
    {
        $this->asAdministratorAtLucknow()
            ->post(route('access-accounts.store', ['role' => 'Viewer']), [
                'name' => 'New Viewer',
                'email' => 'new.viewer@example.test',
                'contact' => '9876543210',
                'address' => 'Lucknow',
                'status' => 'Active',
                'password' => 'secure-password',
            ])
            ->assertRedirect(route('access-accounts.index', ['role' => 'Viewer']))
            ->assertSessionHas('success');

        $account = User::where('email', 'new.viewer@example.test')->firstOrFail();

        $this->assertSame('Viewer', $account->role);
        $this->assertTrue($account->login_enabled);
        $this->assertSame('lucknow', $account->centre);

        $this->asAdministratorAtLucknow()
            ->put(route('access-accounts.update', [
                'accessAccount' => $account,
                'role' => 'Viewer',
            ]), [
                'name' => 'Updated Viewer',
                'email' => $account->email,
                'contact' => '9999999999',
                'address' => 'Updated Lucknow address',
                'status' => 'Inactive',
            ])
            ->assertRedirect(route('access-accounts.index', ['role' => 'Viewer']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $account->id,
            'name' => 'Updated Viewer',
            'role' => 'Viewer',
            'login_enabled' => true,
            'status' => 'Inactive',
            'centre' => 'lucknow',
        ]);

        $this->asAdministratorAtLucknow()
            ->delete(route('access-accounts.destroy', [
                'accessAccount' => $account,
                'role' => 'Viewer',
            ]))
            ->assertRedirect(route('access-accounts.index', ['role' => 'Viewer']))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $account->id]);
    }

    private function asAdministratorAtLucknow(): static
    {
        return $this->asStaticUser()->withSession(['selected_centre' => 'lucknow']);
    }

    private function viewerAccount(): User
    {
        return User::factory()->create([
            'unique_id' => 'ID-EXISTING-VIEWER',
            'name' => 'Existing Viewer',
            'email' => 'existing.viewer@example.test',
            'role' => 'Viewer',
            'login_enabled' => true,
            'status' => 'Active',
            'centre' => 'lucknow',
        ]);
    }
}
