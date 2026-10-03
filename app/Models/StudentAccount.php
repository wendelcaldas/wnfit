<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

class StudentAccount extends Authenticatable
{
    protected $fillable = ['aluno_id', 'username', 'password', 'must_change_password', 'credential_version', 'last_login_at'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'must_change_password' => 'boolean', 'credential_version' => 'integer', 'last_login_at' => 'datetime'];
    }

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class);
    }
}
