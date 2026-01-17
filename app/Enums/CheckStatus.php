<?php

namespace App\Enums;

enum CheckStatus: string
{
    case IN_PORTFOLIO = 'InPortfolio';
    case DEPOSITED = 'Deposited';
    case DELIVERED = 'Delivered';
    case REJECTED = 'Rejected';
}