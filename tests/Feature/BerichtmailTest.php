<?php

declare(strict_types=1);

use App\Mail\NewMessageMail;
use App\Models\Aanbod;
use App\Models\Conversation;
use App\Models\GeocodeCache;
use App\Models\MailLog;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

function bmUser(string $email, string $name): User
{
    return User::factory()->create([
        'email' => $email,
        'name' => $name,
        'locatie_label' => '2011',
        'lat' => 52.381,
        'lon' => 4.637,
    ]);
}

function bmConversation(User $a, User $b, ?int $aanbodId = null): Conversation
{
    return Conversation::query()->create([
        'user_a_id' => min($a->id, $b->id),
        'user_b_id' => max($a->id, $b->id),
        'aanbod_id' => $aanbodId,
    ]);
}

function bmSend($test, User $from, Conversation $conv, string $body = 'Hoi!'): void
{
    $test->actingAs($from)
        ->post(route('account.gesprek.store', $conv), [
            'action' => 'message',
            'body' => $body,
            'website' => '',
            'form_started' => time() - 10,
        ])
        ->assertRedirect();
}

test('berichtmail noemt afzender, aanbod en link naar het gesprek, niet de inhoud', function () {
    Mail::fake();
    $ada = bmUser('ada@example.com', 'Ada');
    $ben = bmUser('ben@example.com', 'Ben');
    $aanbod = Aanbod::query()->create([
        'user_id' => $ben->id,
        'type' => 'uitdelen',
        'tekst' => 'Muntstekjes uit eigen tuin',
        'visible' => true,
        'expires_at' => now()->addDays(30),
    ]);
    $conv = bmConversation($ada, $ben, $aanbod->id);

    bmSend($this, $ada, $conv, 'Geheime inhoud 123');

    Mail::assertSent(NewMessageMail::class, function (NewMessageMail $mail) use ($ben, $conv) {
        $text = $mail->render();

        return $mail->hasTo($ben->email)
            && $mail->envelope()->subject === 'Ada stuurde je een bericht op '.config('site.name')
            && str_contains($text, 'Muntstekjes uit eigen tuin')
            && str_contains($text, route('account.gesprek', $conv))
            && str_contains($text, '/berichtmail/uit/')
            && ! str_contains($text, 'Geheime inhoud 123')
            && ! str_contains($text, 'ada@example.com');
    });

    expect(MailLog::query()->where('type', 'NewMessageMail')->where('status', 'ok')->where('user_id', $ben->id)->exists())->toBeTrue();
});

test('mislukte berichtmail komt in het mail-log en het bericht blijft staan', function () {
    $ada = bmUser('ada2@example.com', 'Ada');
    $ben = bmUser('ben2@example.com', 'Ben');
    $conv = bmConversation($ada, $ben);

    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP weg'));

    bmSend($this, $ada, $conv);

    expect(Message::query()->count())->toBe(1);
    $log = MailLog::query()->where('type', 'NewMessageMail')->first();
    expect($log)->not->toBeNull();
    expect($log->status)->toBe('fail');
    expect($log->user_id)->toBe($ben->id);
});

test('meerdere berichten kort na elkaar geven één mail tot het gesprek is geopend', function () {
    Mail::fake();
    $ada = bmUser('ada3@example.com', 'Ada');
    $ben = bmUser('ben3@example.com', 'Ben');
    $conv = bmConversation($ada, $ben);

    bmSend($this, $ada, $conv, 'Een');
    bmSend($this, $ada, $conv, 'Twee');
    bmSend($this, $ada, $conv, 'Drie');
    Mail::assertSentCount(1);

    // Ben opent het gesprek: daarna levert een nieuw bericht weer een mail op.
    $this->actingAs($ben)->get(route('account.gesprek', $conv))->assertOk();
    bmSend($this, $ada, $conv, 'Vier');
    Mail::assertSentCount(2);
});

test('ongelezen bericht ouder dan de bundeltijd geeft weer een mail', function () {
    Mail::fake();
    $ada = bmUser('ada4@example.com', 'Ada');
    $ben = bmUser('ben4@example.com', 'Ben');
    $conv = bmConversation($ada, $ben);

    Message::query()->create([
        'conversation_id' => $conv->id,
        'sender_id' => $ada->id,
        'body' => 'Oud',
        'created_at' => now()->subHours(30),
    ]);

    bmSend($this, $ada, $conv, 'Nieuw');
    Mail::assertSentCount(1);
});

test('geen berichtmail als de ontvanger die uitzette', function () {
    Mail::fake();
    $ada = bmUser('ada5@example.com', 'Ada');
    $ben = bmUser('ben5@example.com', 'Ben');
    $ben->forceFill(['berichtmail' => false])->save();
    $conv = bmConversation($ada, $ben);

    bmSend($this, $ada, $conv);

    expect(Message::query()->count())->toBe(1);
    Mail::assertNothingSent();
});

test('uitzetlink: GET toont alleen een knop, POST zet uit, zonder ondertekening 403', function () {
    $ben = bmUser('ben6@example.com', 'Ben');
    $url = URL::signedRoute('berichtmail.uit', ['user' => $ben->id]);

    $this->get($url)->assertOk()->assertSee('Zet de mail uit', false)
        ->assertSee('noindex', false);
    expect($ben->fresh()->berichtmail)->toBeTrue();

    $this->post($url)->assertOk()->assertSee('Je krijgt geen mail meer', false);
    expect($ben->fresh()->berichtmail)->toBeFalse();

    $this->get(route('berichtmail.uit', ['user' => $ben->id]))->assertForbidden();
    $this->post(route('berichtmail.uit', ['user' => $ben->id]))->assertForbidden();
});

test('berichtmail aan en uit via profiel', function () {
    $ben = bmUser('ben7@example.com', 'Ben');

    $this->actingAs($ben)->get(route('account.profiel'))
        ->assertOk()
        ->assertSee('Stuur me een mail als iemand me een bericht stuurt', false);

    GeocodeCache::query()->create([
        'query_key' => 'haarlem',
        'lat' => 52.381,
        'lon' => 4.637,
        'label' => 'Haarlem',
        'cached_at' => now(),
    ]);
    $post = fn (array $extra) => $this->actingAs($ben)->post(route('account.profiel'), [
        'voornaam' => 'Ben',
        'country' => 'NL',
        'locatie' => 'Haarlem',
        'telefoon' => '',
        ...$extra,
    ])->assertRedirect(route('account.profiel'));

    $post(['berichtmail' => '0']);
    expect($ben->fresh()->berichtmail)->toBeFalse();

    $post(['berichtmail' => '1']);
    expect($ben->fresh()->berichtmail)->toBeTrue();

    // Zonder het veld (oud formulier) blijft de instelling staan.
    $post([]);
    expect($ben->fresh()->berichtmail)->toBeTrue();
});

test('ongelezen-teller in navigatie, inbox en account; weg na openen', function () {
    $ada = bmUser('ada8@example.com', 'Ada');
    $ben = bmUser('ben8@example.com', 'Ben');
    $conv = bmConversation($ada, $ben);
    foreach (['Een', 'Twee'] as $body) {
        Message::query()->create([
            'conversation_id' => $conv->id,
            'sender_id' => $ada->id,
            'body' => $body,
            'created_at' => now(),
        ]);
    }

    $this->actingAs($ben)->get(route('aanbod'))
        ->assertOk()
        ->assertSee('aria-label="2 ongelezen berichten"', false)
        ->assertSee(route('account.inbox'), false);
    $this->actingAs($ben)->get(route('account.inbox'))->assertOk()->assertSee('2 nieuw', false);
    $this->actingAs($ben)->get(route('account.index'))->assertOk()->assertSee('2 nieuw', false);

    // Afzender ziet eigen berichten niet als ongelezen.
    $this->actingAs($ada)->get(route('aanbod'))->assertOk()->assertDontSee('ongelezen bericht', false);

    $this->actingAs($ben)->get(route('account.gesprek', $conv))->assertOk();
    $this->actingAs($ben)->get(route('aanbod'))->assertOk()->assertDontSee('ongelezen bericht', false);
    $this->actingAs($ben)->get(route('account.inbox'))->assertOk()->assertDontSee('nieuw</span>', false);
});

test('gegevensexport bevat de berichtmail-instelling', function () {
    $ben = bmUser('ben9@example.com', 'Ben');
    $response = $this->actingAs($ben)->get(route('account.export'))->assertOk();
    $data = json_decode($response->streamedContent(), true);
    expect($data['user']['berichtmail'])->toBeTrue();
});
