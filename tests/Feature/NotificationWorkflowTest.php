<?php

namespace Tests\Feature;

use App\Mail\SystemAlertMail;
use App\Models\Asset;
use App\Models\NotificationPreference;
use App\Models\SmtpSetting;
use App\Models\SystemNotification;
use App\Services\NotificationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asStaticUser();
        NotificationPreference::seedDefaults();
    }

    public function test_smtp_settings_are_dynamic_and_password_is_encrypted(): void
    {
        $this->post(route('settings.smtp.update'), [
            'enabled' => '1',
            'host' => 'smtp.example.com',
            'port' => '587',
            'username' => 'mailer@example.com',
            'password' => 'super-secret-password',
            'encryption' => 'tls',
            'from_name' => 'Agile IIM',
            'from_address' => 'mailer@example.com',
            'notification_emails' => 'admin@example.com, assets@example.com',
        ])->assertRedirect()->assertSessionHas('success');

        $smtp = SmtpSetting::current();
        $this->assertTrue($smtp->enabled);
        $this->assertSame('super-secret-password', $smtp->password);
        $this->assertNotSame('super-secret-password', DB::table('smtp_settings')->value('password'));
        $this->assertSame(['admin@example.com', 'assets@example.com'], $smtp->recipients());
    }

    public function test_notification_service_delivers_in_app_and_email(): void
    {
        Mail::fake();
        SmtpSetting::current()->update([
            'enabled' => true,
            'host' => 'smtp.example.com',
            'port' => 587,
            'from_name' => 'Agile IIM',
            'from_address' => 'alerts@example.com',
            'notification_emails' => 'admin@example.com',
        ]);

        $notification = app(NotificationService::class)->send(
            'user_created',
            'New user created',
            'Test User was added as Viewer.',
            'info',
            'Users',
        );

        $this->assertTrue($notification->in_app_visible);
        $this->assertNotNull($notification->fresh()->emailed_at);
        Mail::assertSent(SystemAlertMail::class, fn (SystemAlertMail $mail) => $mail->hasTo('admin@example.com'));
    }

    public function test_expiry_report_and_user_events_create_deduplicated_alerts(): void
    {
        $this->seed(DatabaseSeeder::class);
        $asset = Asset::firstOrFail();
        $asset->update([
            'warranty_expiry' => today()->addDays(5),
            'amc_expiry' => today()->addDays(10),
        ]);

        $this->artisan('notifications:check-expiries')->assertSuccessful();
        $this->artisan('notifications:check-expiries')->assertSuccessful();

        $this->assertSame(1, SystemNotification::where('event_type', 'warranty_expiry')->count());
        $this->assertSame(1, SystemNotification::where('event_type', 'amc_expiry')->count());

        $this->post(route('reports.generate'), [
            'report_type' => 'Asset Register',
            'from_date' => '2026-01-01',
            'to_date' => '2026-12-31',
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('system_notifications', ['event_type' => 'report_generated']);

        $this->post(route('users.store'), [
            'name' => 'Notification User',
            'email' => 'notification-user@example.com',
            'role' => 'Viewer',
            'status' => 'Active',
        ])->assertRedirect();
        $this->assertDatabaseHas('system_notifications', ['event_type' => 'user_created']);
    }

    public function test_in_app_notifications_and_channel_preferences_are_manageable(): void
    {
        $service = app(NotificationService::class);
        $first = $service->send('vendor_changed', 'Vendor created', 'A vendor was created.', 'info', 'Vendors');
        $second = $service->send('user_deleted', 'User deleted', 'A user was deleted.', 'warning', 'Users');

        $this->patch(route('notifications.read', $first))->assertRedirect();
        $this->assertNotNull($first->fresh()->read_at);

        $this->post(route('notifications.read-all'))->assertRedirect();
        $this->assertNotNull($second->fresh()->read_at);

        $this->post(route('notifications.preferences'), [
            'preferences' => [
                'amc_expiry' => ['email' => '1', 'days_before' => '45'],
            ],
        ])->assertRedirect()->assertSessionHas('success');

        $amc = NotificationPreference::where('event_type', 'amc_expiry')->firstOrFail();
        $this->assertFalse($amc->in_app_enabled);
        $this->assertTrue($amc->email_enabled);
        $this->assertSame(45, $amc->days_before);

        $this->delete(route('notifications.destroy', $first))->assertRedirect();
        $this->assertDatabaseMissing('system_notifications', ['id' => $first->id]);
    }
}
