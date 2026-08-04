<?php

namespace App\Support;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetType;
use App\Models\Brand;
use App\Models\Department;
use App\Models\SubDepartment;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AssetCsv
{
    public const COLUMNS = [
        'asset_tag',
        'name',
        'asset_category',
        'asset_type',
        'brand',
        'department',
        'sub_department',
        'model',
        'serial_number',
        'purchase_date',
        'installation_date',
        'location',
        'assigned_to',
        'status',
        'warranty_expiry',
        'amc_expiry',
        'notes',
    ];

    public const IMPORT_COLUMNS = [
        'name',
        'asset_category',
        'asset_type',
        'brand',
        'department',
        'sub_department',
        'model',
        'serial_number',
        'purchase_date',
        'installation_date',
        'location',
        'assigned_to',
        'status',
        'warranty_expiry',
        'amc_expiry',
        'notes',
    ];

    public const IMPORT_HEADINGS = [
        'Name',
        'Asset Category',
        'Asset Type',
        'Brand',
        'Department',
        'Sub Department',
        'Model',
        'Serial Number',
        'Purchase Date',
        'Installation Date',
        'Location',
        'Assigned To',
        'Status',
        'Warranty Expiry',
        'AMC Expiry',
        'Notes',
    ];

    public static function exportRows(): array
    {
        $assets = Asset::with(['category', 'type', 'brand', 'department', 'subDepartment'])
            ->orderBy('id')
            ->get();

        $rows = [];

        foreach ($assets as $asset) {
            $rows[] = [
                'asset_tag' => $asset->asset_tag,
                'name' => $asset->name,
                'asset_category' => $asset->category?->name ?? '',
                'asset_type' => $asset->type?->name ?? '',
                'brand' => $asset->brand?->name ?? '',
                'department' => $asset->department?->name ?? '',
                'sub_department' => $asset->subDepartment?->name ?? '',
                'model' => $asset->model ?? '',
                'serial_number' => $asset->serial_number ?? '',
                'purchase_date' => $asset->purchase_date?->format('Y-m-d') ?? '',
                'installation_date' => $asset->installation_date?->format('Y-m-d') ?? '',
                'location' => $asset->location ?? '',
                'assigned_to' => $asset->assigned_to ?? '',
                'status' => $asset->status ?? '',
                'warranty_expiry' => $asset->warranty_expiry?->format('Y-m-d') ?? '',
                'amc_expiry' => $asset->amc_expiry?->format('Y-m-d') ?? '',
                'notes' => $asset->notes ?? '',
            ];
        }

        return $rows;
    }

    public static function exportStyledExcel(string $filePath): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setTitle('Assets');

        foreach (self::COLUMNS as $index => $heading) {
            $columnLetter = Coordinate::stringFromColumnIndex($index + 1);

            $sheet->setCellValue(
                $columnLetter . '1',
                strtoupper(str_replace('_', ' ', $heading))
            );
        }

        $rowNumber = 2;

        foreach (self::exportRows() as $row) {
            foreach (self::COLUMNS as $index => $column) {
                $columnLetter = Coordinate::stringFromColumnIndex($index + 1);

                $sheet->setCellValue(
                    $columnLetter . $rowNumber,
                    $row[$column] ?? ''
                );
            }

            $rowNumber++;
        }

        $lastColumn = $sheet->getHighestColumn();
        $lastRow = $sheet->getHighestRow();

        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F4E78'],
            ],
            'alignment' => [
                'horizontal' => 'center',
                'vertical' => 'center',
            ],
        ]);

        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC'],
                ],
            ],
        ]);

        foreach (range('A', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}1");

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);
    }

    public static function exportStyledImportSample(string $filePath): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Asset Import');

        foreach (self::IMPORT_HEADINGS as $index => $heading) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1).'1', $heading);
        }

        $lastColumn = Coordinate::stringFromColumnIndex(count(self::IMPORT_HEADINGS));
        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F4E78'],
            ],
            'alignment' => [
                'horizontal' => 'center',
                'vertical' => 'center',
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC'],
                ],
            ],
        ]);

        for ($column = 1; $column <= count(self::IMPORT_HEADINGS); $column++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
        }

        $sheet->getRowDimension(1)->setRowHeight(24);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}1");

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);
    }

    public static function parseFile(string $path): array
    {
        $extension = Str::lower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($extension, ['csv', 'txt'], true)) {
            return self::parseCsv($path);
        }

        try {
            $sheet = IOFactory::load($path)->getActiveSheet();
        } catch (\Throwable $exception) {
            return ['rows' => [], 'errors' => ['Unable to read the Excel file: '.$exception->getMessage()]];
        }

        $data = $sheet->toArray('', true, true, false);
        if ($data === [] || ! isset($data[0])) {
            return ['rows' => [], 'errors' => ['Excel header missing.']];
        }

        $mapping = self::columnMapping($data[0]);
        if ($mapping['errors'] !== []) {
            return ['rows' => [], 'errors' => $mapping['errors']];
        }

        $rows = [];
        foreach (array_slice($data, 1, null, true) as $zeroBasedRow => $values) {
            if (collect($values)->every(fn ($value) => trim((string) $value) === '')) {
                continue;
            }

            $row = [];
            foreach (self::COLUMNS as $column) {
                $index = $mapping['columns'][$column] ?? null;
                $row[$column] = $index === null ? '' : trim((string) ($values[$index] ?? ''));
            }
            $rows[$zeroBasedRow + 1] = $row;
        }

        return ['rows' => $rows, 'errors' => []];
    }

    public static function parseCsv(string $path): array
    {
        $handle = fopen($path, 'rb');

        if (!$handle) {
            return ['rows' => [], 'errors' => ['Unable to open CSV file.']];
        }

        $rows = [];
        $errors = [];

        $header = fgetcsv($handle);

        if ($header === false) {
            return ['rows' => [], 'errors' => ['CSV header missing.']];
        }

        $mapping = self::columnMapping($header);
        if ($mapping['errors'] !== []) {
            fclose($handle);

            return ['rows' => [], 'errors' => $mapping['errors']];
        }

        $rowIndex = 2;

        while (($data = fgetcsv($handle)) !== false) {
            if (collect($data)->every(fn ($value) => trim((string) $value) === '')) {
                $rowIndex++;

                continue;
            }

            $row = [];

            foreach (self::COLUMNS as $col) {
                $idx = $mapping['columns'][$col] ?? null;
                $row[$col] = $idx === null ? '' : (string) ($data[$idx] ?? '');
            }

            $rows[$rowIndex] = $row;
            $rowIndex++;
        }

        fclose($handle); 

        return ['rows' => $rows, 'errors' => $errors];
    }

    public static function resolveIdsForRow(array $row): array
    {
        $errors = [];

        $assetCategoryName = trim((string) ($row['asset_category'] ?? ''));
        $assetTypeName = trim((string) ($row['asset_type'] ?? ''));
        $brandName = trim((string) ($row['brand'] ?? ''));
        $departmentName = trim((string) ($row['department'] ?? ''));
        $subDepartmentName = trim((string) ($row['sub_department'] ?? ''));

        if ($assetCategoryName === '') {
            $errors[] = 'asset_category is required.';
        }

        if ($assetTypeName === '') {
            $errors[] = 'asset_type is required.';
        }

        $category = null;

        if ($assetCategoryName !== '') {
            $category = AssetCategory::whereRaw('LOWER(name)=?', [Str::lower($assetCategoryName)])->first();

            if (!$category) {
                $errors[] = "Asset category not found: {$assetCategoryName}";
            }
        }

        $type = null;

        if ($assetTypeName !== '') {
            if ($category) {
                $type = AssetType::whereRaw('LOWER(name)=?', [Str::lower($assetTypeName)])
                    ->where('asset_category_id', $category->id)
                    ->first();

                if (!$type) {
                    $errors[] = "Asset type not found for category (type: {$assetTypeName}, category: {$assetCategoryName})";
                }
            } else {
                $type = AssetType::whereRaw('LOWER(name)=?', [Str::lower($assetTypeName)])->first();

                if (!$type) {
                    $errors[] = "Asset type not found: {$assetTypeName}";
                }
            }
        }

        $brand = null;

        if ($brandName !== '') {
            $brand = Brand::whereRaw('LOWER(name)=?', [Str::lower($brandName)])->first();

            if (!$brand) {
                $errors[] = "Brand not found: {$brandName}";
            }
        }

        $department = null;

        if ($departmentName !== '') {
            $department = Department::whereRaw('LOWER(name)=?', [Str::lower($departmentName)])->first();

            if (!$department) {
                $errors[] = "Department not found: {$departmentName}";
            }
        }

        $subDepartment = null;

        if ($subDepartmentName !== '') {
            if ($department) {
                $subDepartment = SubDepartment::whereRaw('LOWER(name)=?', [Str::lower($subDepartmentName)])
                    ->where('department_id', $department->id)
                    ->first();

                if (!$subDepartment) {
                    $errors[] = "Sub department not found for department (sub: {$subDepartmentName}, department: {$departmentName})";
                }
            } else {
                $subDepartment = SubDepartment::whereRaw('LOWER(name)=?', [Str::lower($subDepartmentName)])->first();

                if (!$subDepartment) {
                    $errors[] = "Sub department not found: {$subDepartmentName}";
                }
            }
        }

        // asset_tag is optional during import (auto-generated in controller if missing)
        foreach (['name', 'status'] as $field) {
            if (trim((string) ($row[$field] ?? '')) === '') {
                $errors[] = "{$field} is required.";
            }
        }

        foreach ([
            'purchase_date' => 'Purchase Date',
            'installation_date' => 'Installation Date',
            'warranty_expiry' => 'Warranty Expiry',
            'amc_expiry' => 'AMC Expiry',
        ] as $field => $label) {
            $value = trim((string) ($row[$field] ?? ''));
            if ($value !== '' && ! self::isIsoDate($value)) {
                $errors[] = "{$label} must use YYYY-MM-DD format.";
            }
        }


        $status = trim((string) ($row['status'] ?? ''));

        if ($status !== '' && !in_array($status, ['Active', 'In Stock', 'Under Maintenance', 'Retired'], true)) {
            $errors[] = "Invalid status: {$status}";
        }

        return [
            'errors' => $errors,
            'resolved' => [
                'asset_category_id' => $category?->id,
                'asset_type_id' => $type?->id,
                'brand_id' => $brand?->id,
                'department_id' => $department?->id,
                'sub_department_id' => $subDepartment?->id,
            ],
        ];
    }

    private static function columnMapping(array $headings): array
    {
        $columns = [];
        foreach ($headings as $index => $heading) {
            $normalized = Str::of((string) $heading)
                ->trim()
                ->lower()
                ->replaceMatches('/[^a-z0-9]+/', '_')
                ->trim('_')
                ->value();

            if (in_array($normalized, self::COLUMNS, true)) {
                $columns[$normalized] = $index;
            }
        }

        $missing = array_values(array_diff(self::IMPORT_COLUMNS, array_keys($columns)));

        return [
            'columns' => $columns,
            'errors' => $missing === []
                ? []
                : ['Import file missing columns: '.implode(', ', $missing)],
        ];
    }

    private static function isIsoDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();

        return $date !== false
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $date->format('Y-m-d') === $value;
    }
}
