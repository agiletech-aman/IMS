<?php

namespace App\Support;

use App\Models\AssetSubtype;
use App\Models\AssetType;
use App\Models\Brand;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Asset Subtype Excel import/export, one worksheet per Asset Type — the same
 * pattern as AssetCsv, applied to the Subtype master instead of Assets. Each
 * sheet only carries that Type's own configured Parameter columns, pulled
 * live from AssetType, so a Type's sheet never shows another Type's fields.
 */
class SubtypeCsv
{
    /**
     * One column per Parameter currently configured on the Asset Type.
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
     * The ordered column list for one Asset Type's sheet: Name, Code, Brand,
     * Status, Description, then that type's own parameter columns.
     */
    public static function columnSpec(AssetType $type, bool $withCode = false): array
    {
        $spec = [];

        $spec[] = ['key' => 'name', 'label' => 'Name', 'required' => true];

        if ($withCode) {
            $spec[] = ['key' => 'code', 'label' => 'Code', 'required' => false];
        }

        $spec[] = ['key' => 'brand', 'label' => 'Brand', 'required' => true];
        $spec[] = ['key' => 'status', 'label' => 'Status', 'required' => true];
        $spec[] = ['key' => 'description', 'label' => 'Description', 'required' => false];

        foreach (self::parameterColumns($type) as $field) {
            $spec[] = $field;
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
     * true, each sheet is filled with that type's existing Subtypes; otherwise
     * it's a sample: the header row plus one illustrative example row.
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
            $sheet->setTitle('Subtypes');
            $sheet->setCellValue('A1', 'No asset types were selected.');
        }

        $spreadsheet->setActiveSheetIndex(0);

        (new Xlsx($spreadsheet))->save($filePath);
    }

    private static function writeSheet(Worksheet $sheet, AssetType $type, bool $withData): void
    {
        $spec = self::columnSpec($type, withCode: true);
        $lastColumn = Coordinate::stringFromColumnIndex(count($spec));

        foreach ($spec as $index => $column) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1).'1', $column['label']);
        }

        $rowNumber = 2;

        if ($withData) {
            $subtypes = AssetSubtype::where('asset_type_id', $type->id)
                ->with('brand')
                ->orderBy('name')
                ->get();

            foreach ($subtypes as $subtype) {
                foreach ($spec as $index => $column) {
                    $sheet->setCellValue(
                        Coordinate::stringFromColumnIndex($index + 1).$rowNumber,
                        self::valueForColumn($subtype, $column)
                    );
                }
                $rowNumber++;
            }
        } else {
            $exampleBrand = Brand::where('status', 'Active')->orderBy('name')->first();

            foreach ($spec as $index => $column) {
                $sheet->setCellValue(
                    Coordinate::stringFromColumnIndex($index + 1).$rowNumber,
                    self::sampleValueForColumn($column, $type, $exampleBrand)
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

    private static function valueForColumn(AssetSubtype $subtype, array $column): string
    {
        if (isset($column['parameter'])) {
            return (string) ($subtype->parameter_values[$column['parameter']] ?? '');
        }

        return match ($column['key']) {
            'name' => (string) $subtype->name,
            'code' => (string) $subtype->code,
            'brand' => $subtype->brand?->name ?? '',
            'status' => (string) $subtype->status,
            'description' => (string) ($subtype->description ?? ''),
            default => '',
        };
    }

    private static function sampleValueForColumn(array $column, AssetType $type, ?Brand $brand): string
    {
        if (isset($column['parameter'])) {
            return 'sample value';
        }

        return match ($column['key']) {
            'name' => 'Sample '.$type->name.' Subtype',
            'code' => '',
            'brand' => $brand?->name ?? 'Sample Brand',
            'status' => 'Active',
            'description' => 'A sample subtype',
            default => '',
        };
    }

    /**
     * Parse an uploaded workbook into one entry per recognized sheet — a
     * sheet is matched to an Asset Type by its title, same as AssetCsv.
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
            $specWithCode = self::columnSpec($type, withCode: true);
            $mapping = self::mapHeader($data[0], $specWithCode);

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
                foreach ($specWithCode as $column) {
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
     * spec — the type is fixed by which sheet the row came from.
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
                $errors[] = "Brand \"{$brandName}\" does not exist.";
            }
        }

        $status = trim((string) ($row['status'] ?? ''));
        if ($status !== '' && ! in_array($status, ['Active', 'Inactive'], true)) {
            $errors[] = "Invalid status: {$status}";
        }

        $parameterValues = [];
        foreach ($spec as $column) {
            if (! isset($column['parameter'])) {
                continue;
            }

            $value = trim((string) ($row[$column['key']] ?? ''));

            if ($value !== '') {
                $parameterValues[$column['parameter']] = $value;
            }
        }

        $description = trim((string) ($row['description'] ?? ''));

        return [
            'errors' => $errors,
            'resolved' => [
                'asset_type_id' => $type->id,
                'brand_id' => $brand?->id,
                'status' => $status !== '' ? $status : 'Active',
                'description' => $description !== '' ? $description : null,
                'parameter_values' => $parameterValues,
            ],
        ];
    }

    private static function normalizeKey(string $label): string
    {
        return Str::of($label)->trim()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->value();
    }
}
