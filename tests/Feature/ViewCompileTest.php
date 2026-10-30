<?php

declare(strict_types=1);

test('alle Blade-views compileren via view:cache', function () {
    $this->artisan('view:clear')->assertSuccessful();
    $this->artisan('view:cache')->assertSuccessful();
    $this->artisan('view:clear')->assertSuccessful();
});
