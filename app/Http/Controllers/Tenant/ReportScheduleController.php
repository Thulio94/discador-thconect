<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\ReportSchedule;
use App\Services\Reports\SimulatedReportScheduleRunner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReportScheduleController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $companyId = $request->user()->company_id;
        $data = $request->validate($this->rules($companyId));

        ReportSchedule::create($this->attributes($data, $companyId, $request->user()->id));

        return redirect()
            ->route('tenant.reports.index', ['report' => 'exports', 'tab' => 'schedules'])
            ->with('status', 'Agendamento criado com sucesso.');
    }

    public function update(Request $request, ReportSchedule $reportSchedule): RedirectResponse
    {
        $this->authorizeTenant($request, $reportSchedule);
        $data = $request->validate($this->rules($reportSchedule->company_id));

        $reportSchedule->update($this->attributes($data, $reportSchedule->company_id, $reportSchedule->user_id));

        return redirect()
            ->route('tenant.reports.index', ['report' => 'exports', 'tab' => 'schedules'])
            ->with('status', 'Agendamento atualizado com sucesso.');
    }

    public function toggleStatus(Request $request, ReportSchedule $reportSchedule): RedirectResponse
    {
        $this->authorizeTenant($request, $reportSchedule);
        $status = $reportSchedule->status === 'active' ? 'inactive' : 'active';
        $reportSchedule->update(['status' => $status]);

        return redirect()
            ->route('tenant.reports.index', ['report' => 'exports', 'tab' => 'schedules'])
            ->with('status', $status === 'active'
                ? 'Agendamento ativado com sucesso.'
                : 'Agendamento inativado com sucesso.');
    }

    public function destroy(Request $request, ReportSchedule $reportSchedule): RedirectResponse
    {
        $this->authorizeTenant($request, $reportSchedule);
        $reportSchedule->delete();

        return redirect()
            ->route('tenant.reports.index', ['report' => 'exports', 'tab' => 'schedules'])
            ->with('status', 'Agendamento excluido com sucesso.');
    }

    public function run(
        Request $request,
        ReportSchedule $reportSchedule,
        SimulatedReportScheduleRunner $runner
    ): RedirectResponse {
        $this->authorizeTenant($request, $reportSchedule);

        if ($reportSchedule->status !== 'active') {
            throw ValidationException::withMessages([
                'schedule' => 'Ative o agendamento antes de executar.',
            ]);
        }

        $recordsCount = $runner->run($reportSchedule, $request->user());

        return redirect()
            ->route('tenant.reports.index', ['report' => 'exports', 'tab' => 'schedules'])
            ->with('status', sprintf('Agendamento executado. %d registros encontrados.', $recordsCount));
    }

    private function authorizeTenant(Request $request, ReportSchedule $reportSchedule): void
    {
        abort_unless($reportSchedule->company_id === $request->user()->company_id, 404);
    }

    private function rules(int $companyId): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'report_type' => ['nullable', Rule::in(['calls', 'agents', 'campaigns', 'lists', 'qualifications', 'teams', 'utilization', 'callbacks'])],
            'format' => ['required', Rule::in(['csv', 'xlsx', 'pdf'])],
            'frequency' => ['required', Rule::in(['daily', 'weekly'])],
            'weekday' => ['nullable', 'required_if:frequency,weekly', 'integer', 'between:0,6'],
            'run_time' => ['required', 'date_format:H:i'],
            'period_days' => ['required', 'integer', Rule::in([7, 15, 30, 60, 90])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'user_id' => ['nullable', Rule::exists('users', 'id')->where('company_id', $companyId)],
            'campaign_id' => ['nullable', Rule::exists('campaigns', 'id')->where('company_id', $companyId)],
            'disposition_id' => ['nullable', Rule::exists('dispositions', 'id')->where('company_id', $companyId)],
            'team_id' => ['nullable', Rule::exists('teams', 'id')->where('company_id', $companyId)],
            'list_id' => ['nullable', Rule::exists('contact_lists', 'id')->where('company_id', $companyId)],
        ];
    }

    private function attributes(array $data, int $companyId, int $userId): array
    {
        return [
            'company_id' => $companyId,
            'user_id' => $userId,
            'report_type' => $data['report_type'] ?? 'calls',
            'name' => $data['name'],
            'format' => $data['format'],
            'frequency' => $data['frequency'],
            'weekday' => $data['frequency'] === 'weekly' ? $data['weekday'] : null,
            'run_time' => $data['run_time'],
            'filters' => array_filter([
                'period_days' => $data['period_days'],
                'user_id' => $data['user_id'] ?? null,
                'campaign_id' => $data['campaign_id'] ?? null,
                'disposition_id' => $data['disposition_id'] ?? null,
                'team_id' => $data['team_id'] ?? null,
                'list_id' => $data['list_id'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''),
            'status' => $data['status'],
        ];
    }
}
