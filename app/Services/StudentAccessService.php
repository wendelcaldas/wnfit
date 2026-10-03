<?php

namespace App\Services;

use App\Models\Aluno;
use App\Models\StudentAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StudentAccessService
{
    public function suggest(string $name): string
    {
        $base = substr(Str::slug(explode(' ', trim($name))[0] ?? '', '.'), 0, 40) ?: 'aluno';
        if (strlen($base) < 3) {
            $base .= '.aluno';
        }
        $username = $base;
        $suffix = 2;
        while (StudentAccount::where('username', $username)->exists()) {
            $username = $base.$suffix++;
        }

        return $username;
    }

    public function issue(Aluno $student, ?string $username = null, bool $reset = false): array
    {
        return DB::transaction(function () use ($student, $username, $reset) {
            Aluno::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $account = StudentAccount::where('aluno_id', $student->id)->lockForUpdate()->first();
            abort_if($account && ! $reset, 422, 'Este aluno já possui acesso. Use gerar nova senha.');
            $password = Str::password(12, symbols: false);
            if ($account) {
                $account->update(['password' => $password, 'must_change_password' => true, 'credential_version' => $account->credential_version + 1]);
            } else {
                $account = StudentAccount::create(['aluno_id' => $student->id, 'username' => $username ?: $this->suggest($student->nome), 'password' => $password]);
            }

            return ['username' => $account->username, 'temporaryPassword' => $password];
        });
    }
}
