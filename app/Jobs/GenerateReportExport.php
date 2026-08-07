<?php

namespace App\Jobs;

use App\Models\ReportExport;
use App\Services\Reports\ReportDataService;
use App\Services\Reports\SimplePdfReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;
use App\Services\Capacity\ResourceLimitService;

class GenerateReportExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(public readonly int $exportId) {}

    public function handle(ReportDataService $reports, SimplePdfReport $pdf, ?ResourceLimitService $limits = null): void
    {
        $export = ReportExport::query()->find($this->exportId);
        if (! $export || $export->status !== 'queued') {
            return;
        }
        $limits ??= app(ResourceLimitService::class);
        $export->update(['status' => 'processing', 'progress' => 15, 'started_at' => now(), 'error_message' => null]);

        try {
            $filters = $reports->filters($export->filters ?? [], $export->company_id);
            [$headings, $rows] = $reports->exportStream($export->report_type, $filters);
            $rowLimit = match ($export->format) {
                'csv' => $limits->limits($export->company_id)->max_csv_rows,
                'xlsx' => $limits->limits($export->company_id)->max_xlsx_rows,
                default => $limits->limits($export->company_id)->max_pdf_rows,
            };
            $path = sprintf('report-exports/%d/%s-%d.%s', $export->company_id, $export->report_type, $export->id, $export->format);
            $count = $this->write($path, $export->format, $headings, $rows, $pdf, $rowLimit);
            $export->update(['progress' => 80, 'records_count' => $count]);
            $export->update(['status' => 'completed', 'progress' => 100, 'file_path' => $path, 'file_size' => Storage::disk('local')->size($path), 'completed_at' => now(), 'expires_at' => now()->addDays(30)]);
        } catch (Throwable $exception) {
            report($exception);
            $export->update(['status' => 'failed', 'progress' => 100, 'error_message' => 'Não foi possível gerar este arquivo. Tente novamente ou ajuste os filtros.', 'completed_at' => now()]);
        }
    }

    private function write(string $path, string $format, array $headings, iterable $rows, SimplePdfReport $pdf, int $limit): int
    {
        Storage::disk('local')->makeDirectory(dirname($path));
        if ($format === 'csv') {
            $temporary = tempnam(sys_get_temp_dir(), 'thconect-report-');
            $stream = fopen($temporary, 'w');
            fputcsv($stream, $headings);
            $count = 0; foreach ($rows as $row) {
                if ($count >= $limit) break;
                fputcsv($stream, $row);
                $count++;
            } fclose($stream);
            $readStream = fopen($temporary, 'r');
            Storage::disk('local')->put($path, $readStream);
            fclose($readStream);
            @unlink($temporary);
            return $count;
        }
        if ($format === 'xlsx') {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->fromArray($headings, null, 'A1');
            $count = 0; foreach ($rows as $row) { if ($count >= $limit) break; $sheet->fromArray($row, null, 'A'.($count + 2)); $count++; }
            (new Xlsx($spreadsheet))->save(Storage::disk('local')->path($path));
            $spreadsheet->disconnectWorksheets();

            return $count;
        }
        $lines = [implode(' | ', $headings)];
        $count = 0; foreach ($rows as $row) { if ($count >= min($limit, 10000)) break;
            $lines[] = implode(' | ', $row);
            $count++;
        } Storage::disk('local')->put($path, $pdf->render($lines)); return $count;
    }
}
