<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Veilige afbeeldingsupload: finfo + echte decode, alleen jpeg/png/webp,
 * max 8 MB, opnieuw encoden (EXIF/GPS weg), resize max ~1600px.
 */
final class SafeImageUpload
{
    public const MAX_BYTES = 8 * 1024 * 1024;

    public const MAX_EDGE = 1600;

    public const JPEG_QUALITY = 85;

    /** @var list<string> */
    private const ALLOWED_MIME = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /**
     * @return array{ok: true, path: string}|array{ok: false, error: string}
     */
    public function store(UploadedFile $file, string $directory): array
    {
        if (! $file->isValid()) {
            return ['ok' => false, 'error' => 'De upload is mislukt. Probeer een andere foto.'];
        }

        if ($file->getSize() !== false && $file->getSize() > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'De foto mag maximaal 8 MB zijn.'];
        }

        $realPath = $file->getRealPath();
        if ($realPath === false || ! is_readable($realPath)) {
            return ['ok' => false, 'error' => 'De upload is mislukt. Probeer een andere foto.'];
        }

        $mime = $this->detectMime($realPath);
        if ($mime === null || ! in_array($mime, self::ALLOWED_MIME, true)) {
            return ['ok' => false, 'error' => 'Alleen JPEG, PNG of WebP zijn toegestaan.'];
        }

        $image = $this->decode($realPath, $mime);
        if ($image === null) {
            return ['ok' => false, 'error' => 'Dit bestand lijkt geen geldige afbeelding.'];
        }

        try {
            $resized = $this->resizeMaxEdge($image, self::MAX_EDGE);
            if ($resized !== $image) {
                imagedestroy($image);
                $image = $resized;
            }

            $encoded = $this->encodeJpeg($image);
            imagedestroy($image);

            if ($encoded === null) {
                return ['ok' => false, 'error' => 'De foto kon niet worden verwerkt.'];
            }

            $relative = trim($directory, '/').'/'.Str::uuid()->toString().'.jpg';
            $written = Storage::disk('public')->put($relative, $encoded);
            if (! $written) {
                return ['ok' => false, 'error' => 'Opslaan van de foto is mislukt.'];
            }

            return ['ok' => true, 'path' => $relative];
        } catch (Throwable $e) {
            if (isset($image) && $image instanceof \GdImage) {
                imagedestroy($image);
            }
            Log::warning('SafeImageUpload mislukt: '.$e->getMessage());

            return ['ok' => false, 'error' => 'De foto kon niet worden verwerkt.'];
        }
    }

    public function delete(?string $path): void
    {
        $path = trim((string) $path);
        if ($path === '') {
            return;
        }
        try {
            Storage::disk('public')->delete($path);
        } catch (Throwable) {
            // Best-effort; AVG-wissen mag niet crashen.
        }
    }

    private function detectMime(string $path): ?string
    {
        if (! function_exists('finfo_open')) {
            return null;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return null;
        }
        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);

        return is_string($mime) ? strtolower($mime) : null;
    }

    /** @return \GdImage|null */
    private function decode(string $path, string $mime): mixed
    {
        try {
            $raw = @file_get_contents($path);
            if ($raw === false || $raw === '') {
                return null;
            }

            // Echte decode-check: imagecreatefromstring weigert geen-beeld.
            $image = @imagecreatefromstring($raw);
            if ($image === false) {
                return null;
            }

            // Extra: MIME moet matchen met wat GD herkent via getimagesize.
            $info = @getimagesizefromstring($raw);
            if ($info === false) {
                imagedestroy($image);

                return null;
            }

            $expected = match ($mime) {
                'image/jpeg' => IMAGETYPE_JPEG,
                'image/png' => IMAGETYPE_PNG,
                'image/webp' => IMAGETYPE_WEBP,
                default => null,
            };
            if ($expected === null || (int) ($info[2] ?? 0) !== $expected) {
                imagedestroy($image);

                return null;
            }

            if (! imageistruecolor($image)) {
                $true = imagecreatetruecolor(imagesx($image), imagesy($image));
                if ($true === false) {
                    imagedestroy($image);

                    return null;
                }
                $white = imagecolorallocate($true, 255, 255, 255);
                if ($white !== false) {
                    imagefilledrectangle($true, 0, 0, imagesx($true), imagesy($true), $white);
                }
                imagecopy($true, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
                imagedestroy($image);
                $image = $true;
            }

            return $image;
        } catch (Throwable) {
            return null;
        }
    }

    /** @param \GdImage $image @return \GdImage */
    private function resizeMaxEdge(mixed $image, int $maxEdge): mixed
    {
        $w = imagesx($image);
        $h = imagesy($image);
        if ($w < 1 || $h < 1) {
            return $image;
        }
        $longest = max($w, $h);
        if ($longest <= $maxEdge) {
            return $image;
        }

        $scale = $maxEdge / $longest;
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        if ($dst === false) {
            return $image;
        }
        imagecopyresampled($dst, $image, 0, 0, 0, 0, $nw, $nh, $w, $h);

        return $dst;
    }

    /** @param \GdImage $image */
    private function encodeJpeg(mixed $image): ?string
    {
        ob_start();
        $ok = imagejpeg($image, null, self::JPEG_QUALITY);
        $data = ob_get_clean();
        if (! $ok || ! is_string($data) || $data === '') {
            return null;
        }

        return $data;
    }
}
