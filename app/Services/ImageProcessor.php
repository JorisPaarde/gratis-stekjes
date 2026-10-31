<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Veilige beeldverwerking voor aanbodfoto’s: mime+decode-check, EXIF-oriëntatie,
 * herschrijven (stript EXIF/GPS), resize. Gebruikt Imagick indien beschikbaar, anders GD.
 */
final class ImageProcessor
{
    public const DRIVER_IMAGICK = 'imagick';

    public const DRIVER_GD = 'gd';

    /** @var list<string> */
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    private readonly string $driver;

    public function __construct(?string $forceDriver = null)
    {
        if ($forceDriver !== null) {
            $this->driver = $forceDriver;

            return;
        }

        $this->driver = extension_loaded('imagick') ? self::DRIVER_IMAGICK : self::DRIVER_GD;
        if ($this->driver === self::DRIVER_GD && ! extension_loaded('gd')) {
            throw new RuntimeException('Geen beeldverwerking beschikbaar (GD noch Imagick).');
        }
    }

    public function driver(): string
    {
        return $this->driver;
    }

    /**
     * @return array{
     *   full: string,
     *   thumb: string,
     *   mime: string,
     *   width: int,
     *   height: int,
     *   bytes: int,
     *   extension: string
     * }
     */
    public function processUpload(
        UploadedFile $file,
        int $maxBytes,
        int $maxInputDimension,
        int $maxOutputDimension,
        int $thumbDimension,
    ): array {
        if (! $file->isValid()) {
            throw new RuntimeException('Upload mislukt.');
        }

        $size = (int) $file->getSize();
        if ($size <= 0 || $size > $maxBytes) {
            throw new RuntimeException('Bestand is te groot of leeg.');
        }

        $realPath = $file->getRealPath();
        if ($realPath === false || ! is_readable($realPath)) {
            throw new RuntimeException('Bestand niet leesbaar.');
        }

        $this->assertSafeImage($realPath, $maxInputDimension);

        return $this->driver === self::DRIVER_IMAGICK
            ? $this->processWithImagick($realPath, $maxOutputDimension, $thumbDimension)
            : $this->processWithGd($realPath, $maxOutputDimension, $thumbDimension);
    }

    /**
     * Verwerk een lokaal pad (tests / scripts) op dezelfde veilige manier.
     *
     * @return array{full: string, thumb: string, mime: string, width: int, height: int, bytes: int, extension: string}
     */
    public function processPath(
        string $path,
        int $maxOutputDimension = 1600,
        int $thumbDimension = 400,
        int $maxInputDimension = 10000,
    ): array {
        $this->assertSafeImage($path, $maxInputDimension);

        return $this->driver === self::DRIVER_IMAGICK
            ? $this->processWithImagick($path, $maxOutputDimension, $thumbDimension)
            : $this->processWithGd($path, $maxOutputDimension, $thumbDimension);
    }

    public function assertSafeImage(string $path, int $maxInputDimension): void
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('Bestand niet gevonden.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($path);
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new RuntimeException('Alleen JPEG, PNG of WebP toegestaan.');
        }

        $info = @getimagesize($path);
        if ($info === false || empty($info[0]) || empty($info[1])) {
            throw new RuntimeException('Geen geldige afbeelding.');
        }

        $w = (int) $info[0];
        $h = (int) $info[1];
        if ($w < 1 || $h < 1 || $w > $maxInputDimension || $h > $maxInputDimension) {
            throw new RuntimeException('Afmetingen niet toegestaan.');
        }

        // Decode echt: polyglots / corrupte blobs falen hier.
        if ($this->driver === self::DRIVER_IMAGICK) {
            try {
                $im = new \Imagick($path);
                $im->setIteratorIndex(0);
                $im->pingImage($path);
                $im->readImage($path);
                if ($im->getNumberImages() > 1) {
                    // Geen multi-frame (GIF/animatie) — we staan alleen jpeg/png/webp toe,
                    // maar weiger expliciet extra frames.
                    $im->clear();
                    $im->destroy();
                    throw new RuntimeException('Meerdere frames niet toegestaan.');
                }
                $im->clear();
                $im->destroy();
            } catch (\Throwable $e) {
                if ($e instanceof RuntimeException) {
                    throw $e;
                }
                throw new RuntimeException('Afbeelding kon niet worden gelezen.');
            }

            return;
        }

        $decoded = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };
        if ($decoded === false) {
            throw new RuntimeException('Afbeelding kon niet worden gelezen.');
        }
        imagedestroy($decoded);
    }

    /**
     * @return array{full: string, thumb: string, mime: string, width: int, height: int, bytes: int, extension: string}
     */
    private function processWithGd(string $path, int $maxOutput, int $thumbSize): array
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($path);

        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };
        if ($src === false) {
            throw new RuntimeException('Afbeelding kon niet worden gelezen.');
        }

        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $orientation = (int) (@exif_read_data($path)['Orientation'] ?? 1);
            $src = $this->applyGdOrientation($src, $orientation);
        }

        $src = $this->gdResizeMax($src, $maxOutput);
        $full = $this->gdEncodeJpeg($src);
        $w = imagesx($src);
        $h = imagesy($src);

        $thumbSrc = $this->gdResizeMax($src, $thumbSize);
        // gdResizeMax kan dezelfde resource teruggeven; kopieer voor thumb-encode.
        if ($thumbSrc === $src) {
            $thumbCopy = imagecreatetruecolor($w, $h);
            if ($thumbCopy === false) {
                imagedestroy($src);
                throw new RuntimeException('Thumbnail mislukt.');
            }
            imagecopy($thumbCopy, $src, 0, 0, 0, 0, $w, $h);
            $thumb = $this->gdEncodeJpeg($thumbCopy);
            imagedestroy($thumbCopy);
        } else {
            $thumb = $this->gdEncodeJpeg($thumbSrc);
            imagedestroy($thumbSrc);
        }
        imagedestroy($src);

        return [
            'full' => $full,
            'thumb' => $thumb,
            'mime' => 'image/jpeg',
            'width' => $w,
            'height' => $h,
            'bytes' => strlen($full),
            'extension' => 'jpg',
        ];
    }

    /**
     * @return array{full: string, thumb: string, mime: string, width: int, height: int, bytes: int, extension: string}
     */
    private function processWithImagick(string $path, int $maxOutput, int $thumbSize): array
    {
        try {
            $im = new \Imagick($path);
            $im->setIteratorIndex(0);
            // Oriëntatie toepassen vóór strippen.
            if (method_exists($im, 'autoOrientImage')) {
                $im->autoOrientImage();
            } else {
                $orientation = (int) $im->getImageOrientation();
                $this->applyImagickOrientation($im, $orientation);
            }
            $im->stripImage(); // verwijdert EXIF/GPS/IPTC/XMP

            $w = $im->getImageWidth();
            $h = $im->getImageHeight();
            $scale = min(1.0, $maxOutput / max($w, $h, 1));
            if ($scale < 1.0) {
                $im->resizeImage(
                    (int) max(1, round($w * $scale)),
                    (int) max(1, round($h * $scale)),
                    \Imagick::FILTER_LANCZOS,
                    1,
                    true
                );
            }

            $im->setImageFormat('jpeg');
            $im->setImageCompressionQuality(85);
            $im->setImageBackgroundColor('white');
            $im->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
            $full = $im->getImageBlob();
            $outW = $im->getImageWidth();
            $outH = $im->getImageHeight();

            $thumb = clone $im;
            $tScale = min(1.0, $thumbSize / max($outW, $outH, 1));
            if ($tScale < 1.0) {
                $thumb->resizeImage(
                    (int) max(1, round($outW * $tScale)),
                    (int) max(1, round($outH * $tScale)),
                    \Imagick::FILTER_LANCZOS,
                    1,
                    true
                );
            }
            $thumbBlob = $thumb->getImageBlob();
            $thumb->clear();
            $thumb->destroy();
            $im->clear();
            $im->destroy();
        } catch (\Throwable $e) {
            throw new RuntimeException('Afbeelding kon niet worden verwerkt.');
        }

        return [
            'full' => $full,
            'thumb' => $thumbBlob,
            'mime' => 'image/jpeg',
            'width' => $outW,
            'height' => $outH,
            'bytes' => strlen($full),
            'extension' => 'jpg',
        ];
    }

    /** @param \GdImage $src */
    private function applyGdOrientation(\GdImage $src, int $orientation): \GdImage
    {
        return match ($orientation) {
            2 => $this->gdFlip($src, 'x'),
            3 => imagerotate($src, 180, 0) ?: $src,
            4 => $this->gdFlip($src, 'y'),
            5 => $this->gdFlip(imagerotate($src, 270, 0) ?: $src, 'x'),
            6 => imagerotate($src, 270, 0) ?: $src,
            7 => $this->gdFlip(imagerotate($src, 90, 0) ?: $src, 'x'),
            8 => imagerotate($src, 90, 0) ?: $src,
            default => $src,
        };
    }

    private function applyImagickOrientation(\Imagick $im, int $orientation): void
    {
        switch ($orientation) {
            case \Imagick::ORIENTATION_TOPRIGHT:
                $im->flopImage();
                break;
            case \Imagick::ORIENTATION_BOTTOMRIGHT:
                $im->rotateImage(new \ImagickPixel('none'), 180);
                break;
            case \Imagick::ORIENTATION_BOTTOMLEFT:
                $im->flipImage();
                break;
            case \Imagick::ORIENTATION_LEFTTOP:
                $im->flopImage();
                $im->rotateImage(new \ImagickPixel('none'), -90);
                break;
            case \Imagick::ORIENTATION_RIGHTTOP:
                $im->rotateImage(new \ImagickPixel('none'), 90);
                break;
            case \Imagick::ORIENTATION_RIGHTBOTTOM:
                $im->flopImage();
                $im->rotateImage(new \ImagickPixel('none'), 90);
                break;
            case \Imagick::ORIENTATION_LEFTBOTTOM:
                $im->rotateImage(new \ImagickPixel('none'), -90);
                break;
        }
        $im->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
    }

    /** @param \GdImage $src */
    private function gdFlip(\GdImage $src, string $axis): \GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $dst = imagecreatetruecolor($w, $h);
        if ($dst === false) {
            return $src;
        }
        if ($axis === 'x') {
            for ($x = 0; $x < $w; $x++) {
                imagecopy($dst, $src, $w - $x - 1, 0, $x, 0, 1, $h);
            }
        } else {
            for ($y = 0; $y < $h; $y++) {
                imagecopy($dst, $src, 0, $h - $y - 1, 0, $y, $w, 1);
            }
        }
        imagedestroy($src);

        return $dst;
    }

    /** @param \GdImage $src */
    private function gdResizeMax(\GdImage $src, int $max): \GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1.0, $max / max($w, $h, 1));
        if ($scale >= 1.0) {
            return $src;
        }
        $nw = (int) max(1, round($w * $scale));
        $nh = (int) max(1, round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        if ($dst === false) {
            return $src;
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);

        return $dst;
    }

    /** @param \GdImage $src */
    private function gdEncodeJpeg(\GdImage $src): string
    {
        // Truecolor JPEG: geen alpha/palette-resten; strip hiermee alle EXIF.
        ob_start();
        imagejpeg($src, null, 85);
        $blob = (string) ob_get_clean();
        if ($blob === '') {
            throw new RuntimeException('Encoderen mislukt.');
        }

        return $blob;
    }
}
