<?php

namespace App\Exports;

use App\Reports\Report;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Any report as an Excel sheet: title block, headings, rows, summary. */
class ReportExport implements FromArray, WithColumnWidths, WithStrictNullComparison, WithStyles, WithTitle
{
    private int $prefaceRows;
    private int $headingRow;

    public function __construct(private Report $report, private string $school, private string $generatedBy)
    {
        // school, title, one line per filter, "generated", blank line
        $this->prefaceRows = 2 + count($report->filters) + 2;
        $this->headingRow = $this->prefaceRows + 1;
    }

    /** Stop Excel from running text such as "=1+1" as a formula. */
    public static function safe(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }

    public function array(): array
    {
        $rows = [[self::safe($this->school)], [self::safe($this->report->title)]];

        foreach ($this->report->filters as $label => $value) {
            $rows[] = [self::safe("{$label}: {$value}")];
        }

        $rows[] = ['Generated ' . now()->format('M j, Y g:i A') . ' by ' . self::safe($this->generatedBy)];
        $rows[] = [];
        $rows[] = array_map([self::class, 'safe'], $this->report->headings);

        foreach ($this->report->rows as $row) {
            $rows[] = array_map([self::class, 'safe'], $row);
        }

        if ($this->report->summary !== []) {
            $rows[] = [];
            foreach ($this->report->summary as $label => $value) {
                $rows[] = [self::safe((string) $label), self::safe($value)];
            }
        }

        return $rows;
    }

    /**
     * Column widths worked out from the text itself. (Excel's own auto-size asks the GD
     * extension to measure fonts, which can crash PHP on some Windows installs.)
     */
    public function columnWidths(): array
    {
        $widths = [];

        foreach ($this->report->headings as $index => $heading) {
            $longest = mb_strlen((string) $heading);

            foreach ($this->report->rows as $row) {
                $longest = max($longest, mb_strlen((string) ($row[$index] ?? '')));
            }

            $widths[Coordinate::stringFromColumnIndex($index + 1)] = min(max($longest + 3, 9), 45);
        }

        return $widths;
    }

    public function styles(Worksheet $sheet): ?array
    {
        $lastColumn = $sheet->getHighestColumn();

        // Merge the title lines so they do not stretch the first column.
        for ($row = 1; $row < $this->prefaceRows; $row++) {
            $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
        }

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);

        $range = "A{$this->headingRow}:{$lastColumn}{$this->headingRow}";
        $sheet->getStyle($range)->getFont()->setBold(true);
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E3EAF5');
        // Numbers and percentages line up on the right, heading included.
        $lastRow = $this->headingRow + max(count($this->report->rows), 1);
        foreach ($this->report->numericColumns() as $index) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->getStyle("{$column}{$this->headingRow}:{$column}{$lastRow}")
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        $sheet->freezePane('A' . ($this->headingRow + 1));

        return [];
    }

    public function title(): string
    {
        return Str::limit(trim(preg_replace('/[\\\\\/\?\*\[\]:]/', '', $this->report->title)), 31, '');
    }
}
