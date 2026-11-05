<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Services\ConversionTracker;
use App\Support\FormGuard;
use App\Support\Naam;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as IlluminateValidator;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $foundOptions = array_keys((array) config('conversion.found_via_options', []));

        return [
            'voornaam' => ['required', 'string', 'max:80', 'not_regex:/@/', function (string $attr, mixed $value, \Closure $fail): void {
                if (is_string($value) && Naam::lijktOpEmail($value, is_string($this->input('email')) ? $this->input('email') : null)) {
                    $fail(Naam::MELDING_EMAIL);

                    return;
                }
                if (is_string($value) && Naam::bezet($value)) {
                    $fail(Naam::melding($value));
                }
            }],
            'email' => ['required', 'string', 'email:filter', 'max:254'],
            // min:10 i.p.v. Password::min zodat password.min-bericht altijd meekomt naast confirmed.
            'password' => ['required', 'string', 'confirmed', 'min:10', 'max:200'],
            'gratis_akkoord' => ['accepted'],
            'privacy' => ['accepted'],
            'struikrover' => ['nullable', 'boolean'],
            'stekchallenge' => ['nullable', 'boolean'],
            'website' => ['nullable', 'string', 'max:200'],
            'form_started' => ['required', 'integer'],
            // Optioneel en los van de rest — blijft makkelijk meegenomen in 0.76.0.
            'acquisition_found_via' => ['nullable', 'string', Rule::in($foundOptions)],
            'acquisition_found_via_other' => ['nullable', 'string', 'max:120'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'voornaam.required' => 'Vul een voornaam of bijnaam in.',
            'voornaam.not_regex' => 'Gebruik een voornaam of bijnaam, geen e-mailadres. Die zien anderen.',
            'email.required' => 'Vul een geldig e-mailadres in.',
            'email.email' => 'Vul een geldig e-mailadres in.',
            'password.required' => 'Kies een wachtwoord.',
            'password.confirmed' => 'De wachtwoorden komen niet overeen.',
            'password.min' => 'Kies een wachtwoord van minstens 10 tekens.',
            'gratis_akkoord.accepted' => 'Je moet akkoord gaan: alleen gratis, geen verkoop.',
            'privacy.accepted' => 'Je moet akkoord gaan met de privacyverklaring.',
        ];
    }

    public function withValidator(IlluminateValidator $validator): void
    {
        $validator->after(function (IlluminateValidator $validator): void {
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
            $found = (string) $this->input('acquisition_found_via', '');
            $other = trim((string) $this->input('acquisition_found_via_other', ''));
            if ($found === 'anders' && $other === '') {
                $validator->errors()->add('acquisition_found_via_other', 'Vertel kort hoe je ons gevonden hebt.');
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        if (! FormGuard::honeypotFilled($this->input('website'))) {
            try {
                $this->attributes->set('conversion_failed_validator', $validator);
                app(ConversionTracker::class)->recordValidationErrors($this, $validator->errors());
            } catch (\Throwable) {
                // Meting mag validatie nooit blokkeren.
            }
        }

        throw new HttpResponseException(
            redirect()
                ->to($this->getRedirectUrl())
                ->withInput($this->except($this->dontFlash))
                ->withErrors($validator->errors(), $this->errorBag)
        );
    }

    public function isBot(): bool
    {
        return FormGuard::honeypotFilled($this->input('website'));
    }
}
