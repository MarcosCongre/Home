<?php

declare(strict_types=1);

namespace App\Domain\Alerts;

enum AlertStatus: string
{
    case UNREAD = 'unread';
    case DISMISSED = 'dismissed';
}
