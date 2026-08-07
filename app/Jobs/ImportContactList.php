<?php

namespace App\Jobs;

use App\Models\ContactList;
use App\Services\Lists\CsvContactListImportService;
use App\Support\Notifications\AppNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportContactList implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;
    public int $tries = 2;

    public function __construct(public int $listId, public string $path, public array $columns, public string $separator, public string $delimiter) {}

    public function handle(CsvContactListImportService $importer, AppNotificationService $notifications): void
    {
        $list = ContactList::query()->find($this->listId);
        if (! $list || ! Storage::disk('local')->exists($this->path)) return;

        $list->update(['import_status' => 'processing', 'import_progress' => 5, 'import_started_at' => now(), 'import_error' => null]);
        try {
            $file = new UploadedFile(Storage::disk('local')->path($this->path), basename($this->path), 'text/csv', null, true);
            $result = $importer->import($list, $file, $this->columns, $this->separator, $this->delimiter)->toSummaryArray();
            $imported = (int) ($result['leads_imported'] ?? 0);
            $list->update(['import_status' => 'completed', 'import_progress' => 100, 'imported_rows' => $imported, 'import_completed_at' => now()]);
            $notifications->createForCompanyAudience($list->company_id, ['company_admin', 'supervisor'], 'mailing', 'success', 'Importacao concluida', "{$list->name} recebeu {$imported} leads.", route('tenant.lists.index'));
        } catch (Throwable $exception) {
            report($exception);
            $list->update(['import_status' => 'failed', 'import_progress' => 100, 'import_error' => 'Nao foi possivel concluir a importacao.', 'import_completed_at' => now()]);
            $notifications->createForCompanyAudience($list->company_id, ['company_admin', 'supervisor'], 'mailing', 'danger', 'Importacao nao concluida', "A lista {$list->name} apresentou erro no processamento.", route('tenant.lists.index'));
        } finally {
            Storage::disk('local')->delete($this->path);
        }
    }
}
