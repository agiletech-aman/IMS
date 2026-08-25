<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Shared Excel export behaviour for centre-scoped reports: a single sheet
 * when a specific centre is selected, or one sheet per centre (plus an
 * "Unassigned" sheet for legacy rows without a centre) when viewing the
 * compiled (all centres) data.
 */
trait ExportsCentreSplitExcel
{
    private function centreSplitSpreadsheet(
        array $headings,
        Collection $records,
        ?string $selectedCentre,
        callable $centreOf,
        callable $rowMapper,
    ): Spreadsheet {
        $groups = $selectedCentre !== null
            ? [$this->centreLabel($selectedCentre) => $records]
            : $records->groupBy(fn ($record) => $this->centreLabel($centreOf($record)))
                ->sortKeys()
                ->all();

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        foreach ($groups as $title => $rows) {
            $this->writeCentreSheet($spreadsheet, (string) $title, $headings, $rows, $rowMapper);
        }

        return $spreadsheet;
    }

    private function writeCentreSheet(
        Spreadsheet $spreadsheet,
        string $title,
        array $headings,
        iterable $rows,
        callable $rowMapper,
    ): void {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle(Str::substr($title, 0, 31));

        foreach ($headings as $index => $heading) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1).'1', $heading);
        }

        $rowNumber = 2;

        foreach ($rows as $row) {
            foreach ($rowMapper($row) as $index => $value) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1).$rowNumber, $value);
            }

            $rowNumber++;
        }

        $lastColumn = Coordinate::stringFromColumnIndex(count($headings));
        $lastRow = max($rowNumber - 1, 1);

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
    }

    private function centreLabel(?string $centre): string
    {
        return match ($centre) {
            'noida' => 'Noida',
            'lucknow' => 'Lucknow',
            default => 'Unassigned',
        };
    }
}
