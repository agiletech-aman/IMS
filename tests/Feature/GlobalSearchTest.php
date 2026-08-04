<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Complaint;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asStaticUser();
    }

    public function test_it_returns_matching_assets_tickets_and_users(): void
    {
        $this->seed(DatabaseSeeder::class);
        $asset = Asset::firstOrFail();
        $asset->update(['name' => 'Orion Workstation']);
        $user = User::factory()->create([
            'name' => 'Orion User',
            'email' => 'orion@example.com',
            'unique_id' => 'USR-ORION',
        ]);
        $ticket = Complaint::create([
            'complaint_number' => 'CMP-ORION',
            'subject' => 'Orion VPN access',
            'description' => 'VPN access is unavailable.',
            'requester_name' => 'Orion User',
            'category' => 'Hardware',
            'priority' => 'High',
            'status' => 'Complaint Raised',
        ]);

        $this->getJson(route('global-search.index', ['query' => 'Orion']))
            ->assertOk()
            ->assertJsonCount(3, 'results')
            ->assertJsonFragment([
                'type' => 'Assets',
                'title' => "{$asset->asset_tag} · Orion Workstation",
                'url' => route('assets.show', $asset),
            ])
            ->assertJsonFragment([
                'type' => 'Tickets',
                'title' => 'CMP-ORION · Orion VPN access',
                'url' => route('complaints.show', $ticket),
            ])
            ->assertJsonFragment([
                'type' => 'Users',
                'title' => 'Orion User',
                'url' => route('users.index', ['search' => $user->unique_id]),
            ]);
    }

    public function test_it_requires_two_characters_before_searching(): void
    {
        $this->getJson(route('global-search.index', ['query' => 'A']))
            ->assertOk()
            ->assertExactJson(['results' => []]);
    }
}
