<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AccessAccountController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AssetImportExportController;
use App\Http\Controllers\AssetMasterController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BackupScheduleController;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserImportExportController;
use App\Http\Controllers\VendorController;
use App\Http\Middleware\EnsureAdministrator;
use App\Http\Middleware\EnsureCentreSelected;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureStaticAuthenticated;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware(EnsureStaticAuthenticated::class)->group(function () {

    Route::get('/centre/switch/{centre}', function ($centre) {

        abort_unless(filled(session('static_auth_user.admin_id')), 403);

        abort_unless(
            in_array($centre, ['all', 'noida', 'lucknow'], true),
            404
        );

        app(\App\Services\CentreContextService::class)->set($centre);

        return redirect()->back();

    })->name('centre.switch');

});
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
Route::view('/forgot-password', 'auth.forgot-password')->name('password.request');
Route::view('/reset-password', 'auth.reset-password')->name('password.reset');

Route::middleware([EnsureStaticAuthenticated::class, EnsureCentreSelected::class])->group(function () {
    $permission = fn (string $module, string $action = 'view') => EnsurePermission::class.":{$module},{$action}";

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/media/{path}', [MediaController::class, 'show'])
        ->where('path', '.*')
        ->name('media.show');
    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware($permission('dashboard'))->name('dashboard');
    Route::get('/global-search', [GlobalSearchController::class, 'index'])->name('global-search.index');
    Route::get('assets/export', [AssetImportExportController::class, 'exportCsv'])->middleware($permission('assets', 'export'))->name('assets.export');
    Route::get('assets/import-sample', [AssetImportExportController::class, 'importSampleCsv'])->middleware($permission('assets', 'import'))->name('assets.import-sample');
    Route::post('assets/import', [AssetImportExportController::class, 'importCsv'])->middleware($permission('assets', 'import'))->name('assets.import');
    Route::get('assets', [AssetController::class, 'index'])->middleware($permission('assets'))->name('assets.index');
    Route::get('assets/create', [AssetController::class, 'create'])->middleware($permission('assets', 'create'))->name('assets.create');
    Route::post('assets', [AssetController::class, 'store'])->middleware($permission('assets', 'create'))->name('assets.store');
    Route::get('assets/{asset}', [AssetController::class, 'show'])->middleware($permission('assets'))->name('assets.show');
    Route::get('assets/{asset}/edit', [AssetController::class, 'edit'])->middleware($permission('assets', 'update'))->name('assets.edit');
    Route::match(['put', 'patch'], 'assets/{asset}', [AssetController::class, 'update'])->middleware($permission('assets', 'update'))->name('assets.update');
    Route::delete('assets/{asset}', [AssetController::class, 'destroy'])->middleware($permission('assets', 'delete'))->name('assets.destroy');
   Route::prefix('asset-management')
    ->name('asset-management.')
    ->group(function () {

        $permission = fn (string $module, string $action = 'view') =>
            EnsurePermission::class . ":{$module},{$action}";

        foreach ([
            'departments' => 'departments',
            'sub-departments' => 'sub_departments',
            'types' => 'types',
            'brands' => 'brands',
        ] as $path => $module) {

            // List
            Route::get(
                $path,
                [AssetMasterController::class, 'index']
            )
                ->middleware($permission($module))
                ->name($path . '.index');


            // Export
            Route::get(
                "{$path}/export",
                [AssetMasterController::class, 'export']
            )
                ->middleware($permission($module, 'export'))
                ->name($path . '.export');


            // Import
            Route::post(
                "{$path}/import",
                [AssetMasterController::class, 'import']
            )
                ->middleware($permission($module, 'import'))
                ->name($path . '.import');


            // Create
            Route::post(
                $path,
                [AssetMasterController::class, 'store']
            )
                ->middleware($permission($module, 'create'))
                ->name($path . '.store');


            // Update
            Route::match(
                ['put', 'patch'],
                "{$path}/{record}",
                [AssetMasterController::class, 'update']
            )
                ->middleware($permission($module, 'update'))
                ->name($path . '.update');


            // Delete
            Route::delete(
                "{$path}/{record}",
                [AssetMasterController::class, 'destroy']
            )
                ->middleware($permission($module, 'delete'))
                ->name($path . '.destroy');
        }
    });
    Route::middleware(EnsureAdministrator::class)->group(function () {
        Route::view('/inventory', 'inventory.index')->name('inventory.index');
        Route::view('/software', 'software.index')->name('software.index');
        Route::view('/amc-warranty', 'amc-warranty.index')->name('amc-warranty.index');
        Route::view('/tickets', 'tickets.index')->name('tickets.index');
        Route::view('/tickets/create', 'tickets.create')->name('tickets.create');
        Route::view('/tickets/{id}', 'tickets.show')->name('tickets.show');
        Route::view('/servers', 'servers.index')->name('servers.index');
        Route::view('/network', 'network.index')->name('network.index');
        Route::view('/ups', 'ups.index')->name('ups.index');
        Route::view('/cctv', 'cctv.index')->name('cctv.index');
        Route::view('/video-conference', 'video-conference.index')->name('video-conference.index');
    });
    Route::get('/notifications', [NotificationController::class, 'index'])->middleware($permission('notifications'))->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->middleware($permission('notifications', 'update'))->name('notifications.read-all');
    Route::post('/notifications/preferences', [NotificationController::class, 'updatePreferences'])
        ->middleware([$permission('settings'), $permission('settings_advanced', 'update')])
        ->name('notifications.preferences');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->middleware($permission('notifications', 'update'))->name('notifications.read');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->middleware($permission('notifications', 'delete'))->name('notifications.destroy');
    Route::get('/users/{user}/asset-history', [UserController::class, 'assetHistory'])->middleware($permission('faculty'))->name('users.asset-history');
    Route::get('/users/{user}/asset-history/export', [UserController::class, 'exportAssetHistory'])->middleware($permission('faculty', 'export'))->name('users.asset-history.export');
    Route::post('/users/{user}/assign-asset', [UserController::class, 'assignAsset'])->middleware($permission('faculty', 'assign'))->name('users.assign-asset');
    Route::get('users/export', [UserImportExportController::class, 'exportCsv'])->middleware($permission('faculty', 'import'))->name('users.export');
    Route::get('users/import-sample', [UserImportExportController::class, 'importSampleCsv'])->middleware($permission('faculty', 'import'))->name('users.import-sample');
    Route::post('users/import', [UserImportExportController::class, 'importCsv'])->middleware($permission('faculty', 'import'))->name('users.import');
    Route::get('/users', [UserController::class, 'index'])->middleware($permission('faculty'))->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->middleware($permission('faculty', 'create'))->name('users.store');
    Route::match(['put', 'patch'], '/users/{user}', [UserController::class, 'update'])->middleware($permission('faculty', 'update'))->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware($permission('faculty', 'delete'))->name('users.destroy');

    Route::get('/access-accounts', [AccessAccountController::class, 'index'])->name('access-accounts.index');
    Route::post('/access-accounts', [AccessAccountController::class, 'store'])->name('access-accounts.store');
    Route::match(['put', 'patch'], '/access-accounts/{accessAccount}', [AccessAccountController::class, 'update'])->name('access-accounts.update');
    Route::delete('/access-accounts/{accessAccount}', [AccessAccountController::class, 'destroy'])->name('access-accounts.destroy');
    Route::get('/roles-permissions', [RolePermissionController::class, 'index'])->middleware($permission('roles_permissions'))->name('roles-permissions.index');
    Route::post('/roles-permissions', [RolePermissionController::class, 'update'])->middleware($permission('roles_permissions', 'update'))->name('roles-permissions.update');
    Route::get('/vendors', [VendorController::class, 'index'])->middleware($permission('vendors'))->name('vendors.index');
    Route::post('/vendors', [VendorController::class, 'store'])->middleware($permission('vendors', 'create'))->name('vendors.store');
    Route::match(['put', 'patch'], '/vendors/{vendor}', [VendorController::class, 'update'])->middleware($permission('vendors', 'update'))->name('vendors.update');
    Route::delete('/vendors/{vendor}', [VendorController::class, 'destroy'])->middleware($permission('vendors', 'delete'))->name('vendors.destroy');
    Route::get('/complaints', [ComplaintController::class, 'index'])->middleware($permission('complaints'))->name('complaints.index');
    Route::get('/complaints/export', [ComplaintController::class, 'export'])->middleware($permission('complaints', 'export'))->name('complaints.export');
    Route::get('/complaints/create', [ComplaintController::class, 'create'])->middleware($permission('complaints', 'create'))->name('complaints.create');
    Route::post('/complaints', [ComplaintController::class, 'store'])->middleware($permission('complaints', 'create'))->name('complaints.store');
    Route::get('/complaints/{complaint}', [ComplaintController::class, 'show'])->middleware($permission('complaints'))->name('complaints.show');
    Route::patch('/complaints/{complaint}/assign', [ComplaintController::class, 'assign'])->middleware($permission('complaints', 'assign'))->name('complaints.assign');
    Route::patch('/complaints/{complaint}/status', [ComplaintController::class, 'updateStatus'])->middleware($permission('complaints', 'update'))->name('complaints.status');
    Route::delete('/complaints/{complaint}', [ComplaintController::class, 'destroy'])->middleware($permission('complaints', 'delete'))->name('complaints.destroy');
    Route::get('/reports', [ReportController::class, 'index'])->middleware($permission('reports'))->name('reports.index');
    Route::post('/reports/generate', [ReportController::class, 'generate'])->middleware($permission('reports', 'create'))->name('reports.generate');
    Route::get('/reports/export', [ReportController::class, 'export'])->middleware($permission('reports', 'export'))->name('reports.export');
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->middleware($permission('audit_logs'))->name('audit-logs.index');
    Route::get('/audit-logs/export', [AuditLogController::class, 'export'])->middleware($permission('audit_logs', 'export'))->name('audit-logs.export');
    Route::delete('/audit-logs', [AuditLogController::class, 'clear'])->middleware(EnsureAdministrator::class)->name('audit-logs.clear');
    Route::middleware([EnsureAdministrator::class, $permission('backup')])->prefix('backup')->name('backup.')->group(function () use ($permission) {
        Route::get('/', [BackupController::class, 'index'])->name('index');
        Route::post('/', [BackupController::class, 'store'])->middleware($permission('backup', 'create'))->name('store');
        Route::post('/schedules', [BackupScheduleController::class, 'store'])->middleware($permission('backup', 'update'))->name('schedules.store');
        Route::put('/schedules/{schedule}', [BackupScheduleController::class, 'update'])->middleware($permission('backup', 'update'))->name('schedules.update');
        Route::delete('/schedules/{schedule}', [BackupScheduleController::class, 'destroy'])->middleware($permission('backup', 'update'))->name('schedules.destroy');
        Route::get('/{backup}', [BackupController::class, 'show'])->name('show');
        Route::get('/{backup}/logs', [BackupController::class, 'logs'])->name('logs');
        Route::get('/{backup}/download', [BackupController::class, 'download'])->middleware($permission('backup', 'download'))->name('download');
        Route::post('/{backup}/verify', [BackupController::class, 'verify'])->middleware($permission('backup', 'verify'))->name('verify');
        Route::post('/{backup}/restore', [BackupController::class, 'restore'])->middleware($permission('backup', 'restore'))->name('restore');
        Route::delete('/{backup}', [BackupController::class, 'destroy'])->middleware($permission('backup', 'delete'))->name('destroy');
    });
    Route::get('/settings', [SettingsController::class, 'index'])->middleware($permission('settings'))->name('settings.index');
    Route::post('/settings/general', [SettingsController::class, 'updateGeneral'])->middleware([$permission('settings'), $permission('settings_basic', 'update')])->name('settings.general.update');
    Route::post('/settings/company', [SettingsController::class, 'updateCompany'])->middleware([$permission('settings'), $permission('settings_basic', 'update')])->name('settings.company.update');
    Route::post('/settings/security', [SettingsController::class, 'updateSecurity'])->middleware([$permission('settings'), $permission('settings_advanced', 'update')])->name('settings.security.update');
    Route::post('/settings/theme', [SettingsController::class, 'updateTheme'])->middleware([$permission('settings'), $permission('settings_basic', 'update')])->name('settings.theme.update');
    Route::post('/settings/smtp', [SettingsController::class, 'updateSmtp'])->middleware([$permission('settings'), $permission('settings_advanced', 'update')])->name('settings.smtp.update');
    Route::post('/settings/smtp/test', [SettingsController::class, 'testSmtp'])->middleware([$permission('settings'), $permission('settings_advanced', 'update')])->name('settings.smtp.test');
    Route::middleware([EnsureAdministrator::class, $permission('administrators')])->group(function () use ($permission) {
        Route::post('/settings/administrators', [AdminController::class, 'store'])->middleware($permission('administrators', 'create'))->name('settings.admins.store');
        Route::put('/settings/administrators/{admin}', [AdminController::class, 'update'])->middleware($permission('administrators', 'update'))->name('settings.admins.update');
        Route::delete('/settings/administrators/{admin}', [AdminController::class, 'destroy'])->middleware($permission('administrators', 'delete'))->name('settings.admins.destroy');
    });
});

