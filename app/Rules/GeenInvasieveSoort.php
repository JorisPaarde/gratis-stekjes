<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\InvasieveSoorten;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class GeenInvasieveSoort implements ValidationRule
{
    public function __construct(
        private readonly ?InvasieveSoorten $checker = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $checker = $this->checker ?? app(InvasieveSoorten::class);
        $message = $checker->blockMessage($value, route('invasieve-soorten'));
        if ($message !== null) {
            $fail($message);
        }
    }
}
