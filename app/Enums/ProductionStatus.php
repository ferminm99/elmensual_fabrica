<?php

namespace App\Enums;

enum ProductionStatus: string
{
    case PLANNED = 'Planned';
    case CUTTING = 'Cutting';
    case WORKSHOP = 'Workshop';
    case FINISHED = 'Finished';
}