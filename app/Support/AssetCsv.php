<?php

namespace App\Support;

use App\Models\Asset;
use App\Models\AssetSubtype;
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
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Asset Excel import/export, one worksheet per Asset Type.
 *
 * Every workbook (export, or the blank import sample) has one sheet named
 * exactly after an Asset Type. Each sheet only carries the fields common to
 * every asset plus that type's own active Subtype fields — pulled live from
 * AssetSubtype, never hardcoded — so the sheet stays free of columns that
 * don't apply to that type. Import identifies each sheet's type by its name
 * and validates/creates rows using only that type's column set.
 */
class AssetCsv
{
    private const COMMON_FIELDS = [
        'name' => ['label' => 'Name', 'required' => true],
        'brand' => ['label' => 'Brand', 'required' => true],
        'department' => ['label' => 'Department', 'required' => true],
        'sub_department' => ['label' => 'Sub Department', 'required' => true],
        'serial_number' => ['label' => 'Serial Number', 'required' => true],
        'fr_number' => ['label' => 'FR Number', 'required' => true],
        'installation_date' => ['label' => 'Installation Date', 'required' => true],
    ];

    private const TAIL_FIELDS = [
        'assigned_to' => ['label' => 'Assigned To', 'required' => false],
        'status' => ['label' => 'Status', 'required' => true],
        'warranty_expiry' => ['label' => 'Warranty Expiry', 'required' => false],
        'amc_expiry' => ['label' => 'AMC Expiry', 'required' => false],
        'notes' => ['label' => 'Notes', 'required' => false],
    ];

    /**
     * The active Subtype fields belonging to one Asset Type, as column spec entries.
     */
    public static function subtypeFields(AssetType $type): array
    {
        return AssetSubtype::where('asset_type_id', $type->id)
            ->where('status', 'Active')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (AssetSubtype $subtype) => [
                'key' => self::normalizeKey($subtype->name),
                'label' => $subtype->name,
                'required' => true,
                'subtype_id' => $subtype->id,
            ])
            ->all();
    }

    /**
     * The ordered column list for one Asset Type's sheet: common fields, then
     * that type's own subtype fields, then the tail fields.
     */
    public static function columnSpec(AssetType $type, bool $withAssetTag = false): array
    {
        $spec = [];

        if ($withAssetTag) {
            $spec[] = ['key' => 'asset_tag', 'label' => 'Asset Tag', 'required' => false];
        }

        foreach (self::COMMON_FIELDS as $key => $meta) {
            $spec[] = ['key' => $key, 'label' => $meta['label'], 'required' => $meta['required']];
        }

        foreach (self::subtypeFields($type) as $field) {
            $spec[] = $field;
        }

        foreach (self::TAIL_FIELDS as $key => $meta) {
            $spec[] = ['key' => $key, 'label' => $meta['label'], 'required' => $meta['required']];
        }

        return $spec;
    }

    /**
     * The worksheet title an Asset Type's sheet uses on export (Excel titles
     * are capped at 31 chars and can't contain \ / ? * [ ] :).
     */
    public static function sheetTitle(AssetType $type): string
    {
        $title = preg_replace('/[\\\\\/\?\*\[\]:]/', '-', trim($type->name));

        return mb_substr($title, 0, 31);
    }

    /**
     * Build a workbook with one sheet per given Asset Type. When $withData is
     * true, each sheet is filled with that type's existing assets (respecting
     * the normal centre scope); otherwise it's just the header row (a sample).
     */
    public static function exportWorkbook(array $assetTypeIds, string $filePath, bool $withData): void
    {
        $types = AssetType::whereIn('id', $assetTypeIds)->orderBy('name')->get();

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        foreach ($types as $type) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle(self::sheetTitle($type));
            self::writeSheet($sheet, $type, $withData);
        }

        if ($spreadsheet->getSheetCount() === 0) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle('Assets');
            $sheet->setCellValue('A1', 'No asset types were selected.');
        }

        $spreadsheet->setActiveSheetIndex(0);

        (new Xlsx($spreadsheet))->save($filePath);
    }

    private static function writeSheet(Worksheet $sheet, AssetType $type, bool $withData): void
    {
        $spec = self::columnSpec($type, withAssetTag: true);
        $lastColumn = Coordinate::stringFromColumnIndex(count($spec));

        foreach ($spec as $index => $column) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1).'1', $column['label']);
        }

        $rowNumber = 2;

        if ($withData) {
            $assets = Asset::where('asset_type_id', $type->id)
                ->with(['brand', 'department', 'subDepartment'])
                ->orderBy('id')
                ->get();

            foreach ($assets as $asset) {
                foreach ($spec as $index => $column) {
                    $sheet->setCellValue(
                        Coordinate::stringFromColumnIndex($index + 1).$rowNumber,
                        self::valueForColumn($asset, $column)
                    );
                }
                $rowNumber++;
            }
        }

        $lastRow = max($rowNumber - 1, 1);

        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
            'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
        ]);

        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
        ]);

        foreach (range('A', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $sheet->getRowDimension(1)->setRowHeight(24);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}1");
    }

    private static function valueForColumn(Asset $asset, array $column): string
    {
        if (isset($column['subtype_id'])) {
            return (string) ($asset->subtype_values[$column['subtype_id']] ?? '');
        }

        return match ($column['key']) {
            'asset_tag' => (string) $asset->asset_tag,
            'name' => (string) $asset->name,
            'brand' => $asset->brand?->name ?? '',
            'department' => $asset->department?->name ?? '',
            'sub_department' => $asset->subDepartment?->name ?? '',
            'serial_number' => (string) ($asset->serial_number ?? ''),
            'fr_number' => (string) ($asset->fr_number ?? ''),
            'installation_date' => $asset->installation_date?->format('Y-m-d') ?? '',
            'assigned_to' => (string) ($asset->assigned_to ?? ''),
            'status' => (string) ($asset->status ?? ''),
            'warranty_expiry' => $asset->warranty_expiry?->format('Y-m-d') ?? '',
            'amc_expiry' => $asset->amc_expiry?->format('Y-m-d') ?? '',
            'notes' => (string) ($asset->notes ?? ''),
            default => '',
        };
    }

    /**
     * Parse an uploaded workbook into one entry per recognized sheet. A sheet
     * is matched to an Asset Type by its title (falling back to the type's
     * sanitized export title, so round-tripping a downloaded file always
     * matches even if the type name held characters Excel forbids in titles).
     * Sheets that don't match any type, or are missing a required column,
     * are reported in 'fileErrors' and skipped rather than guessed at.
     */
    public static function parseWorkbook(string $path): array
    {
        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable $exception) {
            return ['sheets' => [], 'fileErrors' => ['Unable to read the file: '.$exception->getMessage()]];
        }

        $types = AssetType::all();
        $sheets = [];
        $fileErrors = [];

        foreach ($spreadsheet->getAllSheets() as $worksheet) {
            $title = trim($worksheet->getTitle());

            $type = $types->first(fn (AssetType $candidate) => Str::lower(trim($candidate->name)) === Str::lower($title)
                || Str::lower(self::sheetTitle($candidate)) === Str::lower($title));

            if (! $type) {
                $fileErrors[] = "Sheet \"{$title}\" doesn't match any Asset Type — it was skipped.";

                continue;
            }

            $data = $worksheet->toArray('', true, true, false);

            if ($data === [] || ! isset($data[0])) {
                continue;
            }

            $spec = self::columnSpec($type);
            $specWithTag = self::columnSpec($type, withAssetTag: true);
            $mapping = self::mapHeader($data[0], $specWithTag);

            $missing = collect($spec)
                ->filter(fn (array $column) => $column['required'] && ! isset($mapping[$column['key']]))
                ->pluck('label')
                ->all();

            if ($missing !== []) {
                $fileErrors[] = "Sheet \"{$title}\": missing required column(s): ".implode(', ', $missing);

                continue;
            }

            $rows = [];

            foreach (array_slice($data, 1, null, true) as $zeroBasedRow => $values) {
                if (collect($values)->every(fn ($value) => trim((string) $value) === '')) {
                    continue;
                }

                $row = [];
                foreach ($specWithTag as $column) {
                    $index = $mapping[$column['key']] ?? null;
                    $row[$column['key']] = $index === null ? '' : trim((string) ($values[$index] ?? ''));
                }
                $rows[$zeroBasedRow + 1] = $row;
            }

            $sheets[] = ['type' => $type, 'rows' => $rows];
        }

        if ($sheets === [] && $fileErrors === []) {
            $fileErrors[] = 'The file contains no sheets matching a known Asset Type.';
        }

        return ['sheets' => $sheets, 'fileErrors' => $fileErrors];
    }

    private static function mapHeader(array $headings, array $spec): array
    {
        $keyByLabel = collect($spec)->mapWithKeys(fn (array $column) => [self::normalizeKey($column['label']) => $column['key']]);

        $mapping = [];
        foreach ($headings as $index => $heading) {
            $normalized = self::normalizeKey((string) $heading);
            if (isset($keyByLabel[$normalized])) {
                $mapping[$keyByLabel[$normalized]] = $index;
            }
        }

        return $mapping;
    }

    /**
     * Validate and resolve one data row against a known Asset Type's column
     * spec — the type is no longer guessed from a cell, it's already fixed by
     * which sheet the row came from.
     */
    public static function resolveRow(AssetType $type, array $row): array
    {
        $errors = [];
        $spec = self::columnSpec($type);

        foreach ($spec as $column) {
            $value = trim((string) ($row[$column['key']] ?? ''));
            if ($column['required'] && $value === '') {
                $errors[] = "{$column['label']} is required.";
            }
        }

        $brandName = trim((string) ($row['brand'] ?? ''));
        $brand = null;
        if ($brandName !== '') {
            $brand = Brand::whereRaw('LOWER(name)=?', [Str::lower($brandName)])->first();
            if (! $brand) {
                $errors[] = "Brand not found: {$brandName}";
            }
        }

        $departmentName = trim((string) ($row['department'] ?? ''));
        $department = null;
        if ($departmentName !== '') {
            $department = Department::whereRaw('LOWER(name)=?', [Str::lower($departmentName)])->first();
            if (! $department) {
                $errors[] = "Department not found: {$departmentName}";
            }
        }

        $subDepartmentName = trim((string) ($row['sub_department'] ?? ''));
        $subDepartment = null;
        if ($subDepartmentName !== '') {
            $subDepartmentQuery = SubDepartment::whereRaw('LOWER(name)=?', [Str::lower($subDepartmentName)]);
            if ($department) {
                $subDepartmentQuery->where('department_id', $department->id);
            }
            $subDepartment = $subDepartmentQuery->first();

            if (! $subDepartment) {
                $errors[] = $department
                    ? "Sub department not found for department (sub: {$subDepartmentName}, department: {$departmentName})"
                    : "Sub department not found: {$subDepartmentName}";
            }
        }

        $subtypeValues = [];
        foreach ($spec as $column) {
            if (! isset($column['subtype_id'])) {
                continue;
            }
            $value = trim((string) ($row[$column['key']] ?? ''));
            if ($value !== '') {
                $subtypeValues[$column['subtype_id']] = $value;
            }
        }

        foreach ([
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
        if ($status !== '' && ! in_array($status, ['Active', 'In Stock', 'Under Maintenance', 'Retired'], true)) {
            $errors[] = "Invalid status: {$status}";
        }

        return [
            'errors' => $errors,
            'resolved' => [
                'asset_type_id' => $type->id,
                'brand_id' => $brand?->id,
                'department_id' => $department?->id,
                'sub_department_id' => $subDepartment?->id,
                'subtype_values' => $subtypeValues,
            ],
        ];
    }

    private static function normalizeKey(string $label): string
    {
        return Str::of($label)->trim()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->value();
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
