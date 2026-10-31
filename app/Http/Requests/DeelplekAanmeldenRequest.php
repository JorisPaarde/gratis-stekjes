<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\Country;
use App\Support\FormGuard;
use App\Support\PostalCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DeelplekAanmeldenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $types = array_keys(config('stekjes.deelplek_types', []));

        return [
            'naam' => ['required', 'string', 'max:160'],
            'type' => ['required', 'string', Rule::in($types)],
            'country' => ['required', 'string', Rule::in(Country::codes())],
            'adres' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:16'],
            'plaats' => ['required', 'string', 'max:120'],
            // Echte website; honeypot blijft het veld "website".
            'url' => ['nullable', 'string', 'max:500'],
            'beschrijving' => ['nullable', 'string', 'max:1000'],
            'contact_email' => ['required', 'string', 'email:filter', 'max:254'],
            'privacy' => ['accepted'],
            'website' => ['nullable', 'string', 'max:200'],
            'form_started' => ['required', 'integer'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'naam.required' => 'Vul een naam in.',
            'type.required' => 'Kies een type.',
            'type.in' => 'Kies een geldig type.',
            'country.required' => 'Kies een land.',
            'country.in' => 'Kies Nederland of België.',
            'plaats.required' => 'Vul een plaats in.',
            'contact_email.required' => 'Vul een contact-e-mailadres in.',
            'contact_email.email' => 'Vul een geldig e-mailadres in.',
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
            $email = (string) $this->input('contact_email', '');
            if ($email !== '' && FormGuard::isDisposableEmail($email)) {
                $validator->errors()->add('contact_email', 'Gebruik geen wegwerpmailadres.');
            }
            $url = trim((string) $this->input('url', ''));
            if ($url !== '' && ! preg_match('#^https?://#i', $url)) {
                $validator->errors()->add('url', 'Website moet met http:// of https:// beginnen (of leeg laten).');
            }
            $postcode = trim((string) $this->input('postcode', ''));
            $country = Country::normalize((string) $this->input('country', Country::NL));
            if ($postcode !== '') {
                $pcValid = PostalCode::validate($postcode, $country);
                if ($pcValid !== true) {
                    $validator->errors()->add('postcode', $pcValid);
                }
            }
        });
    }

    public function isBot(): bool
    {
        return FormGuard::honeypotFilled($this->input('website'));
    }
}
