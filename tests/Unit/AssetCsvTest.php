<?php

namespace Tests\Unit;

use App\Support\AssetCsv;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\TestCase;

class AssetCsvTest extends TestCase
{
    public function test_import_csv_does_not_require_asset_tag(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'asset_csv_');
        $handle = fopen($path, 'wb');
        fputcsv($handle, AssetCsv::IMPORT_COLUMNS);
        fputcsv($handle, array_replace(array_fill(0, count(AssetCsv::IMPORT_COLUMNS), ''), [
            0 => 'Test Laptop',
            1 => 'Hardware',
            2 => 'Laptop',
            12 => 'Active',
        ]));
        fclose($handle);

        $parsed = AssetCsv::parseCsv($path);
        unlink($path);

        $this->assertSame([], $parsed['errors']);
        $this->assertSame('', $parsed['rows'][2]['asset_tag']);
        $this->assertSame('Test Laptop', $parsed['rows'][2]['name']);
    }

    public function test_export_columns_still_include_asset_tag(): void
    {
        $this->assertContains('asset_tag', AssetCsv::COLUMNS);
        $this->assertNotContains('asset_tag', AssetCsv::IMPORT_COLUMNS);
    }

    public function test_styled_excel_sample_excludes_asset_tag_and_can_be_parsed(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'asset_sample_').'.xlsx';
        AssetCsv::exportStyledImportSample($path);

        $sheet = IOFactory::load($path)->getActiveSheet();
        $headers = $sheet->rangeToArray('A1:'.$sheet->getHighestColumn().'1')[0];
        $parsed = AssetCsv::parseFile($path);
        unlink($path);

        $this->assertSame(AssetCsv::IMPORT_HEADINGS, $headers);
        $this->assertNotContains('asset_tag', $headers);
        $this->assertContains('Sub Department', $headers);
        $this->assertSame([], $parsed['errors']);
        $this->assertSame([], $parsed['rows']);
        $this->assertSame('1F4E78', $sheet->getStyle('A1')->getFill()->getStartColor()->getRGB());
    }
}
