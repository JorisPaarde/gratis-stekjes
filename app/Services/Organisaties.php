<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class Organisaties
{
    public function __construct(
        private readonly ?string $jsonPath = null,
    ) {}

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        $data = $this->load();

        return array_values(array_filter(
            $data['organisaties'] ?? [],
            static function ($row): bool {
                if (! is_array($row) || empty($row['slug']) || empty($row['naam'])) {
                    return false;
                }

                return self::rowVisible($row);
            }
        ));
    }

    /** @return array<string, mixed>|null */
    public function find(string $slug): ?array
    {
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            return null;
        }
        foreach ($this->all() as $row) {
            if (($row['slug'] ?? '') === $slug) {
                return $row;
            }
        }

        return null;
    }

    /** Of een organisatie-rij publiek zichtbaar is (default true). */
    private static function rowVisible(array $row): bool
    {
        if (! array_key_exists('visible', $row)) {
            return true;
        }
        $visible = filter_var($row['visible'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($visible === null) {
            return (bool) $row['visible'];
        }

        return $visible;
    }

    /**
     * @return array{meta: array<string, mixed>, organisaties: list<array<string, mixed>>}
     */
    public function load(): array
    {
        $path = $this->jsonPath ?? base_path('data/organisaties.json');
        if (! is_readable($path)) {
            throw new RuntimeException('Organisaties-bestand niet leesbaar: '.$path);
        }
        $raw = file_get_contents($path);
        $data = json_decode((string) $raw, true);
        if (! is_array($data) || ! isset($data['organisaties']) || ! is_array($data['organisaties'])) {
            throw new RuntimeException('Ongeldig organisaties-JSON.');
        }
        if (! isset($data['meta']) || ! is_array($data['meta'])) {
            throw new RuntimeException('Organisaties-JSON mist meta.');
        }

        return $data;
    }

    /** @return list<string> */
    public function validateJson(): array
    {
        $errors = [];
        try {
            $data = $this->load();
        } catch (RuntimeException $e) {
            return [$e->getMessage()];
        }

        $seen = [];
        foreach ($data['organisaties'] as $i => $row) {
            if (! is_array($row)) {
                $errors[] = "organisaties[$i] is geen object";

                continue;
            }
            foreach (['slug', 'naam', 'type', 'plaats', 'website', 'bron_url', 'kort'] as $key) {
                if (! array_key_exists($key, $row) || trim((string) $row[$key]) === '') {
                    $errors[] = "organisaties[$i] mist veld $key";
                }
            }
            $slug = (string) ($row['slug'] ?? '');
            if ($slug !== '' && isset($seen[$slug])) {
                $errors[] = "dubbele slug: $slug";
            } elseif ($slug !== '') {
                $seen[$slug] = true;
            }
            $website = (string) ($row['website'] ?? '');
            if ($website !== '' && ! preg_match('#^https?://#i', $website)) {
                $errors[] = "organisaties[$i] website moet http(s) zijn";
            }
            $bron = (string) ($row['bron_url'] ?? '');
            if ($bron !== '' && ! preg_match('#^https?://#i', $bron)) {
                $errors[] = "organisaties[$i] bron_url moet http(s) zijn";
            }
            if (array_key_exists('visible', $row)) {
                $visible = filter_var($row['visible'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($visible === null && ! is_bool($row['visible'])) {
                    $errors[] = "organisaties[$i] visible moet boolean zijn";
                }
            }
            if (array_key_exists('deelplek_slugs', $row) && ! is_array($row['deelplek_slugs'])) {
                $errors[] = "organisaties[$i] deelplek_slugs moet een array zijn";
            }
            if (array_key_exists('gerelateerde_slugs', $row)) {
                if (! is_array($row['gerelateerde_slugs'])) {
                    $errors[] = "organisaties[$i] gerelateerde_slugs moet een array zijn";
                } else {
                    foreach ($row['gerelateerde_slugs'] as $j => $relatedSlug) {
                        if (! is_string($relatedSlug) || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $relatedSlug)) {
                            $errors[] = "organisaties[$i] gerelateerde_slugs[$j] ongeldig";
                        }
                    }
                }
            }
            if (array_key_exists('links', $row)) {
                if (! is_array($row['links'])) {
                    $errors[] = "organisaties[$i] links moet een array zijn";
                } else {
                    foreach ($row['links'] as $j => $link) {
                        if (! is_array($link)) {
                            $errors[] = "organisaties[$i] links[$j] is geen object";

                            continue;
                        }
                        $label = trim((string) ($link['label'] ?? ''));
                        $url = trim((string) ($link['url'] ?? ''));
                        if ($label === '') {
                            $errors[] = "organisaties[$i] links[$j] mist label";
                        }
                        if ($url === '' || ! preg_match('#^https?://#i', $url)) {
                            $errors[] = "organisaties[$i] links[$j] url moet http(s) zijn";
                        }
                    }
                }
            }
        }

        return $errors;
    }
}
