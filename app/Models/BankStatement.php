<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankStatement extends Model
{
    protected $guarded = [];

    public function account()
    {
        return $this->belongsTo(CompanyAccount::class, 'company_account_id');
    }

    public function rows()
    {
        return $this->hasMany(BankStatementRow::class);
    }
}