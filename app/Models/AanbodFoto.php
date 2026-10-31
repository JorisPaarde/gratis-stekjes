<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AanbodFoto extends Model
{
    protected $table = 'aanbod_fotos';

    protected $fillable = [
        'aanbod_id',
        'disk',
        'path',
        'thumb_path',
        'mime',
        'width',
        'height',
        'bytes',
        'sort_order',
        'alt',
    ];

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'bytes' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function aanbod(): BelongsTo
    {
        return $this->belongsTo(Aanbod::class, 'aanbod_id');
    }

    public function publicUrl(): string
    {
        return route('media.aanbod-foto', ['foto' => $this->id, 'variant' => 'full']);
    }

    public function thumbUrl(): string
    {
        return route('media.aanbod-foto', ['foto' => $this->id, 'variant' => 'thumb']);
    }

    /** @return array{id: int, url: string, thumb_url: string, alt: string, width: int, height: int} */
    public function toPublicArray(): array
    {
        $alt = trim((string) ($this->alt ?? ''));
        if ($alt === '') {
            $tekst = trim((string) ($this->aanbod?->tekst ?? ''));
            $alt = $tekst !== '' ? 'Foto bij aanbod: '.$tekst : 'Foto bij gratis planten-aanbod';
        }

        return [
            'id' => (int) $this->id,
            'url' => $this->publicUrl(),
            'thumb_url' => $this->thumbUrl(),
            'alt' => $alt,
            'width' => (int) $this->width,
            'height' => (int) $this->height,
        ];
    }
}
