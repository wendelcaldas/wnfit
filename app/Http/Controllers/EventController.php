<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventController extends Controller
{
    private function organization(Request $request, ?Event $event = null)
    {
        $org = $request->user()->organizacoes()->wherePivot('status', 'ativo')->firstOrFail();
        abort_unless(in_array($org->pivot->papel, ['proprietario', 'administrador']), 403);
        abort_if($event && $event->organizacao_id !== $org->id, 404);

        return $org;
    }

    public function index(Request $request)
    {
        $org = $this->organization($request);

        return Event::where('organizacao_id', $org->id)->withCount(['registrations', 'registrations as present_count' => fn ($q) => $q->whereNotNull('checked_in_at')])->latest()->get();
    }

    public function save(Request $request, ?Event $event = null)
    {
        $org = $this->organization($request, $event);
        abort_if($event?->closed_at, 422, 'O evento já foi encerrado.');
        $data = $request->validate([
            'name' => 'required|string|max:150', 'organizer' => 'required|string|max:150',
            'description' => 'nullable|string|max:3000', 'location' => 'required|string|max:255',
            'instructions' => 'nullable|string|max:3000', 'after_content' => 'nullable|string|max:3000',
            'starts_at' => 'required|date', 'ends_at' => 'required|date|after:starts_at',
            'checkin_opens_at' => 'required|date|before_or_equal:ends_at',
            'capacity' => 'nullable|integer|min:1', 'status' => 'required|in:draft,published,cancelled',
        ]);

        foreach (['starts_at', 'ends_at', 'checkin_opens_at'] as $field) {
            $data[$field] = Carbon::parse($data[$field], config('app.timezone'))->setTimezone(config('app.timezone'));
        }

        return DB::transaction(function () use ($event, $data, $org) {
            if ($event) {
                $event = Event::lockForUpdate()->findOrFail($event->id);
                abort_if($event->closed_at, 422, 'O evento já foi encerrado.');
                abort_if(($data['capacity'] ?? null) && $data['capacity'] < $event->registrations()->count(), 422, 'O limite não pode ser menor que o total de inscritos.');
                $event->update($data);
            } else {
                $event = Event::create([...$data, 'organizacao_id' => $org->id, 'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(8))]);
            }

            return response()->json($event);
        });
    }

    public function close(Request $request, Event $event)
    {
        $this->organization($request, $event);

        return DB::transaction(function () use ($event) {
            $event = Event::lockForUpdate()->findOrFail($event->id);
            abort_unless($event->status === 'published' && $event->starts_at->lte(now()), 422, 'Só é possível encerrar um evento publicado que já começou.');
            if (! $event->closed_at) {
                $event->update(['closed_at' => now()]);
            }

            return response()->json($event);
        });
    }

    public function registrations(Request $request, Event $event)
    {
        $this->organization($request, $event);
        $data = $request->validate(['q' => 'nullable|string|max:120', 'status' => 'nullable|in:present,absent']);

        return $event->registrations()->select(['id', 'name', 'phone', 'created_at', 'checked_in_at', 'attendance_source', 'marketing_opt_in', 'rating', 'feedback'])
            ->when($data['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$term.'%')->orWhere('phone', 'like', '%'.$term.'%')))
            ->when(($data['status'] ?? null) === 'present', fn ($q) => $q->whereNotNull('checked_in_at'))
            ->when(($data['status'] ?? null) === 'absent', fn ($q) => $q->whereNull('checked_in_at'))
            ->latest()->paginate(30);
    }

    public function attendance(Request $request, Event $event, EventRegistration $registration)
    {
        $this->organization($request, $event);
        abort_unless($registration->event_id === $event->id, 404);
        $data = $request->validate(['present' => 'required|boolean']);
        $registration->update(['checked_in_at' => $data['present'] ? now() : null, 'attendance_source' => $data['present'] ? 'staff' : 'staff_absent', 'attendance_updated_by' => $request->user()->id]);

        return response()->json(['ok' => true]);
    }
}
