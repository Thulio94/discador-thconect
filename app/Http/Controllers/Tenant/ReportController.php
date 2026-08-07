<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateReportExport;
use App\Models\Call;
use App\Models\ReportExport;
use App\Services\Reports\ReportDataService;
use App\Services\Reports\SimplePdfReport;
use App\Services\Capacity\ResourceLimitService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request, ReportDataService $reports): View|RedirectResponse
    {
        $catalog = $reports->catalog();
        $selectedReport = (string) $request->query('report', '');
        if ($selectedReport === 'schedules') {
            return redirect()->route('tenant.reports.index', ['report' => 'exports', 'tab' => 'schedules']);
        }
        if ($selectedReport === '' && $request->query()) {
            $selectedReport = 'calls';
        }
        if ($selectedReport !== '' && ! isset($catalog[$selectedReport])) {
            abort(404);
        }
        if ($selectedReport === '') {
            return view('tenant.reports.index', ['reportCatalog' => $catalog, 'selectedReport' => null]);
        }

        $this->validateFilters($request);
        $filters = $reports->filters($request->all(), (int) $request->user()->company_id);

        return view('tenant.reports.index', [
            'reportCatalog' => $catalog,
            'selectedReport' => $selectedReport,
            'filters' => $filters,
            'options' => $reports->options($filters['company_id']),
            'reportData' => $reports->data($selectedReport, $filters),
            'reports' => $reports,
        ]);
    }

    public function queueExport(Request $request, ReportDataService $reports, ResourceLimitService $limits): RedirectResponse
    {
        $catalog = $reports->catalog();
        $data = $request->validate([
            'report_type' => ['required', Rule::in(array_keys($catalog))],
            'format' => ['required', Rule::in(['csv', 'xlsx', 'pdf'])],
        ]);
        $limits->assertExport((int) $request->user()->company_id, $data['format']);
        $this->validateFilters($request);
        $filters = $reports->filters($request->all(), (int) $request->user()->company_id);
        unset($filters['company_id']);
        $export = ReportExport::create([
            'company_id' => $request->user()->company_id,
            'user_id' => $request->user()->id,
            'report_type' => $data['report_type'],
            'format' => $data['format'],
            'status' => 'queued',
            'progress' => 0,
            'filters' => $this->serializableFilters($filters),
            'records_count' => 0,
        ]);
        GenerateReportExport::dispatch($export->id)->onQueue('reports');

        return redirect()->route('tenant.reports.index', ['report' => 'exports'])
            ->with('status', 'Exportação enviada para a fila. O arquivo ficará disponível quando o processamento terminar.');
    }

    public function download(Request $request, ReportExport $reportExport): StreamedResponse
    {
        abort_unless($reportExport->company_id === $request->user()->company_id, 404);
        abort_if($reportExport->status !== 'completed' || ! $reportExport->file_path || ($reportExport->expires_at && $reportExport->expires_at->isPast()), 404);
        abort_unless(Storage::disk('local')->exists($reportExport->file_path), 404);

        return Storage::disk('local')->download($reportExport->file_path, sprintf('%s-%s.%s', $reportExport->report_type, $reportExport->id, $reportExport->format));
    }

    public function recording(Request $request, Call $call): StreamedResponse
    {
        abort_unless($call->company_id === $request->user()->company_id, 404);
        $disk = $call->recording_disk ?: 'local';
        abort_unless($call->recording_path && Storage::disk($disk)->exists($call->recording_path), 404);

        return Storage::disk($disk)->response($call->recording_path, null, [
            'Content-Type' => 'audio/'.($call->recording_format ?: 'wav'),
            'Content-Disposition' => 'inline',
        ]);
    }

    public function export(Request $request, ReportDataService $reports): StreamedResponse
    {
        return $this->legacyDownload($request, $reports, 'csv');
    }

    public function exportXlsx(Request $request, ReportDataService $reports): StreamedResponse
    {
        return $this->legacyDownload($request, $reports, 'xlsx');
    }

    public function exportPdf(Request $request, ReportDataService $reports, SimplePdfReport $pdf): StreamedResponse
    {
        $this->validateFilters($request);
        $filters = $reports->filters($request->all(), (int) $request->user()->company_id);
        [$headings, $rows] = $reports->exportRows('calls', $filters);
        ReportExport::create([
            'company_id' => $request->user()->company_id,
            'user_id' => $request->user()->id,
            'report_type' => 'calls',
            'format' => 'pdf',
            'status' => 'completed',
            'progress' => 100,
            'filters' => $this->serializableFilters($filters),
            'records_count' => count($rows),
            'completed_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
        $lines = ['Relatório de Ligações', implode(' | ', $headings)];
        foreach (array_slice($rows, 0, 100) as $row) {
            $lines[] = implode(' | ', $row);
        }

        return response()->streamDownload(fn () => print ($pdf->render($lines)), 'relatorios-chamadas.pdf', ['Content-Type' => 'application/pdf']);
    }

    private function legacyDownload(Request $request, ReportDataService $reports, string $format): StreamedResponse
    {
        $this->validateFilters($request);
        $filters = $reports->filters($request->all(), (int) $request->user()->company_id);
        [$headings, $rows] = $reports->exportRows('calls', $filters);
        ReportExport::create([
            'company_id' => $request->user()->company_id,
            'user_id' => $request->user()->id,
            'report_type' => 'calls',
            'format' => $format,
            'status' => 'completed',
            'progress' => 100,
            'filters' => $this->serializableFilters($filters),
            'records_count' => count($rows),
            'completed_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
        if ($format === 'xlsx') {
            return response()->streamDownload(function () use ($headings, $rows): void {
                $sheet = (new Spreadsheet)->getActiveSheet();
                $sheet->fromArray($headings, null, 'A1');
                $sheet->fromArray($rows, null, 'A2');
                (new Xlsx($sheet->getParent()))->save('php://output');
            }, 'relatorios-chamadas.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
        }

        return response()->streamDownload(function () use ($headings, $rows): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headings);
            foreach ($rows as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
        }, 'relatorios-chamadas.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function validateFilters(Request $request): void
    {
        $request->validate([
            'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'user_id' => ['nullable', 'integer'], 'campaign_id' => ['nullable', 'integer'], 'team_id' => ['nullable', 'integer'], 'list_id' => ['nullable', 'integer'], 'disposition_id' => ['nullable', 'integer'], 'telephony_route_id' => ['nullable', 'integer'],
            'phone' => ['nullable', 'string', 'max:30'], 'status' => ['nullable', 'string', 'max:30'], 'hangup_party' => ['nullable', Rule::in(['agent', 'customer', 'carrier', 'system', 'unknown'])], 'amd_result' => ['nullable', 'string', 'max:40'], 'direction' => ['nullable', Rule::in(['outbound', 'inbound'])], 'callback_status' => ['nullable', Rule::in(['pending', 'completed', 'cancelled'])],
        ]);
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $maxDays = app(ResourceLimitService::class)->limits((int) $request->user()->company_id)->max_report_range_days;
            if (now()->parse($request->string('date_from'))->diffInDays(now()->parse($request->string('date_to'))) > $maxDays) {
                throw \Illuminate\Validation\ValidationException::withMessages(['date_to' => "A consulta interativa permite no maximo {$maxDays} dias."]);
            }
        }
    }

    private function serializableFilters(array $filters): array
    {
        return collect($filters)->map(fn ($value) => $value instanceof \DateTimeInterface ? $value->toDateString() : $value)->filter(fn ($value) => $value !== null && $value !== '')->all();
    }
}
