<?php

namespace Tests\Feature;

use App\Models\Asset;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public static function uiRoutes(): array
    {
        return array_map(
            static fn (string $path): array => [$path],
            [
                '/', '/login', '/forgot-password', '/reset-password',
                '/dashboard', '/assets', '/assets/create',
                '/asset-management/departments', '/asset-management/sub-departments',
                '/asset-management/types', '/asset-management/brands', '/asset-management/categories',
                '/inventory', '/software', '/amc-warranty', '/tickets', '/tickets/create',
                '/tickets/1', '/notifications', '/users', '/roles-permissions', '/vendors', '/servers', '/network', '/ups', '/cctv',
                '/video-conference', '/reports', '/audit-logs', '/backup', '/settings',
            ],
        );
    }

    #[DataProvider('uiRoutes')]
    public function test_static_ui_routes_render_successfully(string $path): void
    {
        if (! in_array($path, ['/', '/login', '/forgot-password', '/reset-password'], true)) {
            $this->asStaticUser();
        }

        $response = $this->get($path);

        if ($path === '/') {
            $response->assertRedirect('/dashboard');

            return;
        }

        $response->assertOk();
    }

    public function test_asset_management_is_database_backed(): void
    {
        $this->asStaticUser();
        $this->seed(DatabaseSeeder::class);
        $asset = Asset::firstOrFail();

        $this->get(route('assets.show', $asset))->assertOk()->assertSee('Installation Date');
        $this->get(route('assets.edit', $asset))->assertOk()->assertDontSee('Purchase Cost');
        $this->get(route('assets.create'))->assertOk()->assertSee('<option value="">Unassigned</option>', false);
        $this->get(route('asset-management.brands.index'))->assertOk()->assertSee('Brand Logo');
    }
}
