<?php

namespace App\Http\Controllers;

use App\Models\Asset;
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

    public function exportCsv(): BinaryFileResponse
    {
        $filename = 'assets_export_'.date('Ymd_His').'.xlsx';
        $tmpPath = storage_path('app/'.$filename);

        AssetCsv::exportStyledExcel($tmpPath);
        $this->audit->record(
            'EXPORT',
            'Assets',
            'Asset inventory was exported to Excel.',
            metadata: ['record_count' => Asset::count(), 'format' => 'xlsx'],
        );

        return response()->download($tmpPath)->deleteFileAfterSend(true);
    }

    public function importSampleCsv(): BinaryFileResponse
    {
        $filename = 'assets_import_sample.xlsx';
        $tmpPath = storage_path('app/'.$filename);

        AssetCsv::exportStyledImportSample($tmpPath);
        $this->audit->record('DOWNLOAD', 'Assets', 'Asset import sample was downloaded.');

        return response()->download($tmpPath)->deleteFileAfterSend(true);
    }

    public function importCsv(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'csv' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
        ], [
            'csv.required' => 'Please select an Excel or CSV file to import.',
            'csv.file' => 'The selected upload is not a valid file.',
            'csv.mimes' => 'Only XLSX, XLS, CSV, and TXT files are allowed.',
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
        $parsed = AssetCsv::parseFile($file->getRealPath());
        $rows = $parsed['rows'] ?? [];
        $parseErrors = $parsed['errors'] ?? [];

        $errors = [];
        $importCount = 0;

        // If header parse failed, show errors and stop
        if (! empty($parseErrors)) {
            $this->audit->record(
                'IMPORT',
                'Assets',
                'Asset import file could not be parsed.',
                result: 'Failed',
                metadata: ['errors' => $parseErrors],
            );

            return back()
                ->with('importErrors', [['row' => null, 'asset' => null, 'messages' => $parseErrors]])
                ->with('importResult', ['errors' => count($parseErrors), 'inserted' => 0, 'updated' => 0])
                ->with('error', $parseErrors[0] ?? 'The import file could not be read.')
                ->with('openAssetImportModal', true);
        }

        if ($rows === []) {
            $this->audit->record(
                'IMPORT',
                'Assets',
                'Asset import file contained no data rows.',
                result: 'Failed',
            );

            return back()
                ->with('importErrors', [[
                    'row' => null,
                    'asset' => null,
                    'messages' => ['The import file contains headings but no asset rows.'],
                ]])
                ->with('importResult', ['errors' => 1, 'inserted' => 0, 'updated' => 0])
                ->with('error', 'The import file contains headings but no asset rows.')
                ->with('openAssetImportModal', true);
        }

        foreach ($rows as $rowNumber => $row) {
            $resolved = AssetCsv::resolveIdsForRow($row);
            $rowErrors = $resolved['errors'] ?? [];

            if (! empty($rowErrors)) {
                $errors[] = [
                    'row' => $rowNumber,
                    'asset' => trim((string) ($row['name'] ?? '')) ?: 'Unnamed asset',
                    'messages' => $rowErrors,
                ];

                continue;
            }

            $resolvedIds = $resolved['resolved'] ?? [];

            $payload = [
                'name' => (string) $row['name'],
                'asset_category_id' => (int) ($resolvedIds['asset_category_id'] ?? 0),
                'asset_type_id' => (int) ($resolvedIds['asset_type_id'] ?? 0),
                'brand_id' => $resolvedIds['brand_id'] ? (int) $resolvedIds['brand_id'] : null,
                'department_id' => $resolvedIds['department_id'] ? (int) $resolvedIds['department_id'] : null,
                'sub_department_id' => $resolvedIds['sub_department_id'] ? (int) $resolvedIds['sub_department_id'] : null,
                'model' => (string) $row['model'],
                'serial_number' => (string) $row['serial_number'],
                'purchase_date' => $this->toDate($row['purchase_date']),
                'installation_date' => $this->toDate($row['installation_date']),
                'location' => (string) $row['location'],
                'assigned_to' => (string) $row['assigned_to'],
                'status' => (string) $row['status'],
                'warranty_expiry' => $this->toDate($row['warranty_expiry']),
                'amc_expiry' => $this->toDate($row['amc_expiry']),
                'notes' => (string) $row['notes'],
            ];

            $providedAssetTag = trim((string) ($row['asset_tag'] ?? ''));
            $serialNumber = trim((string) ($payload['serial_number'] ?? ''));

            if ($providedAssetTag !== '' && Asset::where('asset_tag', $providedAssetTag)->exists()) {
                $errors[] = [
                    'row' => $rowNumber,
                    'asset' => trim((string) ($row['name'] ?? '')) ?: 'Unnamed asset',
                    'messages' => ["Asset Tag '{$providedAssetTag}' already exists."],
                ];

                continue;
            }

            if ($serialNumber !== '' && Asset::where('serial_number', $serialNumber)->exists()) {
                $errors[] = [
                    'row' => $rowNumber,
                    'asset' => trim((string) ($row['name'] ?? '')) ?: 'Unnamed asset',
                    'messages' => ["Serial Number '{$serialNumber}' already exists."],
                ];

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
                $errors[] = [
                    'row' => $rowNumber,
                    'asset' => trim((string) ($row['name'] ?? '')) ?: 'Unnamed asset',
                    'messages' => ['Could not save this asset. Check for a duplicate Serial Number or invalid value.'],
                ];

                continue;
            }
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
