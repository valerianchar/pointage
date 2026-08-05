<?php

namespace App\Enums;

enum BugReportStatus: string
{
    case Sent = 'envoye';
    case Resolved = 'resolu';

    public function label(): string
    {
        return match ($this) {
            self::Sent => 'Envoyé',
            self::Resolved => 'Résolu',
        };
    }
}
