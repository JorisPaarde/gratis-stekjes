<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Support\FormGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:filter', 'max:254'],
            'password' => ['required', 'string', 'max:200'],
            'remember' => ['nullable', 'boolean'],
            'website' => ['nullable', 'string', 'max:200'],
            'form_started' => ['required', 'integer'],
            'next' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (FormGuard::honeypotFilled($this->input('website'))) {
                return;
            }
            if (FormGuard::submittedTooFast($this->input('form_started'))) {
                $validator->errors()->add('form_started', 'Even geduld, het formulier ging te snel.');
            }
        });
    }

    public function isBot(): bool
    {
        return FormGuard::honeypotFilled($this->input('website'));
    }

    public function safeNext(): string
    {
        $next = (string) $this->input('next', '/account');
        if ($next === '' || ! str_starts_with($next, '/') || str_starts_with($next, '//')) {
            return '/account';
        }

        return $next;
    }
}
