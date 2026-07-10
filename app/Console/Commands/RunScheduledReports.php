<?php

namespace App\Console\Commands;

use App\Services\Reports\SimulatedReportScheduleRunner;
use Illuminate\Console\Command;

class RunScheduledReports extends Command
{
    protected $signature = 'reports:run-scheduled';

    protected $description = 'Executa os agendamentos de relatorio vencidos.';

    public function handle(SimulatedReportScheduleRunner $runner): int
    {
        $executed = $runner->runDue();

        $this->info(sprintf('%d agendamento(s) de relatorio executado(s).', $executed));

        return self::SUCCESS;
    }
}
