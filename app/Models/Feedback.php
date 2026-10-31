<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSafeEncryptedAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    use HasSafeEncryptedAttributes;

    protected $table = 'feedback';

    protected $fillable = [
        'user_id',
        'page_path',
        'body',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'body' => \App\Casts\SafeEncrypted::class,
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
