<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancingExpense extends Model
{
    protected $fillable = ['financing_id', 'data', 'descricao', 'categoria', 'fornecedor', 'valor', 'origem'];

    protected function casts(): array
    {
        return ['data' => 'date', 'valor' => 'float'];
    }

    public function financing(): BelongsTo
    {
        return $this->belongsTo(Financing::class);
    }
}
