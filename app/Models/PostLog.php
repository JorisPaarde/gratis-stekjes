<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PostLog extends Model
{
    protected $fillable = [
        'posted_on',
        'titel',
        'groep',
        'groep_slug',
        'soort',
        'link',
        'utm_source',
        'notitie',
    ];

    protected function casts(): array
    {
        return [
            'posted_on' => 'date',
        ];
    }

    public static function slugForGroep(string $groep): string
    {
        $slug = Str::slug(trim($groep));

        return $slug !== '' ? Str::limit($slug, 140, '') : 'groep';
    }

    public function trackingUrl(?string $base = null): string
    {
        $baseUrl = rtrim($base ?: (string) config('app.url'), '/');
        $query = http_build_query([
            'utm_source' => $this->utm_source ?: 'facebook',
            'utm_medium' => 'social',
            'utm_campaign' => $this->groep_slug,
            'utm_content' => (string) $this->id,
        ]);

        return $baseUrl.'/ga/word-lid?'.$query;
    }
}
