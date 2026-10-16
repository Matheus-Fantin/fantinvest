<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancingPayment extends Model
{
    protected $fillable = ['financing_id', 'tipo', 'numero', 'data', 'valor', 'reduzir', 'transaction_id'];

    protected function casts(): array
    {
        return ['data' => 'date', 'valor' => 'float', 'numero' => 'integer'];
    }

    public function financing(): BelongsTo
    {
        return $this->belongsTo(Financing::class);
    }
}
