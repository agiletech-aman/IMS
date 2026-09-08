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
 * Every workbook (export, or the sample) has one sheet named exactly after an
 * Asset Type. Each sheet only carries what's actually needed to create/update
 * an Asset under the Type → Subtype → Brand → Parameters model: Name, Asset
 * Type, Subtype Code, Brand, then one column per that Type's currently
 * configured Subtype Parameters — pulled live from AssetType, never
 * hardcoded, so the sheet stays free of columns that don't apply to that
 * type. Import identifies each sheet's type by its name and validates/
 * creates rows using only that type's column set.
 */
class AssetCsv
{
    private const COMMON_FIELDS = [
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
     * One column per Parameter currently configured on the Asset Type — the
     * value shown/expected is that row's resolved Subtype's configured value
     * for that parameter.
     */
    public static function parameterColumns(AssetType $type): array
    {
        return collect($type->parameters ?? [])
            ->map(fn (string $parameter) => [
                'key' => self::normalizeKey($parameter),
                'label' => $parameter,
                'required' => false,
                'parameter' => $parameter,
            ])
            ->all();
    }

    /**
     * The ordered column list for one Asset Type's sheet: Name, Asset Type,
     * Subtype Code, Brand, then that type's own parameter columns.
     */
    public static function columnSpec(AssetType $type, bool $withAssetTag = false): array
    {
        $spec = [];

        if ($withAssetTag) {
            $spec[] = ['key' => 'asset_tag', 'label' => 'Asset Tag', 'required' => false];
        }

        $spec[] = ['key' => 'name', 'label' => 'Name', 'required' => true];
        $spec[] = ['key' => 'asset_type', 'label' => 'Asset Type', 'required' => true];
        $spec[] = ['key' => 'subtype_code', 'label' => 'Subtype Code', 'required' => false];
        $spec[] = ['key' => 'brand', 'label' => 'Brand', 'required' => true];

        foreach (self::COMMON_FIELDS as $key => $meta) {
            $spec[] = ['key' => $key, 'label' => $meta['label'], 'required' => $meta['required']];
        }

        foreach (self::parameterColumns($type) as $field) {
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
     * the normal centre scope); otherwise it's a sample: the header row plus
     * one example row built from that type's first configured Subtype, so the
     * sample always reflects real master data instead of being blank.
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
                ->with(['brand', 'subtype', 'department', 'subDepartment'])
                ->orderBy('id')
                ->get();

            foreach ($assets as $asset) {
                foreach ($spec as $index => $column) {
                    $sheet->setCellValue(
                        Coordinate::stringFromColumnIndex($index + 1).$rowNumber,
                        self::valueForColumn($asset, $column, $type)
                    );
                }
                $rowNumber++;
            }
        } else {
            $sampleSubtype = AssetSubtype::where('asset_type_id', $type->id)
                ->where('status', 'Active')
                ->with('brand')
                ->orderBy('name')
                ->first();

            foreach ($spec as $index => $column) {
                $sheet->setCellValue(
                    Coordinate::stringFromColumnIndex($index + 1).$rowNumber,
                    self::sampleValueForColumn($column, $type, $sampleSubtype)
                );
            }
            $rowNumber++;
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

    private static function valueForColumn(Asset $asset, array $column, AssetType $type): string
    {
        if (isset($column['parameter'])) {
            return self::parameterValueForAsset($asset, $column['parameter']);
        }

        return match ($column['key']) {
            'asset_tag' => (string) $asset->asset_tag,
            'name' => (string) $asset->name,
            'asset_type' => $type->name,
            'subtype_code' => (string) ($asset->subtype?->code ?? ''),
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
     * A realistic example value for the sample download, built from real
     * master data (the type's own first active Subtype) rather than blanks.
     */
    private static function sampleValueForColumn(array $column, AssetType $type, ?AssetSubtype $subtype): string
    {
        if (isset($column['parameter'])) {
            return (string) ($subtype?->parameter_values[$column['parameter']] ?? '');
        }

        return match ($column['key']) {
            'asset_tag' => '',
            'name' => 'Sample '.$type->name,
            'asset_type' => $type->name,
            'subtype_code' => (string) ($subtype?->code ?? ''),
            'brand' => (string) ($subtype?->brand?->name ?? ''),
            'department' => 'Sample Department',
            'sub_department' => 'Sample Sub Department',
            'serial_number' => '',
            'fr_number' => '',
            'installation_date' => now()->format('Y-m-d'),
            'assigned_to' => '',
            'status' => 'In Stock',
            'warranty_expiry' => '',
            'amc_expiry' => '',
            'notes' => '',
            default => '',
        };
    }

    /**
     * A Parameter's value for one asset: the assigned Subtype's configured
     * value, falling back to the pre-redesign per-asset subtype_values entry
     * whose field name matches this parameter (backward compatibility for
     * assets that predate Subtype-level parameter configuration — that old
     * data is never modified, just still surfaced here when it maps cleanly).
     */
    private static function parameterValueForAsset(Asset $asset, string $parameter): string
    {
        if ($asset->subtype) {
            $value = $asset->subtype->parameter_values[$parameter] ?? null;

            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        foreach ($asset->subtypeFieldValues() as $field) {
            if (Str::lower(trim($field['label'])) === Str::lower(trim($parameter))) {
                return (string) $field['value'];
            }
        }

        return '';
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
     * spec — the type is fixed by which sheet the row came from, and the
     * row's own Asset Type column is cross-checked against it.
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

        /*
        |--------------------------------------------------------------------------
        | Asset Type — validated against the master, cross-checked against the sheet
        |--------------------------------------------------------------------------
        */

        $typeName = trim((string) ($row['asset_type'] ?? ''));
        if ($typeName !== '') {
            $matchedType = AssetType::whereRaw('LOWER(name)=?', [Str::lower($typeName)])->first();

            if (! $matchedType) {
                $errors[] = "Asset Type \"{$typeName}\" does not exist.";
            } elseif ($matchedType->id !== $type->id) {
                $errors[] = "Asset Type \"{$typeName}\" does not match this sheet's Asset Type \"{$type->name}\".";
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Brand — validated against the master, never created
        |--------------------------------------------------------------------------
        */

        $brandName = trim((string) ($row['brand'] ?? ''));
        $brand = null;
        if ($brandName !== '') {
            $brand = Brand::whereRaw('LOWER(name)=?', [Str::lower($brandName)])->first();
            if (! $brand) {
                $errors[] = "Brand \"{$brandName}\" does not exist.";
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Department / Sub Department — validated against the master
        |--------------------------------------------------------------------------
        */

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

        /*
        |--------------------------------------------------------------------------
        | Subtype — identified by its unique Code, must belong to this Type
        |--------------------------------------------------------------------------
        */

        $subtypeCode = trim((string) ($row['subtype_code'] ?? ''));
        $subtype = null;

        if ($subtypeCode !== '') {
            $subtype = AssetSubtype::whereRaw('LOWER(code)=?', [Str::lower($subtypeCode)])->first();

            if (! $subtype) {
                $errors[] = "Invalid Subtype Code: {$subtypeCode}";
            } elseif ($subtype->asset_type_id !== $type->id) {
                $errors[] = "Subtype {$subtypeCode} does not belong to Asset Type {$type->name}.";
                $subtype = null;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Parameter values — validated against the resolved Subtype's config
        |--------------------------------------------------------------------------
        */

        if ($subtype) {
            foreach ($spec as $column) {
                if (! isset($column['parameter'])) {
                    continue;
                }

                $provided = trim((string) ($row[$column['key']] ?? ''));
                $configured = trim((string) ($subtype->parameter_values[$column['parameter']] ?? ''));

                if ($provided !== '' && $configured !== '' && Str::lower($provided) !== Str::lower($configured)) {
                    $errors[] = "{$column['label']} value \"{$provided}\" does not match the configured value \"{$configured}\" for Subtype \"{$subtype->code}\".";
                }
            }

            if ($subtype->is_required) {
                foreach ($type->parameters ?? [] as $parameterName) {
                    $configured = trim((string) ($subtype->parameter_values[$parameterName] ?? ''));

                    if ($configured === '') {
                        $errors[] = "Parameter \"{$parameterName}\" is required for Subtype \"{$subtype->code}\" but has no configured value — set it on the Subtype before importing.";
                    }
                }
            }
        }

        $dates = [];
        foreach ([
            'installation_date' => 'Installation Date',
            'warranty_expiry' => 'Warranty Expiry',
            'amc_expiry' => 'AMC Expiry',
        ] as $field => $label) {
            $value = trim((string) ($row[$field] ?? ''));

            if ($value === '') {
                continue;
            }

            $normalized = self::parseFlexibleDate($value);

            if ($normalized === null) {
                $errors[] = "{$label} \"{$value}\" is not a recognized date. Use YYYY-MM-DD (e.g. 2026-01-15) or DD/MM/YYYY.";
            } else {
                $dates[$field] = $normalized;
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
                'asset_subtype_id' => $subtype?->id,
                'brand_id' => $brand?->id,
                'department_id' => $department?->id,
                'sub_department_id' => $subDepartment?->id,
                'installation_date' => $dates['installation_date'] ?? null,
                'warranty_expiry' => $dates['warranty_expiry'] ?? null,
                'amc_expiry' => $dates['amc_expiry'] ?? null,
            ],
        ];
    }

    private static function normalizeKey(string $label): string
    {
        return Str::of($label)->trim()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->value();
    }

    /**
     * Parse a date cell value against several formats real spreadsheets
     * commonly produce (ISO, and the DD/MM/YYYY-style formats Excel applies
     * when a user types a date and lets it auto-format), returning it
     * normalized to Y-m-d, or null if none of them match.
     */
    private static function parseFlexibleDate(string $value): ?string
    {
        foreach (['!Y-m-d', '!d/m/Y', '!d-m-Y', '!d.m.Y', '!m/d/Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            $formatErrors = \DateTimeImmutable::getLastErrors();

            if (
                $date !== false
                && ($formatErrors === false || ($formatErrors['warning_count'] === 0 && $formatErrors['error_count'] === 0))
            ) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }
}
