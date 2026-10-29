<?php

namespace App\Http\Controllers;

use App\Models\StaffNotification;
use App\Services\WorkoutNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffNotificationController extends Controller
{
    private function inbox(Request $request, bool $syncDeadlines = false): Builder
    {
        $organization = $request->user()->organizacoes()->where('organizacoes.ativa', true)->wherePivot('status', 'ativo')->firstOrFail();
        if ($syncDeadlines) {
            app(WorkoutNotificationService::class)->syncDeadlines($organization->id);
        }

        return StaffNotification::where('organizacao_id', $organization->id)->where('user_id', $request->user()->id);
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['before' => ['nullable', 'integer', 'min:1']]);
        $query = $this->inbox($request, true);
        $unreadCount = (clone $query)->whereNull('read_at')->count();
        $items = $query->when($data['before'] ?? null, fn ($q, $id) => $q->where('id', '<', $id))->latest('id')->limit(21)->get();
        $hasMore = $items->count() > 20;
        $items = $items->take(20);

        return response()->json([
            'notifications' => $items->map->payload()->values(), 'unreadCount' => $unreadCount,
            'nextCursor' => $hasMore ? $items->last()->id : null,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function read(Request $request, int $notification): JsonResponse
    {
        $query = $this->inbox($request);
        $item = (clone $query)->whereKey($notification)->firstOrFail();
        (clone $query)->whereKey($notification)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json([
            'notification' => $item->fresh()->payload(),
            'unreadCount' => (clone $query)->whereNull('read_at')->count(),
        ])->header('Cache-Control', 'private, no-store');
    }
}
