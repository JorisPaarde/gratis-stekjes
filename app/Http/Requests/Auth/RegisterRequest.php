<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Support\FormGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'voornaam' => ['required', 'string', 'max:80'],
            'email' => ['required', 'string', 'email:filter', 'max:254'],
            // min:10 i.p.v. Password::min zodat password.min-bericht altijd meekomt naast confirmed.
            'password' => ['required', 'string', 'confirmed', 'min:10', 'max:200'],
            'gratis_akkoord' => ['accepted'],
            'privacy' => ['accepted'],
            'website' => ['nullable', 'string', 'max:200'],
            'form_started' => ['required', 'integer'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'voornaam.required' => 'Vul een voornaam of bijnaam in.',
            'email.required' => 'Vul een geldig e-mailadres in.',
            'email.email' => 'Vul een geldig e-mailadres in.',
            'password.required' => 'Kies een wachtwoord.',
            'password.confirmed' => 'De wachtwoorden komen niet overeen.',
            'password.min' => 'Kies een wachtwoord van minstens 10 tekens.',
            'gratis_akkoord.accepted' => 'Je moet akkoord gaan: alleen gratis, geen verkoop.',
            'privacy.accepted' => 'Je moet akkoord gaan met de privacyverklaring.',
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
            $email = (string) $this->input('email', '');
            if ($email !== '' && FormGuard::isDisposableEmail($email)) {
                $validator->errors()->add('email', 'Gebruik geen wegwerpmailadres.');
            }
        });
    }

    public function isBot(): bool
    {
        return FormGuard::honeypotFilled($this->input('website'));
    }
}
