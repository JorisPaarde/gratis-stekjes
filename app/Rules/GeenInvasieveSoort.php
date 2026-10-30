<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\InvasieveSoorten;
use App\Services\Plantensoorten;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

final class GeenInvasieveSoort implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(
        private readonly ?InvasieveSoorten $checker = null,
        private readonly ?Plantensoorten $plantensoorten = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $plantensoorten = $this->plantensoorten ?? app(Plantensoorten::class);
        $id = $this->resolvePlantensoortId($plantensoorten, $value);
        $checkText = $plantensoorten->textForInvasieveCheck($value, $id);

        $checker = $this->checker ?? app(InvasieveSoorten::class);
        $message = $checker->blockMessage($checkText, route('invasieve-soorten'));
        if ($message !== null) {
            $fail($message);
        }
    }

    private function resolvePlantensoortId(Plantensoorten $plantensoorten, string $tekst): ?int
    {
        $raw = $this->data['plantensoort_id'] ?? null;
        if ($raw !== null && $raw !== '') {
            $id = (int) $raw;
            if ($id > 0 && $plantensoorten->exists($id)) {
                return $id;
            }
        }

        $exact = $plantensoorten->resolveExact($tekst);

        return $exact['id'] ?? null;
    }
}
