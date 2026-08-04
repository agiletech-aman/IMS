<?php

namespace App\Http\Controllers;

use App\Mail\SystemAlertMail;
use App\Models\Admin;
use App\Models\NotificationPreference;
use App\Models\SmtpSetting;
use App\Models\SystemSetting;
use App\Services\DynamicMailConfig;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class SettingsController extends Controller
{
    public function index(): View
    {
        NotificationPreference::seedDefaults();
        $canManageAdmins = session('static_auth_user.role') === 'Administrator';

        return view('settings.index', [
            'settings' => SystemSetting::current(),
            'smtp' => SmtpSetting::current(),
            'preferences' => NotificationPreference::orderBy('id')->get(),
            'canManageAdmins' => $canManageAdmins,
            'admins' => $canManageAdmins ? Admin::latest()->get() : collect(),
        ]);
    }

    public function updateGeneral(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'application_name' => ['required', 'string', 'max:255'],
            'language' => ['required', Rule::in(['en', 'hi'])],
            'timezone' => ['required', 'timezone:all'],
            'date_format' => ['required', Rule::in(['d M Y', 'd/m/Y', 'Y-m-d'])],
            'currency' => ['required', Rule::in(['INR', 'USD', 'EUR'])],
        ]);
        SystemSetting::current()->update($data);

        return back()->with('success', 'General settings updated successfully.')->with('activeSettingsTab', 'general');
    }

    public function updateCompany(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'registered_address' => ['nullable', 'string', 'max:2000'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:30'],
        ]);
        SystemSetting::current()->update($data);

        return back()->with('success', 'Company information updated successfully.')->with('activeSettingsTab', 'company');
    }

    public function updateSecurity(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'session_timeout' => ['required', 'integer', Rule::in([15, 30, 60, 120])],
            'password_expiry_days' => ['nullable', 'integer', Rule::in([30, 60, 90, 180])],
            'require_mfa' => ['nullable', 'boolean'],
            'strong_password' => ['nullable', 'boolean'],
            'restrict_concurrent_sessions' => ['nullable', 'boolean'],
        ]);
        $data['require_mfa'] = $request->boolean('require_mfa');
        $data['strong_password'] = $request->boolean('strong_password');
        $data['restrict_concurrent_sessions'] = $request->boolean('restrict_concurrent_sessions');
        SystemSetting::current()->update($data);

        return back()->with('success', 'Security policy updated successfully.')->with('activeSettingsTab', 'security');
    }

    public function updateTheme(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'default_theme' => ['required', Rule::in(['light', 'dark', 'system'])],
        ]);
        SystemSetting::current()->update($data);

        return back()->with('success', 'Default theme updated successfully.')->with('activeSettingsTab', 'theme');
    }

    public function updateSmtp(Request $request): RedirectResponse
    {
        $smtp = SmtpSetting::current();
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'host' => ['required_if:enabled,1', 'nullable', 'string', 'max:255'],
            'port' => ['required_if:enabled,1', 'nullable', 'integer', 'between:1,65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:1000'],
            'encryption' => ['nullable', Rule::in(['tls', 'ssl', 'none'])],
            'from_name' => ['required_if:enabled,1', 'nullable', 'string', 'max:255'],
            'from_address' => ['required_if:enabled,1', 'nullable', 'email', 'max:255'],
            'notification_emails' => ['required_if:enabled,1', 'nullable', 'string', 'max:2000'],
        ]);

        $data['enabled'] = $request->boolean('enabled');
        $data['encryption'] = ($data['encryption'] ?? null) === 'none' ? null : ($data['encryption'] ?? null);
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        $smtp->update($data);

        return back()->with('success', 'SMTP settings saved. Database settings now override mail environment defaults.')->with('activeSettingsTab', 'smtp');
    }

    public function testSmtp(DynamicMailConfig $mailConfig, AuditLogger $audit): RedirectResponse
    {
        $smtp = SmtpSetting::current();
        if (! $smtp->enabled || ! $smtp->host || $smtp->recipients() === []) {
            $audit->record('TEST', 'SMTP Settings', 'SMTP test could not start because configuration is incomplete.', result: 'Failed');

            return back()->with('error', 'Enable SMTP and provide a host and notification recipient first.')->with('activeSettingsTab', 'smtp');
        }

        try {
            $mailConfig->apply($smtp);
            Mail::to($smtp->recipients())->send(new SystemAlertMail(
                'SMTP test successful',
                'This test confirms that dynamic SMTP settings are working.',
                'success',
                'Settings',
            ));
            $audit->record('TEST', 'SMTP Settings', 'SMTP test email was sent successfully.');

            return back()->with('success', 'Test email sent successfully.')->with('activeSettingsTab', 'smtp');
        } catch (Throwable $exception) {
            report($exception);
            $audit->record(
                'TEST',
                'SMTP Settings',
                'SMTP test email failed.',
                result: 'Failed',
                metadata: ['error' => mb_strimwidth($exception->getMessage(), 0, 300)],
            );

            return back()->with('error', 'SMTP test failed: '.mb_strimwidth($exception->getMessage(), 0, 300))->with('activeSettingsTab', 'smtp');
        }
    }
}
