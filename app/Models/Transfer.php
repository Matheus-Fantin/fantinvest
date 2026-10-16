<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Transferência entre contas ou pagamento de fatura (quando card_id está preenchido). Não é receita nem gasto. */
class Transfer extends Model
{
    protected $fillable = ['user_id', 'de_account_id', 'para_account_id', 'card_id', 'fatura', 'data', 'valor', 'descricao'];

    protected function casts(): array
    {
        return ['data' => 'date', 'valor' => 'float'];
    }

    public function de(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'de_account_id');
    }

    public function para(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'para_account_id');
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }
}
