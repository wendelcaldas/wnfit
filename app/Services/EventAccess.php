<?php

namespace App\Services;

use App\Models\Event;
use App\Models\StudentAccount;
use Illuminate\Http\Request;

class EventAccess
{
    public static function isPilot(StudentAccount $account): bool
    {
        $pilot = trim((string) config('events.pilot_username'));

        return app()->environment('production') && $pilot !== '' && $account->username === $pilot;
    }

    public static function modulesAvailable(StudentAccount $account, Event $event): bool
    {
        if (self::isPilot($account)) {
            return true;
        }

        if (! $event->starts_at) {
            return false;
        }

        return today(config('app.timezone'))->gte($event->starts_at->copy()->timezone(config('app.timezone'))->startOfDay());
    }

    public static function ensureModulesAvailable(StudentAccount $account, Event $event): void
    {
        abort_unless(self::modulesAvailable($account, $event), 422, 'Este recurso estará disponível no dia do evento.');
    }

    public static function staff(Request $request, Event $event, bool $manage = false): void
    {
        $org = $request->user()?->organizacoes()->where('organizacoes.id', $event->organizacao_id)->where('ativa', true)->wherePivot('status', 'ativo')->first();
        abort_unless($org, 404);
        abort_unless(in_array($org->pivot->papel, $manage ? ['proprietario', 'administrador'] : ['proprietario', 'administrador', 'professor']), 403);
    }
}
