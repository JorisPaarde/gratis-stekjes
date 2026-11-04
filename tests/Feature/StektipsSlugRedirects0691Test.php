<?php

declare(strict_types=1);

test('0.69.1 soorttip-slugwijzigingen geven 301', function (string $from, string $to) {
    $this->get('/stektips/'.$from)
        ->assertStatus(301)
        ->assertRedirect('/stektips/'.$to);
})->with([
    'ijzerhard' => ['ijzerhard', 'echt-ijzerhard'],
    'klokjesbloem' => ['klokjesbloem', 'ruig-klokje'],
    'longkruid' => ['longkruid', 'gevlekt-longkruid'],
    'sleutelbloem' => ['sleutelbloem', 'gulden-sleutelbloem'],
]);

test('nieuwe soortspecifieke tip-paginas laden', function (string $slug) {
    $this->get(route('stektips.show', ['slug' => $slug]))->assertOk();
})->with([
    'echt-ijzerhard',
    'ruig-klokje',
    'gevlekt-longkruid',
    'gulden-sleutelbloem',
    'gewone-margriet',
    'grote-kattenstaart',
]);
