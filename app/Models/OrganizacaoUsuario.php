<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class OrganizacaoUsuario extends Pivot
{
    protected $table = 'organizacao_usuario';

    public $incrementing = true;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organizacao_id',
        'user_id',
        'papel',
        'status',
    ];
}
