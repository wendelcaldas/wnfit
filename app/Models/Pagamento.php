<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pagamento extends Model
{
    protected $fillable = [
        'cobranca_id',
        'valor',
        'metodo',
        'pago_em',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'pago_em' => 'datetime',
        ];
    }

    public function cobranca(): BelongsTo
    {
        return $this->belongsTo(Cobranca::class);
    }
}
