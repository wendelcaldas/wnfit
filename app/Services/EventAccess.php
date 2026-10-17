<?php

namespace App\Services;

use App\Models\Event;
use App\Models\StudentAccount;
use Illuminate\Http\Request;

class EventAccess
{
    public static function experienceAllowed(StudentAccount $account): bool
    {
        $pilot = trim((string) config('events.pilot_username'));

        return $pilot === '' || $account->username === $pilot;
    }

    public static function staff(Request $request, Event $event, bool $manage = false): void
    {
        $org = $request->user()?->organizacoes()->where('organizacoes.id', $event->organizacao_id)->where('ativa', true)->wherePivot('status', 'ativo')->first();
        abort_unless($org, 404);
        abort_unless(in_array($org->pivot->papel, $manage ? ['proprietario', 'administrador'] : ['proprietario', 'administrador', 'professor']), 403);
    }
}
