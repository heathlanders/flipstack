<?php

namespace App\Services\Analytics;

use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportService
{
    /**
     * Render a registered analytics view as a downloadable file.
     */
    public function export(string $view, string $format, string $orgId, array $filters): Response
    {
        $def = ReportRegistry::get($view);
        if (! $def) {
            abort(404, "Unknown report view: {$view}");
        }

        $rows = $def['fetch']($orgId, $filters);
        $columns = $def['columns'];
        $label = $def['label'];

        $filename = $this->filename($view, $format, $filters);

        return match ($format) {
            'csv' => $this->csv($filename, $columns, $rows),
            'xlsx' => $this->xlsx($filename, $label, $columns, $rows),
            'pdf' => $this->pdf($filename, $label, $columns, $rows, $filters),
            default => abort(400, "Unsupported format: {$format}"),
        };
    }

    /**
     * Get the row data only (used by scheduled email senders).
     */
    public function fetchRows(string $view, string $orgId, array $filters): array
    {
        $def = ReportRegistry::get($view);
        if (! $def) {
            return [];
        }

        return $def['fetch']($orgId, $filters);
    }

    private function filename(string $view, string $format, array $filters): string
    {
        $start = $filters['start_date'] ?? 'all';
        $end = $filters['end_date'] ?? 'all';

        return "{$view}_{$start}_to_{$end}.{$format}";
    }

    private function csv(string $filename, array $columns, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($columns, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_values($columns));
            foreach ($rows as $row) {
                $line = [];
                foreach (array_keys($columns) as $key) {
                    $line[] = $this->scalar($row[$key] ?? '');
                }
                fputcsv($out, $line);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function xlsx(string $filename, string $label, array $columns, array $rows): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $export = new class($label, $columns, $rows) implements FromArray, WithHeadings, WithTitle
        {
            public function __construct(
                private readonly string $sheetTitle,
                private readonly array $columns,
                private readonly array $rows
            ) {}

            public function array(): array
            {
                $out = [];
                foreach ($this->rows as $row) {
                    $line = [];
                    foreach (array_keys($this->columns) as $key) {
                        $val = is_array($row) ? ($row[$key] ?? '') : ($row->$key ?? '');
                        $line[] = is_scalar($val) || $val === null ? $val : json_encode($val);
                    }
                    $out[] = $line;
                }

                return $out;
            }

            public function headings(): array
            {
                return array_values($this->columns);
            }

            public function title(): string
            {
                return mb_substr($this->sheetTitle, 0, 30);
            }
        };

        return Excel::download($export, $filename);
    }

    private function pdf(string $filename, string $label, array $columns, array $rows, array $filters): Response
    {
        $pdf = Pdf::loadView('reports.export', [
            'label' => $label,
            'columns' => $columns,
            'rows' => $rows,
            'filters' => $filters,
        ])->setPaper('letter', 'landscape');

        return $pdf->download($filename);
    }

    private function scalar($v): string
    {
        if (is_scalar($v) || $v === null) {
            return (string) $v;
        }

        return json_encode($v);
    }
}
