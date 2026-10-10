<?php

namespace App\Mail;

use App\Models\Analytics\ScheduledReport;
use App\Services\Analytics\ReportExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ScheduledReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ScheduledReport $report) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Scheduled Report — {$this->report->name}");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.scheduled-report',
            with: ['report' => $this->report],
        );
    }

    public function attachments(): array
    {
        $filters = $this->report->filters ?? [
            'start_date' => now()->subDays(29)->toDateString(),
            'end_date' => now()->toDateString(),
        ];

        // Render the export to a temp file and attach it.
        $exporter = app(ReportExportService::class);
        $rows = $exporter->fetchRows($this->report->view, (string) $this->report->organization_id, $filters);

        if (empty($rows)) {
            return [];
        }

        $tmp = tempnam(sys_get_temp_dir(), 'rpt_');
        $columns = \App\Services\Analytics\ReportRegistry::get($this->report->view)['columns'] ?? [];
        $fp = fopen($tmp, 'w');
        fputcsv($fp, array_values($columns));
        foreach ($rows as $row) {
            $line = [];
            foreach (array_keys($columns) as $key) {
                $val = is_array($row) ? ($row[$key] ?? '') : ($row->$key ?? '');
                $line[] = is_scalar($val) || $val === null ? $val : json_encode($val);
            }
            fputcsv($fp, $line);
        }
        fclose($fp);

        return [
            Attachment::fromPath($tmp)->as("{$this->report->view}.csv")->withMime('text/csv'),
        ];
    }
}
