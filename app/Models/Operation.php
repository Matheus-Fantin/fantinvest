<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Operation extends Model
{
    protected $fillable = ['user_id', 'asset_id', 'tipo', 'data', 'quantidade', 'preco', 'taxas', 'resultado', 'account_id'];

    protected function casts(): array
    {
        return ['data' => 'date', 'quantidade' => 'float', 'preco' => 'float', 'taxas' => 'float', 'resultado' => 'float'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function total(): float
    {
        return $this->quantidade * $this->preco;
    }
}
