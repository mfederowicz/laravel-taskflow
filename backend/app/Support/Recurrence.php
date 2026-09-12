<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class Recurrence
{
    public static function frequencies(): array
    {
        return ['daily', 'weekly', 'monthly', 'yearly'];
    }

    public static function isValidFrequency(?string $frequency): bool
    {
        return in_array($frequency, self::frequencies(), true);
    }

    public static function advanceDate(string $frequency, string $dueDate): string
    {
        $date = CarbonImmutable::parse($dueDate);

        return (match ($frequency) {
            'daily' => $date->addDay(),
            'weekly' => $date->addWeek(),
            'monthly' => $date->addMonth(),
            'yearly' => $date->addYear(),
            default => $date,
        })->toDateString();
    }
}
