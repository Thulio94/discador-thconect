<?php

namespace App\Services\Lists;

final readonly class CsvContactListImportResult
{
    public function __construct(
        public int $leadsImported,
        public int $phonesImported,
        public int $blocked,
        public int $invalid,
        public int $duplicated,
    ) {}

    public function toSummaryArray(): array
    {
        return [
            'leads_imported' => $this->leadsImported,
            'phones_imported' => $this->phonesImported,
            'blocked' => $this->blocked,
            'invalid' => $this->invalid,
            'duplicated' => $this->duplicated,
        ];
    }
}
