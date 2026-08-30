<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateReportExport;
use App\Models\Call;
use App\Models\ReportExport;
use App\Services\Capacity\ResourceLimitService;
use App\Services\Recordings\GoogleDriveRecordingBackup;
use App\Services\Reports\ReportDataService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

        return $this->enqueueExport($request, $reports, $limits, $data['report_type'], $data['format']);
    }

    public function download(Request $request, ReportExport $reportExport): StreamedResponse
    {
        abort_unless($reportExport->company_id === $request->user()->company_id, 404);
        abort_if($reportExport->status !== 'completed' || ! $reportExport->file_path || ($reportExport->expires_at && $reportExport->expires_at->isPast()), 404);
        abort_unless(Storage::disk('local')->exists($reportExport->file_path), 404);

        return Storage::disk('local')->download($reportExport->file_path, sprintf('%s-%s.%s', $reportExport->report_type, $reportExport->id, $reportExport->format));
    }

    public function recording(Request $request, Call $call, GoogleDriveRecordingBackup $drive): StreamedResponse
    {
        abort_unless($call->company_id === $request->user()->company_id, 404);
        if ($call->recording_disk === 'google_drive') {
            abort_if(blank($call->recording_path), 404);
            $stream = $drive->download($call->recording_path);

            return response()->stream(function () use ($stream): void {
                while (! $stream->eof()) {
                    echo $stream->read(8192);
                }
            }, 200, [
                'Content-Type' => 'audio/'.($call->recording_format ?: 'wav'),
                'Content-Disposition' => 'inline',
            ]);
        }
        $disk = $call->recording_disk ?: 'local';
        abort_unless($call->recording_path && Storage::disk($disk)->exists($call->recording_path), 404);

        return Storage::disk($disk)->response($call->recording_path, null, [
            'Content-Type' => 'audio/'.($call->recording_format ?: 'wav'),
            'Content-Disposition' => 'inline',
        ]);
    }

    /** Legacy links are kept only to redirect callers into the protected export queue. */
    public function export(Request $request, ReportDataService $reports, ResourceLimitService $limits): RedirectResponse
    {
        return $this->enqueueExport($request, $reports, $limits, 'calls', 'csv');
    }

    public function exportXlsx(Request $request, ReportDataService $reports, ResourceLimitService $limits): RedirectResponse
    {
        return $this->enqueueExport($request, $reports, $limits, 'calls', 'xlsx');
    }

    public function exportPdf(Request $request, ReportDataService $reports, ResourceLimitService $limits): RedirectResponse
    {
        return $this->enqueueExport($request, $reports, $limits, 'calls', 'pdf');
    }

    private function enqueueExport(Request $request, ReportDataService $reports, ResourceLimitService $limits, string $reportType, string $format): RedirectResponse
    {
        $limits->assertExport((int) $request->user()->company_id, $format);
        $this->validateFilters($request);
        $filters = $reports->filters($request->all(), (int) $request->user()->company_id);
        unset($filters['company_id']);
        $export = ReportExport::create([
            'company_id' => $request->user()->company_id,
            'user_id' => $request->user()->id,
            'report_type' => $reportType,
            'format' => $format,
            'status' => 'queued',
            'progress' => 0,
            'filters' => $this->serializableFilters($filters),
            'records_count' => 0,
        ]);
        GenerateReportExport::dispatch($export->id)->onQueue('reports');

        return redirect()->route('tenant.reports.index', ['report' => 'exports'])
            ->with('status', 'Exportação enviada para a fila. O arquivo ficará disponível quando o processamento terminar.');
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
                throw ValidationException::withMessages(['date_to' => "A consulta interativa permite no maximo {$maxDays} dias."]);
            }
        }
    }

    private function serializableFilters(array $filters): array
    {
        return collect($filters)->map(fn ($value) => $value instanceof \DateTimeInterface ? $value->toDateString() : $value)->filter(fn ($value) => $value !== null && $value !== '')->all();
    }
}
