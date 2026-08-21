<?php

namespace App\Support;

use App\Models\Faculty;
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
        'department',
        'fb_type',
        'room_number',
        'remark',
        'status',
    ];

    public const IMPORT_COLUMNS = [
        'name',
        'email',
        'contact',
        'address',
        'department',
        'fb_type',
        'room_number',
        'remark',
        'status',
    ];

    public const IMPORT_HEADINGS = [
        'Name',
        'Email',
        'Contact',
        'Address',
        'Department',
        'FB Type',
        'Room Number',
        'Remark',
        'Status',
    ];

    public const INTERNAL_TEAM_IMPORT_HEADINGS = [
        'Name',
        'Email',
        'Contact',
        'Address',
        'Role',
        'Status',
    ];

    public const INTERNAL_TEAM_COLUMNS = ['name', 'email', 'contact', 'address', 'role', 'status'];

    public static function exportRows(): array
    {
        $rows = [];

        foreach (Faculty::orderBy('id')->get() as $user) {
            $rows[] = [
                'unique_id' => $user->unique_id,
                'name' => $user->name,
                'email' => $user->email,
                'contact' => $user->contact ?? '',
                'address' => $user->address ?? '',
                'department' => $user->department?->name ?? '',
                'fb_type' => $user->fb_type ?? '',
                'room_number' => $user->room_number ?? '',
                'remark' => $user->remark ?? '',
                'status' => $user->status ?? '',
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

    public static function exportStyledInternalTeamSample(string $filePath): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Internal Team');

        foreach (self::INTERNAL_TEAM_IMPORT_HEADINGS as $index => $heading) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1).'1', $heading);
        }

        $lastColumn = Coordinate::stringFromColumnIndex(count(self::INTERNAL_TEAM_IMPORT_HEADINGS));
        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
            'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
        ]);

        for ($column = 1; $column <= count(self::INTERNAL_TEAM_IMPORT_HEADINGS); $column++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
        }

        $sheet->getRowDimension(1)->setRowHeight(24);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}1");

        (new Xlsx($spreadsheet))->save($filePath);
    }

    public static function parseFile(string $path, bool $internalTeam = false): array
    {
        $extension = Str::lower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($extension, ['csv', 'txt'], true)) {
            return self::parseCsv($path, $internalTeam);
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

        $columns = $internalTeam ? self::INTERNAL_TEAM_COLUMNS : self::COLUMNS;
        $required = $internalTeam ? self::INTERNAL_TEAM_COLUMNS : self::IMPORT_COLUMNS;
        $mapping = self::columnMapping($data[0], $columns, $required);
        if ($mapping['errors'] !== []) {
            return ['rows' => [], 'errors' => $mapping['errors']];
        }

        $rows = [];
        foreach (array_slice($data, 1, null, true) as $zeroBasedRow => $values) {
            if (collect($values)->every(fn ($value) => trim((string) $value) === '')) {
                continue;
            }

            $row = [];
            foreach ($columns as $column) {
                $index = $mapping['columns'][$column] ?? null;
                $row[$column] = $index === null ? '' : trim((string) ($values[$index] ?? ''));
            }
            $rows[$zeroBasedRow + 1] = $row;
        }

        return ['rows' => $rows, 'errors' => []];
    }

    public static function parseCsv(string $path, bool $internalTeam = false): array
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

        $columns = $internalTeam ? self::INTERNAL_TEAM_COLUMNS : self::COLUMNS;
        $required = $internalTeam ? self::INTERNAL_TEAM_COLUMNS : self::IMPORT_COLUMNS;
        $mapping = self::columnMapping($header, $columns, $required);
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
            foreach ($columns as $col) {
                $idx = $mapping['columns'][$col] ?? null;
                $row[$col] = $idx === null ? '' : (string) ($data[$idx] ?? '');
            }

            $rows[$rowIndex] = $row;
            $rowIndex++;
        }

        fclose($handle);

        return ['rows' => $rows, 'errors' => $errors];
    }

    private static function columnMapping(array $headings, array $allowedColumns, array $requiredColumns): array
    {
        $aliases = [
            'user_name' => 'name',
            'full_name' => 'name',
            'phone' => 'contact',
            'phone_number' => 'contact',
            'mobile' => 'contact',
            'mobile_number' => 'contact',
            'dept' => 'department',
            'deptt' => 'department',
            'department_name' => 'department',
            'new_old_fb' => 'fb_type',
            'new_old_feedback' => 'fb_type',
            'feedback_type' => 'fb_type',
            'room_no' => 'room_number',
            'room' => 'room_number',
            'remarks' => 'remark',
        ];
        $columns = [];
        foreach ($headings as $index => $heading) {
            $normalized = Str::of((string) $heading)
                ->trim()
                ->lower()
                ->replaceMatches('/[^a-z0-9]+/', '_')
                ->trim('_')
                ->value();
            $normalized = $aliases[$normalized] ?? $normalized;

            if (in_array($normalized, $allowedColumns, true)) {
                $columns[$normalized] = $index;
            }
        }

        $missing = array_values(array_diff($requiredColumns, array_keys($columns)));

        return [
            'columns' => $columns,
            'errors' => $missing === []
                ? []
                : ['Import file missing columns: '.implode(', ', $missing)],
];
    }
}
