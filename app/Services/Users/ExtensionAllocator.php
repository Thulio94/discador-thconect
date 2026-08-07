<?php

namespace App\Services\Users;

use App\Models\User;
use Illuminate\Validation\ValidationException;

final class ExtensionAllocator
{
    public const MINIMUM_EXTENSION = 1000;

    public const MAXIMUM_EXTENSION = 10000;

    public function allocate(): string
    {
        $takenExtensions = User::query()
            ->lockForUpdate()
            ->whereNotNull('extension')
            ->whereRaw('CAST(extension AS UNSIGNED) BETWEEN ? AND ?', [self::MINIMUM_EXTENSION, self::MAXIMUM_EXTENSION])
            ->pluck('extension')
            ->map(fn (string $extension): int => (int) $extension)
            ->flip();

        for ($extension = self::MINIMUM_EXTENSION; $extension <= self::MAXIMUM_EXTENSION; $extension++) {
            if (! $takenExtensions->has($extension)) {
                return (string) $extension;
            }
        }

        throw ValidationException::withMessages([
            'extension' => 'Nao ha ramais disponiveis entre 1000 e 10000.',
        ]);
    }
}
