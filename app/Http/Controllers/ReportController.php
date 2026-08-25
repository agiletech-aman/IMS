<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsCentreSplitExcel;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Brand;
use App\Models\Department;
use App\Models\SubDepartment;
use App\Services\CentreContextService;
use App\Services\NotificationService;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    use ExportsCentreSplitExcel;

    public function index(Request $request): View
    {
        $hasGenerated = $request->boolean('generated');
        $filters = $hasGenerated ? $request->validate($this->rules($request)) : [];
        $query = $hasGenerated ? $this->reportQuery($filters) : Asset::query()->whereRaw('1 = 0');

        return view('reports.index', [
            'assets' => (clone $query)->latest()->paginate(15)->withQueryString(),
            'summary' => [
                'total' => (clone $query)->count(),
                'assigned' => (clone $query)->whereNotNull('assigned_to')->count(),
                'warranty_due' => (clone $query)->whereBetween('warranty_expiry', [today(), today()->addDays(30)])->count(),
                'amc_due' => (clone $query)->whereBetween('amc_expiry', [today(), today()->addDays(30)])->count(),
            ],
            'hasGenerated' => $hasGenerated,
            'types' => AssetType::where('status', 'Active')->orderBy('name')->get(),
            'brands' => Brand::where('status', 'Active')->orderBy('name')->get(),
            'departments' => Department::where('status', 'Active')->orderBy('name')->get(),
            'subDepartments' => SubDepartment::where('status', 'Active')->orderBy('name')->get(),
            'assignees' => Asset::whereNotNull('assigned_to')->where('assigned_to', '!=', '')->distinct()->orderBy('assigned_to')->pluck('assigned_to'),
        ]);
    }

    public function generate(
        Request $request,
        NotificationService $notifications,
        AuditLogger $audit,
    ): RedirectResponse
    {
        $filters = $request->validate($this->rules($request));
        $count = $this->reportQuery($filters)->count();
        $activeFilters = collect($filters)->filter(fn ($value) => filled($value))->count();

        $notifications->send(
            'report_generated',
            'Asset report generated',
            "Asset report generated with {$activeFilters} active filters and {$count} matching records.",
            'info',
            'Reports',
            ['filters' => $filters, 'record_count' => $count],
        );
        $audit->record(
            'GENERATE',
            'Reports',
            "Asset report generated with {$activeFilters} active filters and {$count} matching records.",
            metadata: ['filters' => $filters, 'record_count' => $count],
        );

        return redirect()->route('reports.index', [...$filters, 'generated' => 1])
            ->with('success', "Report generated with {$count} matching records.");
    }

    public function export(
        Request $request,
        NotificationService $notifications,
        AuditLogger $audit,
    ): StreamedResponse
    {
        $filters = $request->validate($this->rules($request));
        $assets = $this->reportQuery($filters)->orderBy('asset_tag')->get();

        $notifications->send(
            'report_generated',
            'Asset report exported',
            "Filtered asset report was exported to CSV with {$assets->count()} records.",
            'info',
            'Reports',
            ['filters' => $filters, 'record_count' => $assets->count(), 'format' => 'csv'],
        );
        $audit->record(
            'EXPORT',
            'Reports',
            "Asset report exported to CSV with {$assets->count()} records.",
            metadata: ['filters' => $filters, 'record_count' => $assets->count(), 'format' => 'csv'],
        );

        return response()->streamDownload(function () use ($assets): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, [
                'Asset ID', 'Asset Name', 'Type', 'Brand', 'Department',
                'Sub Department', 'Assigned To', 'Status', 'Warranty Expiry', 'AMC Expiry',
            ]);

            foreach ($assets as $asset) {
                fputcsv($output, [
                    $asset->asset_tag,
                    $asset->name,
                    $asset->type?->name,
                    $asset->brand?->name,
                    $asset->department?->name,
                    $asset->subDepartment?->name,
                    $asset->assigned_to,
                    $asset->status,
                    $asset->warranty_expiry?->format('Y-m-d'),
                    $asset->amc_expiry?->format('Y-m-d'),
                ]);
            }
            fclose($output);
        }, 'asset-report-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportXlsx(
        Request $request,
        NotificationService $notifications,
        AuditLogger $audit,
        CentreContextService $centreContext,
    ): BinaryFileResponse
    {
        $filters = $request->validate($this->rules($request));
        $assets = $this->reportQuery($filters)->orderBy('asset_tag')->get();

        $notifications->send(
            'report_generated',
            'Asset report exported',
            "Filtered asset report was exported to Excel with {$assets->count()} records.",
            'info',
            'Reports',
            ['filters' => $filters, 'record_count' => $assets->count(), 'format' => 'xlsx'],
        );
        $audit->record(
            'EXPORT',
            'Reports',
            "Asset report exported to Excel with {$assets->count()} records.",
            metadata: ['filters' => $filters, 'record_count' => $assets->count(), 'format' => 'xlsx'],
        );

        $headings = [
            'Asset ID', 'Asset Name', 'Type', 'Brand', 'Department',
            'Sub Department', 'Assigned To', 'Status', 'Warranty Expiry', 'AMC Expiry',
        ];

        $spreadsheet = $this->centreSplitSpreadsheet(
            $headings,
            $assets,
            $centreContext->selected(),
            fn (Asset $asset) => $asset->centre,
            fn (Asset $asset) => [
                $asset->asset_tag,
                $asset->name,
                $asset->type?->name,
                $asset->brand?->name,
                $asset->department?->name,
                $asset->subDepartment?->name,
                $asset->assigned_to,
                $asset->status,
                $asset->warranty_expiry?->format('Y-m-d'),
                $asset->amc_expiry?->format('Y-m-d'),
            ],
        );

        $filename = 'asset-report-'.now()->format('Y-m-d-His').'.xlsx';
        $tmpPath = storage_path('app/'.$filename);

        (new Xlsx($spreadsheet))->save($tmpPath);

        return response()->download($tmpPath, $filename)->deleteFileAfterSend(true);
    }

    private function reportQuery(array $filters): Builder
    {
$query = Asset::with(['type', 'brand', 'department', 'subDepartment']);

        foreach ([
            'asset_type_id', 'brand_id', 'department_id',
            'sub_department_id', 'status',
        ] as $field) {
            if (filled($filters[$field] ?? null)) {
                $query->where($field, $filters[$field]);
            }
        }

        if (filled($filters['assigned_to'] ?? null)) {
            $filters['assigned_to'] === '__unassigned__'
                ? $query->whereNull('assigned_to')
                : $query->where('assigned_to', $filters['assigned_to']);
        }

        $this->applyCoverageFilter($query, 'warranty_expiry', $filters['warranty'] ?? null);
        $this->applyCoverageFilter($query, 'amc_expiry', $filters['amc'] ?? null);

        return $query;
    }

    private function applyCoverageFilter(Builder $query, string $column, ?string $filter): void
    {
        match ($filter) {
            'available' => $query->whereDate($column, '>', today()->addDays(30)),
            'expiring' => $query->whereBetween($column, [today(), today()->addDays(30)]),
            'expired' => $query->whereDate($column, '<', today()),
            'none' => $query->whereNull($column),
            'any' => $query->whereNotNull($column),
            default => null,
        };
    }

    private function rules(Request $request): array
    {
return [
            'asset_type_id' => ['nullable', 'exists:asset_types,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'sub_department_id' => ['nullable', Rule::exists('sub_departments', 'id')->where(
                fn ($query) => filled($request->input('department_id'))
                    ? $query->where('department_id', $request->input('department_id'))
                    : $query
            )],
            'assigned_to' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['Active', 'In Stock', 'Under Maintenance', 'Retired'])],
            'warranty' => ['nullable', Rule::in(['any', 'available', 'expiring', 'expired', 'none'])],
            'amc' => ['nullable', Rule::in(['any', 'available', 'expiring', 'expired', 'none'])],
        ];
    }
}
