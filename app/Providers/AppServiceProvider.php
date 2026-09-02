<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetType;
use App\Models\Brand;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\NotificationPreference;
use App\Models\SystemNotification;
use App\Models\SmtpSetting;
use App\Models\SubDepartment;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Vendor;
use App\Observers\AuditObserver;
use App\Services\DynamicMailConfig;
use App\Services\PermissionService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PermissionService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blade::if('permission', fn (string $module, string $action = 'view') => app(PermissionService::class)->allows($module, $action));

        foreach ([
            Admin::class,
            Asset::class,
            AssetCategory::class,
            AssetType::class,
            Brand::class,
            Department::class,
            Faculty::class,
            SubDepartment::class,
            User::class,
            Vendor::class,
            SystemNotification::class,
            NotificationPreference::class,
            SmtpSetting::class,
            SystemSetting::class,
        ] as $model) {
            $model::observe(AuditObserver::class);
        }

        View::share('systemSettings', null);

        if (Schema::hasTable('system_settings')) {
            $systemSettings = SystemSetting::current();
            View::share('systemSettings', $systemSettings);
            config([
                'app.name' => $systemSettings->application_name,
                'app.locale' => $systemSettings->language,
                'app.timezone' => $systemSettings->timezone,
                'session.lifetime' => $systemSettings->session_timeout,
            ]);
            date_default_timezone_set($systemSettings->timezone);
        }

        if (Schema::hasTable('smtp_settings')) {
            $smtp = SmtpSetting::first();
            if ($smtp?->enabled) {
                app(DynamicMailConfig::class)->apply($smtp);
            }
        }

        View::composer('partials.header', function ($view): void {
            $notifications = collect();
            $unreadCount = 0;

            if (Schema::hasTable('system_notifications')) {
                $notifications = SystemNotification::where('in_app_visible', true)->latest()->limit(5)->get();
                $unreadCount = SystemNotification::where('in_app_visible', true)->whereNull('read_at')->count();
            }

            $view->with([
                'headerNotifications' => $notifications,
                'headerUnreadCount' => $unreadCount,
            ]);
        });
    }
}
