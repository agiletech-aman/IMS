<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsCentreSplitExcel;
use App\Models\AuditLog;
use App\Services\AuditLogger;
use App\Services\CentreContextService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    use ExportsCentreSplitExcel;

    private const MODULES = [
        'Administrators',
        'Alerts',
        'Assets',
        'Authentication',
        'Brands',
        'Categories',
        'Complaint Management',
        'Departments',
        'Notification Settings',
        'Reports',
        'Roles & Permissions',
        'Settings',
        'SMTP Settings',
        'Sub Departments',
        'Types',
        'Users',
        'Vendors',
    ];

    public function index(Request $request): View
    {
        $filters = $request->validate($this->rules());
        $query = $this->filteredQuery($filters);

        return view('audit-logs.index', [
            'logs' => $query->latest()->paginate(20)->withQueryString(),
            'modules' => collect(self::MODULES)
                ->merge(AuditLog::query()->distinct()->pluck('module'))
                ->filter()
                ->unique()
                ->sort()
                ->values(),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'stats' => [
                'today' => AuditLog::whereDate('created_at', today())->count(),
                'logins' => AuditLog::whereIn('action', ['LOGIN', 'LOGIN FAILED', 'LOGOUT'])->count(),
                'changes' => AuditLog::whereIn('action', ['CREATE', 'UPDATE', 'DELETE', 'ASSIGN'])->count(),
                'failed' => AuditLog::where('result', '!=', 'Success')->count(),
            ],
        ]);
    }

    public function export(Request $request, AuditLogger $audit): StreamedResponse
    {
        $filters = $request->validate($this->rules());
        $logs = $this->filteredQuery($filters)->latest()->get();
        $audit->record(
            'EXPORT',
            'Audit Logs',
            "Audit trail exported to CSV with {$logs->count()} records.",
            metadata: ['filters' => $filters, 'record_count' => $logs->count(), 'format' => 'csv'],
        );

        return response()->streamDownload(function () use ($logs): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, [
                'Date & Time', 'User', 'Email', 'Role', 'Action', 'Module',
                'Description', 'IP Address', 'Result', 'Old Values', 'New Values',
            ]);

            foreach ($logs as $log) {
                fputcsv($output, [
                    $log->created_at->format('Y-m-d H:i:s'),
                    $log->actor_name,
                    $log->actor_email,
                    $log->actor_role,
                    $log->action,
                    $log->module,
                    $log->description,
                    $log->ip_address,
                    $log->result,
                    $log->old_values ? json_encode($log->old_values, JSON_UNESCAPED_UNICODE) : null,
                    $log->new_values ? json_encode($log->new_values, JSON_UNESCAPED_UNICODE) : null,
                ]);
            }
            fclose($output);
        }, 'audit-logs-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportXlsx(Request $request, AuditLogger $audit, CentreContextService $centreContext): BinaryFileResponse
    {
        $filters = $request->validate($this->rules());
        $logs = $this->filteredQuery($filters)->latest()->get();
        $audit->record(
            'EXPORT',
            'Audit Logs',
            "Audit trail exported to Excel with {$logs->count()} records.",
            metadata: ['filters' => $filters, 'record_count' => $logs->count(), 'format' => 'xlsx'],
        );

        $headings = [
            'Date & Time', 'User', 'Email', 'Role', 'Action', 'Module',
            'Description', 'IP Address', 'Result', 'Old Values', 'New Values',
        ];

        $spreadsheet = $this->centreSplitSpreadsheet(
            $headings,
            $logs,
            $centreContext->selected(),
            fn (AuditLog $log) => $log->centre,
            fn (AuditLog $log) => [
                $log->created_at->format('Y-m-d H:i:s'),
                $log->actor_name,
                $log->actor_email,
                $log->actor_role,
                $log->action,
                $log->module,
                $log->description,
                $log->ip_address,
                $log->result,
                $log->old_values ? json_encode($log->old_values, JSON_UNESCAPED_UNICODE) : null,
                $log->new_values ? json_encode($log->new_values, JSON_UNESCAPED_UNICODE) : null,
            ],
        );

        $filename = 'audit-logs-'.now()->format('Y-m-d-His').'.xlsx';
        $tmpPath = storage_path('app/'.$filename);

        (new Xlsx($spreadsheet))->save($tmpPath);

        return response()->download($tmpPath, $filename)->deleteFileAfterSend(true);
    }

    public function clear(AuditLogger $audit): RedirectResponse
    {
        $deletedCount = AuditLog::count();

        if ($deletedCount === 0) {
            return back()->with('info', 'Audit Log is already empty. No records were deleted.');
        }

        AuditLog::query()->delete();
        $audit->record(
            'CLEAR',
            'Audit Logs',
            "Super Admin cleared {$deletedCount} audit log records.",
            metadata: [
                'deleted_records' => $deletedCount,
                'clear_event_retained' => true,
            ],
        );

        return back()->with(
            'success',
            "{$deletedCount} audit log records were permanently cleared. A security record of this action was retained.",
        );
    }

    private function filteredQuery(array $filters): Builder
    {
        return AuditLog::query()
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $builder) use ($search): void {
                    $builder->where('actor_name', 'like', "%{$search}%")
                        ->orWhere('actor_email', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%");
                });
            })
            ->when($filters['module'] ?? null, fn (Builder $query, string $module) => $query->where('module', $module))
            ->when($filters['action'] ?? null, fn (Builder $query, string $action) => $query->where('action', $action))
            ->when($filters['result'] ?? null, fn (Builder $query, string $result) => $query->where('result', $result))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date));
    }

    private function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'module' => ['nullable', 'string', 'max:80'],
            'action' => ['nullable', 'string', 'max:40'],
            'result' => ['nullable', 'in:Success,Failed,Blocked'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }
}
