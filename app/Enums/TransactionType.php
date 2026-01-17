<?php

namespace App\Enums;

enum TransactionType: string
{
    case INCOME = 'Income';
    case OUTCOME = 'Outcome';
}