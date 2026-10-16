<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NetWorthSnapshot extends Model
{
    protected $fillable = ['user_id', 'mes', 'ativos', 'dividas'];

    protected function casts(): array
    {
        return ['ativos' => 'float', 'dividas' => 'float'];
    }
}
