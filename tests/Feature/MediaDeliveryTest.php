<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Mockery;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class MediaDeliveryTest extends TestCase
{
    public function test_authenticated_profile_image_is_served_through_the_application(): void
    {
        $disk = Mockery::mock();
        $disk->shouldReceive('exists')
            ->once()
            ->with('admins/profile.png')
            ->andReturnTrue();
        $disk->shouldReceive('response')
            ->once()
            ->with('admins/profile.png', null, Mockery::on(
                fn (array $headers) => $headers['X-Content-Type-Options'] === 'nosniff',
            ))
            ->andReturn(new StreamedResponse(fn () => print 'image-content', 200, [
                'Content-Type' => 'image/png',
            ]));
        Storage::shouldReceive('disk')->once()->with('public')->andReturn($disk);

        $this->asStaticUser()
            ->get('/media/admins/profile.png')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_media_route_requires_login(): void
    {
        $this->get('/media/admins/profile.png')
            ->assertRedirect(route('login'));
    }
}
