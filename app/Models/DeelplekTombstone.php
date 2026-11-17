<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeelplekTombstone extends Model
{
    protected $table = 'deelplek_tombstones';

    protected $fillable = [
        'slug',
        'naam',
        'plaats',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
        ];
    }

    public static function existsForSlug(string $slug): bool
    {
        $slug = trim($slug);
        if ($slug === '') {
            return false;
        }

        return self::query()->where('slug', $slug)->exists();
    }
}
