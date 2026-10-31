<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Aanbod;
use App\Models\AanbodFoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class AanbodFotoService
{
    public function __construct(private readonly ImageProcessor $processor) {}

    /**
     * @param  list<UploadedFile>  $files
     * @return list<AanbodFoto>
     */
    public function storeForAanbod(Aanbod $aanbod, array $files, ?string $altBase = null): array
    {
        $cfg = $this->config();
        $max = (int) $cfg['max_count'];
        $existing = $aanbod->fotos()->count();
        $remaining = max(0, $max - $existing);
        if ($remaining === 0) {
            throw new RuntimeException('Je hebt al het maximum aantal foto’s.');
        }

        $files = array_slice(array_values($files), 0, $remaining);
        $disk = Storage::disk($cfg['disk']);
        $created = [];
        $sort = (int) ($aanbod->fotos()->max('sort_order') ?? -1);

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $processed = $this->processor->processUpload(
                $file,
                (int) $cfg['max_bytes'],
                (int) $cfg['max_input_dimension'],
                (int) $cfg['max_output_dimension'],
                (int) $cfg['thumb_dimension'],
            );

            $dir = $this->userDir((int) $aanbod->user_id);
            $token = Str::random(40);
            $fullRel = $dir.'/'.$token.'.'.$processed['extension'];
            $thumbRel = $dir.'/'.$token.'_t.'.$processed['extension'];

            $disk->put($fullRel, $processed['full']);
            $disk->put($thumbRel, $processed['thumb']);

            $sort++;
            $alt = $altBase !== null && trim($altBase) !== ''
                ? 'Foto bij aanbod: '.trim($altBase)
                : null;

            $created[] = $aanbod->fotos()->create([
                'disk' => $cfg['disk'],
                'path' => $fullRel,
                'thumb_path' => $thumbRel,
                'mime' => $processed['mime'],
                'width' => $processed['width'],
                'height' => $processed['height'],
                'bytes' => $processed['bytes'],
                'sort_order' => $sort,
                'alt' => $alt,
            ]);
        }

        return $created;
    }

    public function deleteFoto(AanbodFoto $foto): void
    {
        $this->deleteFiles($foto);
        $foto->delete();
    }

    public function deleteAllForAanbod(Aanbod $aanbod): void
    {
        $fotos = $aanbod->fotos()->get();
        foreach ($fotos as $foto) {
            $this->deleteFiles($foto);
        }
        $aanbod->fotos()->delete();
    }

    public function deleteAllForUser(int $userId): void
    {
        $fotos = AanbodFoto::query()
            ->whereHas('aanbod', fn ($q) => $q->where('user_id', $userId))
            ->get();

        foreach ($fotos as $foto) {
            $this->deleteFiles($foto);
            $foto->delete();
        }

        // Ruim eventuele weesbestanden in de user-map op.
        $diskName = (string) $this->config()['disk'];
        $dir = $this->userDir($userId);
        $disk = Storage::disk($diskName);
        if ($disk->exists($dir)) {
            $disk->deleteDirectory($dir);
        }
    }

    public function absolutePath(AanbodFoto $foto, string $variant = 'full'): string
    {
        $rel = $variant === 'thumb' ? $foto->thumb_path : $foto->path;
        $disk = Storage::disk($foto->disk);

        return $disk->path($rel);
    }

    /** @return array<string, mixed> */
    private function config(): array
    {
        return (array) config('stekjes.aanbod_fotos', []);
    }

    private function userDir(int $userId): string
    {
        // Geen voorspelbare opeenvolgende namen; wel gegroepeerd per user voor AVG-wipe.
        return 'u'.$userId;
    }

    private function deleteFiles(AanbodFoto $foto): void
    {
        $disk = Storage::disk($foto->disk);
        foreach ([$foto->path, $foto->thumb_path] as $path) {
            if ($path !== '' && $disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }
}
