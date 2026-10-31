<?php

declare(strict_types=1);

use App\Support\SeoTitle;

test('document title krijgt merk-suffix alleen onder 60 tekens', function () {
    $site = 'Gratis planten';

    expect(SeoTitle::document('', $site))->toBe($site);
    expect(SeoTitle::document('Aanbod', $site))->toBe('Aanbod · Gratis planten');

    $home = 'Gratis planten en stekjes delen in je buurt';
    expect(mb_strlen($home.' · '.$site))->toBe(60)
        ->and(SeoTitle::document($home, $site))->toBe($home.' · '.$site);

    $deel = 'Plantenasiel, plantenbieb en ruilkastje in je buurt';
    expect(SeoTitle::fitsWithBrand($deel, $site))->toBeFalse()
        ->and(SeoTitle::document($deel, $site))->toBe($deel);
});
