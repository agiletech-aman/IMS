<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class UserCsv
{
    public const COLUMNS = [
        'unique_id',
        'name',
        'email',
        'contact',
        'address',
        'status',
        'role',
        'login_enabled',
    ];

    public const IMPORT_COLUMNS = [
        'name',
        'email',
        'contact',
        'address',
    ];

    public const IMPORT_HEADINGS = [
        'Name',
        'Email',
        'Contact',
        'Address',
    ];

    public static function exportRows(): array
    {
        $rows = [];

        foreach (User::orderBy('id')->get() as $user) {
            $rows[] = [
                'unique_id' => $user->unique_id,
                'name' => $user->name,
                'email' => $user->email,
                'contact' => $user->contact ?? '',
                'address' => $user->address ?? '',
                'status' => $user->status ?? '',
                'role' => $user->login_enabled ? $user->role : '',
                'login_enabled' => $user->login_enabled ? 'Yes' : 'No',
            ];
        }

        return $rows;
    }

    public static function exportStyledExcel(string $filePath): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Users');

        foreach (self::COLUMNS as $index => $heading) {
            $columnLetter = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue($columnLetter.'1', strtoupper(str_replace('_', ' ', $heading)));
        }

        $rowNumber = 2;
        foreach (self::exportRows() as $row) {
            foreach (self::COLUMNS as $index => $column) {
                $columnLetter = Coordinate::stringFromColumnIndex($index + 1);
                $sheet->setCellValue($columnLetter.$rowNumber, $row[$column] ?? '');
            }
            $rowNumber++;
        }

        $lastColumn = $sheet->getHighestColumn();
        $lastRow = $sheet->getHighestRow();

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

        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}1");

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);
    }

    public static function exportStyledImportSample(string $filePath): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('User Import');

        foreach (self::IMPORT_HEADINGS as $index => $heading) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1).'1', $heading);
        }

        $lastColumn = Coordinate::stringFromColumnIndex(count(self::IMPORT_HEADINGS));
        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
            'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
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

        if (! $handle) {
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
}
