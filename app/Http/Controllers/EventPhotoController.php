<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventPhoto;
use App\Services\EventAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EventPhotoController extends Controller
{
    private function authorizeAlbum(Request $request, Event $event)
    {
        if ($request->is('api/student/*')) {
            $r = app(StudentEventController::class)->participant($event);
            abort_unless($event->moduleSettings()['album']['enabled'], 404);

            return $r;
        }
        EventAccess::staff($request, $event);

        return null;
    }

    private function payload(Request $request, EventPhoto $photo): array
    {
        $base = ($request->is('api/student/*') ? '/api/student/events/' : '/api/events/').$photo->event_id.'/photos/'.$photo->id;

        return ['id' => $photo->id, 'status' => $photo->status, 'thumbnailUrl' => $base.'/file?thumbnail=1', 'url' => $base.'/file', 'createdAt' => $photo->created_at];
    }

    public function index(Request $request, Event $event)
    {
        $r = $this->authorizeAlbum($request, $event);

        return EventPhoto::where('event_id', $event->id)->when($r, fn ($q) => $q->where(fn ($q) => $q->where('status', 'approved')->orWhere('event_registration_id', $r->id)))->orderByDesc('id')->paginate(12)->through(fn ($p) => $this->payload($request, $p));
    }

    public function upload(Request $request, Event $event)
    {
        $r = app(StudentEventController::class)->participant($event);
        abort_unless($event->moduleSettings()['album']['enabled'], 404);
        abort_if(app()->environment('production') && ! config('events.photo_storage_persistent'), 503, 'O álbum aguarda configuração de armazenamento permanente pela equipe.');
        $request->validate(['photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:8192|dimensions:max_width=4096,max_height=4096', 'consent' => 'accepted']);
        abort_unless(function_exists('imagecreatefromstring'), 503, 'O envio de imagens está indisponível. Procure a equipe.');
        $file = $request->file('photo');
        $source = @imagecreatefromstring($file->get());
        abort_unless($source, 422, 'Não foi possível ler esta foto. Use JPG, PNG ou WebP.');
        if ($file->getMimeType() === 'image/jpeg' && function_exists('exif_read_data')) {
            $orientation = (@exif_read_data($file->getRealPath()) ?: [])['Orientation'] ?? 1;
            if (in_array($orientation, [2, 4, 5, 7])) {
                imageflip($source, in_array($orientation, [2, 5, 7]) ? IMG_FLIP_HORIZONTAL : IMG_FLIP_VERTICAL);
            }
            $angle = match ($orientation) {
                3 => 180,5,8 => 90,6,7 => -90,default => 0
            };
            if ($angle) {
                $rotated = imagerotate($source, $angle, 0);
                imagedestroy($source);
                $source = $rotated;
            }
        }
        $disk = Storage::disk(config('events.photo_disk'));
        $name = 'event-photos/'.$event->organizacao_id.'/'.$event->id.'/'.Str::uuid();
        $paths = [];
        try {
            foreach (['path' => 1920, 'thumbnail_path' => 480] as $key => $limit) {
                $ratio = min(1, $limit / max(imagesx($source), imagesy($source)));
                $image = imagecreatetruecolor(max(1, (int) (imagesx($source) * $ratio)), max(1, (int) (imagesy($source) * $ratio)));
                imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
                imagecopyresampled($image, $source, 0, 0, 0, 0, imagesx($image), imagesy($image), imagesx($source), imagesy($source));
                ob_start();
                imagejpeg($image, null, 85);
                $bytes = ob_get_clean();
                imagedestroy($image);
                $paths[$key] = $name.'-'.$limit.'.jpg';
                if (! $disk->put($paths[$key], $bytes, ['visibility' => 'private'])) {
                    throw new \RuntimeException('Falha ao salvar a imagem.');
                }
            }
            $photo = EventPhoto::create(['event_id' => $event->id, 'event_registration_id' => $r->id, 'disk' => config('events.photo_disk'), ...$paths, 'image_consent_at' => now()]);
        } catch (\Throwable $e) {
            $disk->delete(array_values($paths));
            throw $e;
        } finally {
            imagedestroy($source);
        }

        return response()->json(['photo' => $this->payload($request, $photo)], 201);
    }

    public function file(Request $request, Event $event, EventPhoto $photo)
    {
        $r = $this->authorizeAlbum($request, $event);
        abort_unless($photo->event_id === $event->id, 404);
        abort_if($r && $photo->status !== 'approved' && $photo->event_registration_id !== $r->id, 404);
        abort_if($photo->status === 'removed', 404);
        $path = $request->boolean('thumbnail') ? $photo->thumbnail_path : $photo->path;
        $disk = Storage::disk($photo->disk);
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Content-Type' => 'image/jpeg']);
    }

    public function moderate(Request $request, Event $event, EventPhoto $photo)
    {
        EventAccess::staff($request, $event);
        abort_unless($photo->event_id === $event->id, 404);
        $data = $request->validate(['status' => 'required|in:approved,removed']);
        $photo->update(['status' => $data['status'], 'moderated_by' => $request->user()->id, 'moderated_at' => now()]);

        return response()->json(['photo' => $this->payload($request,$photo)]);
    }
}
