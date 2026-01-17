<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $guarded = [];

    protected $casts = [
        'salary_base' => 'decimal:2',
    ];
}