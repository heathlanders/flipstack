<?php

namespace App\Jobs;

use App\Models\Printing\PrintJob;
use App\Services\PrinterConnectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPrintJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public PrintJob $printJob
    ) {}

    public function handle(PrinterConnectionService $printerService): void
    {
        $this->printJob->update(['status' => 'processing']);

        try {
            $success = $printerService->sendToPrinter($this->printJob);

            if ($success) {
                $this->printJob->update([
                    'status' => 'completed',
                    'printed_at' => now(),
                ]);

                // Update product print count
                if ($this->printJob->product) {
                    $this->printJob->product->increment('labels_printed_count', $this->printJob->quantity);
                    $this->printJob->product->update([
                        'last_label_printed_at' => now(),
                        'last_label_printed_by' => $this->printJob->created_by,
                    ]);
                }

                Log::info("Print job {$this->printJob->id} completed successfully");
            } else {
                throw new \Exception('Printer communication failed');
            }

        } catch (\Exception $e) {
            $this->printJob->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error("Print job {$this->printJob->id} failed: ".$e->getMessage());

            // Optionally retry
            if ($this->attempts() < 3) {
                $this->release(60); // Retry in 60 seconds
            }
        }
    }
}
