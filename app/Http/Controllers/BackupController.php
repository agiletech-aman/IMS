<?php

namespace App\Http\Controllers;

use App\Models\Backup;
use App\Models\BackupSchedule;
use App\Models\RestoreJob;
use App\Services\AuditLogger;
use App\Services\BackupHealthService;
use App\Services\BackupManager;
use App\Services\BackupStorage;
use App\Services\BackupVerifier;
use App\Services\DatabaseBackupService;
use App\Services\RestoreManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class BackupController extends Controller
{
    public function index(
        Request $request,
        BackupHealthService $healthService,
        DatabaseBackupService $database,
    ): View {
        $filters = $request->validate([
            'type' => ['nullable', Rule::in(Backup::TYPES)],
            'status' => ['nullable', Rule::in(Backup::STATUSES)],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $backups = Backup::with('schedule')
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('backup_number', 'like', '%'.$search.'%'))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $successful = Backup::whereIn('status', ['completed', 'verified']);

        return view('backup.index', [
            'backups' => $backups,
            'schedules' => BackupSchedule::latest()->get(),
            'restoreJobs' => RestoreJob::with(['backup', 'safetyBackup'])->latest()->limit(10)->get(),
            'stats' => [
                'total' => Backup::count(),
                'successful' => (clone $successful)->count(),
                'failed' => Backup::whereIn('status', ['failed', 'corrupted'])->count(),
                'storage' => (int) (clone $successful)->sum('size_bytes'),
                'last_successful' => (clone $successful)->latest('completed_at')->first(),
            ],
            'health' => $healthService->status(),
            'capabilities' => [
                'zip' => class_exists(\ZipArchive::class),
                'shell' => $database->mysqlDumpAvailable(),
                'php_export' => extension_loaded('pdo'),
            ],
        ]);
    }

    public function store(Request $request, BackupManager $manager): RedirectResponse
    {
        $data = $request->validate(['type' => ['required', Rule::in(Backup::TYPES)]]);
        $actor = $request->session()->get('static_auth_user', []);
        $backup = $manager->create($data['type'], $actor['name'] ?? null, $actor['email'] ?? null);

        return redirect()->route('backup.show', $backup)
            ->with(
                $backup->status === 'failed' ? 'error' : 'success',
                $backup->status === 'failed'
                    ? "Backup failed: {$backup->error_message}"
                    : "{$backup->backup_number} created successfully.",
            );
    }

    public function show(Backup $backup): View
    {
        return view('backup.show', [
            'backup' => $backup->load(['logs' => fn ($query) => $query->latest(), 'schedule', 'restoreJobs.safetyBackup']),
        ]);
    }

    public function logs(Backup $backup): View
    {
        return view('backup.logs', [
            'backup' => $backup,
            'logs' => $backup->logs()->latest()->paginate(30),
        ]);
    }

    public function download(Backup $backup, BackupStorage $storage, AuditLogger $audit): BinaryFileResponse
    {
        abort_unless($backup->isDownloadable(), 404, 'Backup file is not available for download.');
        $path = $storage->resolveExisting((string) $backup->file_path);
        $audit->record(
            'DOWNLOAD',
            'Backup & Recovery',
            "{$backup->backup_number} was downloaded.",
            $backup,
        );

        $response = response()->download($path, $backup->file_name, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
    }

    public function verify(Backup $backup, BackupVerifier $verifier, AuditLogger $audit): RedirectResponse
    {
        abort_unless($backup->isDownloadable(), 422, 'Only completed backups can be verified.');
        $verified = $verifier->verify($backup);
        $audit->record(
            'VERIFY',
            'Backup & Recovery',
            "{$backup->backup_number} verification ".($verified ? 'succeeded.' : 'failed.'),
            $backup,
            result: $verified ? 'Success' : 'Failed',
        );

        return back()->with(
            $verified ? 'success' : 'error',
            $verified ? 'Backup verification completed successfully.' : 'Backup verification failed. The backup was marked corrupted.',
        );
    }

    public function destroy(
        Request $request,
        Backup $backup,
        BackupStorage $storage,
        AuditLogger $audit,
    ): RedirectResponse {
        $request->validate([
            'confirmation' => ['required', Rule::in([$backup->backup_number])],
        ]);
        abort_if($backup->restoreJobs()->exists() || $backup->safetyRestoreJobs()->exists(), 422, 'A backup referenced by restore history cannot be deleted.');

        try {
            if (filled($backup->file_path)) {
                $storage->deleteIfExists($backup->file_path);
            }
            $number = $backup->backup_number;
            $audit->record('DELETE', 'Backup & Recovery', "{$number} and its private file were deleted.", $backup);
            $backup->delete();

            return redirect()->route('backup.index')->with('success', "{$number} deleted permanently.");
        } catch (Throwable $exception) {
            return back()->with('error', 'Backup could not be deleted: '.mb_strimwidth($exception->getMessage(), 0, 300));
        }
    }

    public function restore(
        Request $request,
        Backup $backup,
        RestoreManager $restore,
    ): RedirectResponse {
        $request->validate([
            'confirmation' => ['required', Rule::in([$backup->backup_number])],
        ], [
            'confirmation.in' => 'Enter the backup number exactly to confirm this restore.',
        ]);
        $actor = $request->session()->get('static_auth_user', []);
        $job = $restore->restore($backup, $actor['name'] ?? 'Administrator', $actor['email'] ?? null);

        return redirect()->route('backup.show', $backup)->with(
            $job->status === 'completed' ? 'success' : 'error',
            $job->status === 'completed'
                ? "Restore completed. Safety backup {$job->safetyBackup?->backup_number} was created first."
                : "Restore failed: {$job->error_message}",
        );
    }
}
