<?php

namespace App\Reports;

/** A finished report: a title, the filters used, a table, and a few summary lines. */
class Report
{
    /**
     * @param  array<string, string>  $filters  label => text, shown under the title
     * @param  string[]  $headings
     * @param  array<int, array<int, string|int|float|null>>  $rows
     * @param  array<string, string|int|float>  $summary  label => value
     */
    public function __construct(
        public string $slug,
        public string $title,
        public array $filters,
        public array $headings,
        public array $rows,
        public array $summary = [],
        public string $orientation = 'portrait',
        public ?string $note = null,
    ) {
    }

    /** Columns whose values are all numbers or percentages (aligned to the right). */
    public function numericColumns(): array
    {
        $numeric = [];

        foreach ($this->headings as $index => $heading) {
            $values = array_filter(array_column($this->rows, $index), fn ($value) => $value !== null && $value !== '' && $value !== '—');

            if ($values !== [] && count(array_filter($values, fn ($v) => is_numeric($v) || preg_match('/^-?\d+(\.\d+)?%$/', (string) $v))) === count($values)) {
                $numeric[] = $index;
            }
        }

        return $numeric;
    }
}
