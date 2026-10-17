<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Http\Request;

class EventAccess
{
    public static function staff(Request $request, Event $event, bool $manage = false): void
    {
        $org = $request->user()?->organizacoes()->where('organizacoes.id', $event->organizacao_id)->where('ativa', true)->wherePivot('status', 'ativo')->first();
        abort_unless($org, 404);
        abort_unless(in_array($org->pivot->papel, $manage ? ['proprietario', 'administrador'] : ['proprietario', 'administrador', 'professor']), 403);
    }
}
