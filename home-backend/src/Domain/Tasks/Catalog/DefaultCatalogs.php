<?php

declare(strict_types=1);

namespace App\Domain\Tasks\Catalog;

// Entries a household starts with, matching the lists the frontend used to hard-code.
final class DefaultCatalogs
{
    /** @var array<string, string> name => color */
    public const CATEGORIES = [
        'Cleaning' => '#C4623A',
        'Laundry' => '#9B7DB5',
        'Pets' => '#C4963A',
        'Garden' => '#6B7C4E',
        'Shopping' => '#3A8FB5',
        'Waste' => '#7C5E3A',
        'Bills' => '#B53A3A',
        'Kitchen' => '#3A7C8F',
    ];

    /** @var array<string, int> name => interval in days */
    public const RECURRENCE_TYPES = [
        'Daily' => 1,
        'Mon & Thu' => 3,
        'Tue & Fri' => 3,
        'Weekly' => 7,
        'Biweekly' => 14,
        'Monthly' => 30,
    ];
}
