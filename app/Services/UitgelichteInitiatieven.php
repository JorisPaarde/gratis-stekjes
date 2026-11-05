<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;

/** Initiatieven om aan mee te doen (data/uitgelichte-initiatieven.json): homepage en bovenaan /initiatieven. */
final class UitgelichteInitiatieven
{
    public function __construct(private readonly ?string $jsonPath = null) {}

    /**
     * Acties met een «tot»-datum verdwijnen na die dag (Europe/Amsterdam).
     *
     * @return list<array{anker: string, naam: string, organisatie: string, kort: string, tekst: string, illustratie: string, afbeelding: array{bestand: string, alt: string, credit: string}|null, acties: list<array{label: string, url: string, extern: bool}>}>
     */
    public function all(): array
    {
        $path = $this->jsonPath ?? base_path('data/uitgelichte-initiatieven.json');
        $data = is_readable($path) ? json_decode((string) file_get_contents($path), true) : null;
        $vandaag = Carbon::now('Europe/Amsterdam')->toDateString();

        $uit = [];
        foreach (is_array($data) ? ($data['items'] ?? []) : [] as $item) {
            $acties = [];
            foreach ($item['acties'] ?? [] as $actie) {
                if (! empty($actie['tot']) && (string) $actie['tot'] < $vandaag) {
                    continue;
                }
                $intern = ! empty($actie['route']);
                $acties[] = [
                    'label' => (string) $actie['label'],
                    'url' => $intern ? route((string) $actie['route'], $actie['params'] ?? []) : (string) $actie['url'],
                    'extern' => ! $intern,
                ];
            }
            $paginaUrl = null;
            if (! empty($item['pagina_route'])) {
                $paginaUrl = route((string) $item['pagina_route'], $item['pagina_params'] ?? []);
            } elseif (! empty($item['pagina_url'])) {
                $paginaUrl = (string) $item['pagina_url'];
            }

            $uit[] = [
                'anker' => (string) $item['anker'],
                'naam' => (string) $item['naam'],
                'organisatie' => (string) ($item['organisatie'] ?? ''),
                'kort' => (string) ($item['kort'] ?? ''),
                'tekst' => (string) ($item['tekst'] ?? ''),
                'illustratie' => (string) ($item['illustratie'] ?? 'kopstek'),
                'pagina_url' => $paginaUrl,
                'afbeelding' => ! empty($item['afbeelding']['bestand']) ? [
                    'bestand' => (string) $item['afbeelding']['bestand'],
                    'alt' => (string) ($item['afbeelding']['alt'] ?? ''),
                    'credit' => (string) ($item['afbeelding']['credit'] ?? ''),
                ] : null,
                'acties' => $acties,
            ];
        }

        return $uit;
    }

    /** @return list<string> */
    public function ankers(): array
    {
        return array_column($this->all(), 'anker');
    }
}
