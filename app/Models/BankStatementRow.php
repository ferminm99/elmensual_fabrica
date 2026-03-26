<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankStatementRow extends Model
{
    protected $guarded = [];
    
    protected $casts = [
        'date' => 'date',
    ];

    public function statement()
    {
        return $this->belongsTo(BankStatement::class, 'bank_statement_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}