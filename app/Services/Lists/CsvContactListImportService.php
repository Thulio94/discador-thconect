<?php

namespace App\Services\Lists;

use App\Models\BlacklistNumber;
use App\Models\ContactList;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use SplFileObject;

class CsvContactListImportService
{
    public function import(
        ContactList $list,
        UploadedFile $file,
        array $columns,
        string $separator = ';',
        string $delimiter = '"'
    ): CsvContactListImportResult {
        $selectedColumns = $this->selectedColumns($columns);
        $phoneColumns = array_filter($selectedColumns, fn (array $column): bool => $column['type'] === 'phone');

        if ($phoneColumns === []) {
            throw ValidationException::withMessages([
                'import_columns' => 'Selecione pelo menos uma coluna de telefone.',
            ]);
        }

        $blockedPhones = BlacklistNumber::query()
            ->where('company_id', $list->company_id)
            ->where('status', 'active')
            ->pluck('phone')
            ->map(fn (string $phone): string => $this->normalizePhone($phone))
            ->all();

        $leadsImported = 0;
        $phonesImported = 0;
        $blocked = 0;
        $invalid = 0;
        $duplicated = 0;

        $csv = new SplFileObject($file->getRealPath());
        $csv->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
        $csv->setCsvControl($separator[0] ?? ';', $delimiter[0] ?? '"');
        $csv->fgetcsv();

        DB::transaction(function () use (
            $csv,
            $list,
            $selectedColumns,
            $blockedPhones,
            &$leadsImported,
            &$phonesImported,
            &$blocked,
            &$invalid,
            &$duplicated
        ): void {
            while (! $csv->eof()) {
                $row = $csv->fgetcsv();

                if (! is_array($row) || $row === [null] || $this->isEmptyRow($row)) {
                    continue;
                }

                $extra = [];
                $phones = [];
                $rowPhones = [];

                foreach ($selectedColumns as $column) {
                    $value = trim((string) ($row[$column['index']] ?? ''));

                    if ($column['type'] === 'extra') {
                        if ($value !== '') {
                            $extra[$column['name']] = $value;
                        }

                        continue;
                    }

                    if ($value === '') {
                        continue;
                    }

                    $phone = $this->normalizePhone($value);

                    if (strlen($phone) < 10) {
                        $invalid++;

                        continue;
                    }

                    if (in_array($phone, $blockedPhones, true)) {
                        $blocked++;

                        continue;
                    }

                    if (isset($rowPhones[$phone])) {
                        $duplicated++;

                        continue;
                    }

                    $rowPhones[$phone] = true;
                    $phones[] = [
                        'phone' => $phone,
                        'column_name' => $column['name'],
                    ];
                }

                if ($phones === []) {
                    continue;
                }

                $contact = $list->contacts()->create([
                    'company_id' => $list->company_id,
                    'name' => $extra['Nome'] ?? $extra['nome'] ?? null,
                    'phone' => $phones[0]['phone'],
                    'status' => 'pending',
                    'extra' => $extra === [] ? null : $extra,
                ]);

                foreach ($phones as $position => $phone) {
                    $contact->phones()->create([
                        'company_id' => $list->company_id,
                        'phone' => $phone['phone'],
                        'column_name' => $phone['column_name'],
                        'position' => $position,
                        'is_primary' => $position === 0,
                        'status' => 'pending',
                    ]);
                }

                $leadsImported++;
                $phonesImported += count($phones);
            }
        });

        return new CsvContactListImportResult(
            $leadsImported,
            $phonesImported,
            $blocked,
            $invalid,
            $duplicated,
        );
    }

    private function selectedColumns(array $columns): array
    {
        return collect($columns)
            ->filter(fn (array $column): bool => (bool) ($column['enabled'] ?? false))
            ->map(fn (array $column): array => [
                'index' => (int) ($column['index'] ?? -1),
                'name' => trim((string) ($column['name'] ?? '')),
                'type' => (string) ($column['type'] ?? ''),
            ])
            ->filter(fn (array $column): bool => $column['index'] >= 0
                && $column['name'] !== ''
                && in_array($column['type'], ['extra', 'phone'], true))
            ->values()
            ->all();
    }

    private function isEmptyRow(array $row): bool
    {
        return collect($row)->every(fn ($value): bool => trim((string) $value) === '');
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?: '';
    }
}
