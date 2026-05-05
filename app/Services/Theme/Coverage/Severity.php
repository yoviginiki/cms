<?php

namespace App\Services\Theme\Coverage;

enum Severity: string
{
    case Critical = 'critical';
    case Warning = 'warning';
    case Info = 'info';
}
