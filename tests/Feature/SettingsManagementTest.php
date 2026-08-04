<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asStaticUser();
    }

    public function test_general_and_company_settings_are_persisted(): void
    {
        $this->post(route('settings.general.update'), [
            'application_name' => 'Agile IIM Production',
            'language' => 'hi',
            'timezone' => 'Asia/Kolkata',
            'date_format' => 'd/m/Y',
            'currency' => 'INR',
        ])->assertRedirect()->assertSessionHas('success');

        $this->post(route('settings.company.update'), [
            'company_name' => 'Agile Tech Solutions Pvt Ltd',
            'tax_number' => 'GST-12345',
            'registered_address' => 'Noida, Uttar Pradesh',
            'support_email' => 'support@example.com',
            'support_phone' => '9876543210',
        ])->assertRedirect()->assertSessionHas('success');

        $settings = SystemSetting::current();
        $this->assertSame('Agile IIM Production', $settings->application_name);
        $this->assertSame('hi', $settings->language);
        $this->assertSame('Agile Tech Solutions Pvt Ltd', $settings->company_name);
        $this->assertSame('GST-12345', $settings->tax_number);
    }

    public function test_security_and_theme_settings_are_persisted(): void
    {
        $this->post(route('settings.security.update'), [
            'session_timeout' => '60',
            'password_expiry_days' => '180',
            'require_mfa' => '1',
            'strong_password' => '1',
        ])->assertRedirect()->assertSessionHas('success');

        $this->post(route('settings.theme.update'), [
            'default_theme' => 'dark',
        ])->assertRedirect()->assertSessionHas('success');

        $settings = SystemSetting::current();
        $this->assertSame(60, $settings->session_timeout);
        $this->assertSame(180, $settings->password_expiry_days);
        $this->assertTrue($settings->require_mfa);
        $this->assertTrue($settings->strong_password);
        $this->assertFalse($settings->restrict_concurrent_sessions);
        $this->assertSame('dark', $settings->default_theme);
    }

    public function test_invalid_settings_are_rejected(): void
    {
        $this->post(route('settings.general.update'), [
            'application_name' => '',
            'language' => 'invalid',
            'timezone' => 'Mars/Olympus',
            'date_format' => 'invalid',
            'currency' => 'BTC',
        ])->assertSessionHasErrors(['application_name', 'language', 'timezone', 'date_format', 'currency']);

        $this->post(route('settings.theme.update'), [
            'default_theme' => 'neon',
        ])->assertSessionHasErrors('default_theme');
    }
}
