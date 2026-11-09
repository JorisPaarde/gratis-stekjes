<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Services\ConversionTracker;
use App\Support\Country;
use App\Support\FormGuard;
use App\Support\PostalCode;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as IlluminateValidator;

class RegisterRequest extends FormRequest
{
    /** @var string Altijd dit formulier — nooit previous()/back() naar / zonder melding. */
    protected $redirectRoute = 'account.registreren';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $foundOptions = array_keys((array) config('conversion.found_via_options', []));

        return [
            'email' => ['required', 'string', 'email:filter', 'max:254'],
            // Geen password confirmation; wel min 10. Toon/verberg in de view.
            'password' => ['required', 'string', 'min:10', 'max:200'],
            'country' => ['required', 'string', Rule::in(Country::codes())],
            'postcode' => ['required', 'string', 'max:10'],
            'gratis_akkoord' => ['accepted'],
            'privacy' => ['accepted'],
            ...FormGuard::honeypotRules(),
            'form_started' => ['required', 'integer'],
            'next' => ['nullable', 'string', 'max:200'],
            'acquisition_found_via' => ['nullable', 'string', Rule::in($foundOptions)],
            'acquisition_found_via_other' => ['nullable', 'string', 'max:120'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.required' => 'Vul een geldig e-mailadres in.',
            'email.email' => 'Vul een geldig e-mailadres in.',
            'password.required' => 'Kies een wachtwoord.',
            'password.min' => 'Kies een wachtwoord van minstens 10 tekens.',
            'country.required' => 'Kies een land.',
            'country.in' => 'Kies Nederland of België.',
            'postcode.required' => 'Vul je postcode in.',
            'gratis_akkoord.accepted' => 'Je moet akkoord gaan: alleen gratis, geen verkoop.',
            'privacy.accepted' => 'Je moet akkoord gaan met de privacyverklaring.',
            'form_started' => 'Dat ging erg snel. Klik nog een keer op Account maken.',
        ];
    }

    public function withValidator(IlluminateValidator $validator): void
    {
        $validator->after(function (IlluminateValidator $validator): void {
            if (FormGuard::honeypotFilledIn($this)) {
                return;
            }
            if (FormGuard::submittedTooFast($this->input('form_started'))) {
                FormGuard::logBotRejected('register', 'too_fast');
                $validator->errors()->add(
                    'form_started',
                    'Dat ging erg snel. Klik nog een keer op Account maken.'
                );
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

            $country = Country::normalize((string) $this->input('country', Country::NL));
            $postcode = trim((string) $this->input('postcode', ''));
            if ($postcode === '') {
                return;
            }
            if (! PostalCode::looksLikePostcode($postcode, $country)) {
                $validator->errors()->add(
                    'postcode',
                    $country === Country::BE
                        ? 'Belgische postcode bestaat uit precies 4 cijfers (bijv. 9000).'
                        : 'Nederlandse postcode: 4 cijfers, optioneel gevolgd door 2 letters (bijv. 1234 AB).'
                );

                return;
            }
            $pcValid = PostalCode::validate($postcode, $country);
            if ($pcValid !== true) {
                $validator->errors()->add('postcode', is_string($pcValid) ? $pcValid : 'Deze postcode ken ik niet.');
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        if (! FormGuard::honeypotFilledIn($this)) {
            try {
                $this->attributes->set('conversion_failed_validator', $validator);
                app(ConversionTracker::class)->recordValidationErrors($this, $validator->errors());
            } catch (\Throwable) {
                // Meting mag validatie nooit blokkeren.
            }
        }

        // Geen wachtwoord terug; form_started komt opnieuw uit de honeypot-partial (time()).
        // Expliciet route i.p.v. previous(): ontbrekende Referer stuurde anders naar / en
        // consumeerde de flash vóór het formulier opnieuw zichtbaar was.
        // Vangnet ?fout=snel: file-sessies zonder lock kunnen flash verliezen bij parallelle
        // requests (staging lsphp); query-param toont de melding ook zonder flash.
        $except = array_values(array_unique(array_merge(
            ['current_password', 'password', 'password_confirmation', 'form_started', '_token'],
            FormGuard::HONEYPOT_FIELDS,
        )));

        $query = [];
        if ($validator->errors()->has('form_started')) {
            $query['fout'] = 'snel';
            // Alleen bij te-snel: e-mail ook in query (sessie-flash kan wegvallen).
            $email = strtolower(trim((string) $this->input('email', '')));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $query['email'] = $email;
            }
        }

        throw new HttpResponseException(
            redirect()
                ->route('account.registreren', $query)
                ->withInput($this->except($except))
                ->withErrors($validator->errors(), $this->errorBag)
        );
    }

    public function isBot(): bool
    {
        return FormGuard::honeypotFilledIn($this);
    }
}
