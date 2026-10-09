<?php

namespace App\Enums;

enum ActivityLogName: string
{
    case User = 'User';
    case Role = 'Role';
    case ScheduledJob = 'Scheduled Job';
    case EmailTemplate = 'Email Template';
    case Company = 'Company';
}
