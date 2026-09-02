<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetType;
use App\Services\AuditLogger;
use App\Support\AssetCsv;
use App\Support\UniqueCodeGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AssetImportExportController extends Controller
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function exportCsv(Request $request): BinaryFileResponse
    {
        $typeIds = $this->resolveTypeIds($request);

        $filename = 'assets_export_'.date('Ymd_His').'.xlsx';
        $tmpPath = storage_path('app/'.$filename);

        AssetCsv::exportWorkbook($typeIds, $tmpPath, withData: true);

        $this->audit->record(
            'EXPORT',
            'Assets',
            'Asset inventory was exported to Excel.',
            metadata: [
                'record_count' => Asset::whereIn('asset_type_id', $typeIds)->count(),
                'asset_type_ids' => $typeIds,
                'format' => 'xlsx',
            ],
        );

        return response()->download($tmpPath)->deleteFileAfterSend(true);
    }

    public function importSampleCsv(Request $request): BinaryFileResponse
    {
        $typeIds = $this->resolveTypeIds($request);

        $filename = 'assets_import_sample.xlsx';
        $tmpPath = storage_path('app/'.$filename);

        AssetCsv::exportWorkbook($typeIds, $tmpPath, withData: false);
        $this->audit->record('DOWNLOAD', 'Assets', 'Asset import sample was downloaded.');

        return response()->download($tmpPath)->deleteFileAfterSend(true);
    }

    public function importCsv(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'csv' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ], [
            'csv.required' => 'Please select an Excel file to import.',
            'csv.file' => 'The selected upload is not a valid file.',
            'csv.mimes' => 'Only XLSX and XLS files are allowed — each Asset Type is its own sheet, which a plain CSV can\'t hold.',
            'csv.max' => 'The import file must not be larger than 5 MB.',
        ]);

        if ($validator->fails()) {
            $this->audit->record(
                'IMPORT',
                'Assets',
                'Asset import was rejected during file validation.',
                result: 'Failed',
                metadata: ['errors' => $validator->errors()->all()],
            );

            return back()
                ->withErrors($validator, 'assetImport')
                ->with('openAssetImportModal', true);
        }

        $file = $request->file('csv');
        $parsed = AssetCsv::parseWorkbook($file->getRealPath());
        $sheets = $parsed['sheets'] ?? [];
        $fileErrors = $parsed['fileErrors'] ?? [];

        $errors = [];
        foreach ($fileErrors as $message) {
            $errors[] = ['row' => null, 'sheet' => null, 'asset' => null, 'messages' => [$message]];
        }

        if ($sheets === []) {
            $this->audit->record(
                'IMPORT',
                'Assets',
                'Asset import file contained no recognizable sheets.',
                result: 'Failed',
                metadata: ['errors' => $fileErrors],
            );

            return back()
                ->with('importErrors', $errors)
                ->with('importResult', ['errors' => count($errors), 'inserted' => 0, 'updated' => 0])
                ->with('error', $fileErrors[0] ?? 'The import file could not be read.')
                ->with('openAssetImportModal', true);
        }

        $importCount = 0;
        $totalRows = 0;

        foreach ($sheets as $sheet) {
            $type = $sheet['type'];

            foreach ($sheet['rows'] as $rowNumber => $row) {
                $totalRows++;
                $assetName = trim((string) ($row['name'] ?? '')) ?: 'Unnamed asset';

                $resolved = AssetCsv::resolveRow($type, $row);
                $rowErrors = $resolved['errors'] ?? [];

                if ($rowErrors !== []) {
                    $errors[] = ['row' => $rowNumber, 'sheet' => $type->name, 'asset' => $assetName, 'messages' => $rowErrors];

                    continue;
                }

                $resolvedIds = $resolved['resolved'];

                $payload = [
                    'name' => (string) $row['name'],
                    'asset_type_id' => $type->id,
                    'subtype_values' => $resolvedIds['subtype_values'] ?? [],
                    'brand_id' => $resolvedIds['brand_id'] ?? null,
                    'department_id' => $resolvedIds['department_id'] ?? null,
                    'sub_department_id' => $resolvedIds['sub_department_id'] ?? null,
                    'serial_number' => (string) $row['serial_number'],
                    'fr_number' => (string) $row['fr_number'],
                    'installation_date' => $this->toDate($row['installation_date'] ?? ''),
                    'assigned_to' => (string) ($row['assigned_to'] ?? ''),
                    'status' => (string) $row['status'],
                    'warranty_expiry' => $this->toDate($row['warranty_expiry'] ?? ''),
                    'amc_expiry' => $this->toDate($row['amc_expiry'] ?? ''),
                    'notes' => (string) ($row['notes'] ?? ''),
                ];

                $providedAssetTag = trim((string) ($row['asset_tag'] ?? ''));
                $serialNumber = trim((string) $payload['serial_number']);

                if ($providedAssetTag !== '' && Asset::where('asset_tag', $providedAssetTag)->exists()) {
                    $errors[] = ['row' => $rowNumber, 'sheet' => $type->name, 'asset' => $assetName, 'messages' => ["Asset Tag '{$providedAssetTag}' already exists."]];

                    continue;
                }

                if ($serialNumber !== '' && Asset::where('serial_number', $serialNumber)->exists()) {
                    $errors[] = ['row' => $rowNumber, 'sheet' => $type->name, 'asset' => $assetName, 'messages' => ["Serial Number '{$serialNumber}' already exists."]];

                    continue;
                }

                $assetTag = $providedAssetTag;

                if ($assetTag === '') {
                    $characters = Str::of($payload['name'])->ascii()->upper()->replaceMatches('/[^A-Z0-9]/', '')->value();
                    $first = $characters[0] ?? 'X';
                    $last = $characters !== '' ? $characters[strlen($characters) - 1] : 'X';
                    $assetPrefix = 'AST-'.$first.$last;

                    $assetTag = UniqueCodeGenerator::generate(
                        'assets:'.$first.$last,
                        $assetPrefix,
                        'assets',
                        'asset_tag'
                    );
                }

                try {
                    Asset::create($payload + ['asset_tag' => $assetTag]);
                    $importCount++;
                } catch (\Throwable $e) {
                    report($e);
                    $errors[] = ['row' => $rowNumber, 'sheet' => $type->name, 'asset' => $assetName, 'messages' => ['Could not save this asset. Check for a duplicate Serial Number or invalid value.']];

                    continue;
                }
            }
        }

        if ($totalRows === 0 && $fileErrors === []) {
            $errors[] = ['row' => null, 'sheet' => null, 'asset' => null, 'messages' => ['The import file contains headings but no asset rows.']];
        }

        $response = back()->with('importErrors', $errors)
            ->with('importResult', [
                'errors' => count($errors),
                'inserted' => $importCount,
                'updated' => 0,
            ])
            ->with('openAssetImportModal', $errors !== []);
        $this->audit->record(
            'IMPORT',
            'Assets',
            "Asset import completed with {$importCount} inserted and ".count($errors).' failed rows.',
            result: $errors === [] ? 'Success' : 'Failed',
            metadata: ['inserted' => $importCount, 'failed' => count($errors)],
        );

        if ($errors !== []) {
            return $response->with(
                'warning',
                count($errors).' row(s) could not be imported. Please check the row-wise errors.'
            );
        }

        return $response->with(
            'success',
            "Asset import completed: {$importCount} asset(s) imported successfully."
        );
    }

    /**
     * The Asset Type ids selected for an export/sample download. Falls back
     * to every active type when none are explicitly checked, so the plain
     * "Export" link still works without opening the type picker.
     */
    private function resolveTypeIds(Request $request): array
    {
        $requested = collect($request->input('types', []))
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        return AssetType::where('status', 'Active')
            ->when($requested->isNotEmpty(), fn ($query) => $query->whereIn('id', $requested))
            ->orderBy('name')
            ->pluck('id')
            ->all();
    }

    private function toDate($v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }

        // Expect Y-m-d from export
        return $v;
    }
}
