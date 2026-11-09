<?php

declare(strict_types=1);

/**
 * Regressie: browsers / 1Password moeten op /account/registreren correct kunnen
 * autofillen en een sterk wachtwoord voorstellen. Geen autocomplete="off" op
 * e-mail, wachtwoord, land of postcode.
 */
function registerFormElementAttrs(string $html, string $id): array
{
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $el = $dom->getElementById($id);
    expect($el)->not->toBeNull("Element #{$id} ontbreekt");

    $attrs = [];
    foreach ($el->attributes ?? [] as $attr) {
        $attrs[strtolower($attr->name)] = $attr->value;
    }

    return $attrs;
}

test('registratieformulier heeft correcte autocomplete type en inputmode attributen', function () {
    $html = $this->get(route('account.registreren'))->assertOk()->getContent();

    expect($html)->toContain('method="post"')
        ->and($html)->toContain('data-meet-form="register"')
        ->and($html)->toContain('for="reg-email"')
        ->and($html)->toContain('for="reg-password"')
        ->and($html)->toContain('for="reg-country"')
        ->and($html)->toContain('for="reg-postcode"')
        ->and($html)->not->toContain('name="password_confirmation"');

    $email = registerFormElementAttrs($html, 'reg-email');
    expect($email)->toMatchArray([
        'type' => 'email',
        'name' => 'email',
        'autocomplete' => 'email',
        'inputmode' => 'email',
        'autocapitalize' => 'off',
        'spellcheck' => 'false',
    ]);

    $password = registerFormElementAttrs($html, 'reg-password');
    expect($password)->toMatchArray([
        'type' => 'password',
        'name' => 'password',
        'autocomplete' => 'new-password',
        'passwordrules' => 'minlength: 10;',
        'minlength' => '10',
    ]);

    $country = registerFormElementAttrs($html, 'reg-country');
    expect($country)->toMatchArray([
        'name' => 'country',
        'autocomplete' => 'country',
    ]);

    $postcode = registerFormElementAttrs($html, 'reg-postcode');
    expect($postcode)->toMatchArray([
        'type' => 'text',
        'name' => 'postcode',
        'autocomplete' => 'postal-code',
        'inputmode' => 'text',
        'autocapitalize' => 'characters',
        'spellcheck' => 'false',
    ]);

    expect($html)->toContain('name="'.\App\Support\FormGuard::HONEYPOT_FIELD.'"')
        ->and($html)->toMatch('/name="'.preg_quote(\App\Support\FormGuard::HONEYPOT_FIELD, '/').'"[^>]*autocomplete="off"/s')
        ->and($html)->toContain('data-1p-ignore')
        ->and($html)->toContain('data-lpignore="true"')
        ->and($html)->toContain('data-bwignore')
        ->and($email['autocomplete'])->not->toBe('off')
        ->and($password['autocomplete'])->not->toBe('off')
        ->and($country['autocomplete'])->not->toBe('off')
        ->and($postcode['autocomplete'])->not->toBe('off');
});

test('inloggen en wachtwoord-reset delen consistente autocomplete-attributen', function () {
    $login = $this->get(route('account.inloggen'))->assertOk()->getContent();
    expect($login)->toContain('for="login-email"')
        ->and($login)->toContain('for="login-password"')
        ->and(registerFormElementAttrs($login, 'login-email'))->toMatchArray([
            'type' => 'email',
            'autocomplete' => 'username',
            'inputmode' => 'email',
            'autocapitalize' => 'off',
            'spellcheck' => 'false',
        ])
        ->and(registerFormElementAttrs($login, 'login-password'))->toMatchArray([
            'type' => 'password',
            'autocomplete' => 'current-password',
        ]);

    $forgot = $this->get(route('account.wachtwoord.vergeten'))->assertOk()->getContent();
    expect($forgot)->toContain('for="forgot-email"')
        ->and(registerFormElementAttrs($forgot, 'forgot-email'))->toMatchArray([
            'type' => 'email',
            'autocomplete' => 'email',
            'inputmode' => 'email',
        ]);
});
