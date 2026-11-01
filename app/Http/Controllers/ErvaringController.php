<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Ervaring;
use App\Services\Plantensoorten;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ErvaringController extends Controller
{
    public function index(Plantensoorten $plantensoorten): View|Response
    {
        $items = Schema::hasTable('ervaringen')
            ? Ervaring::query()
                ->approved()
                ->with('user:id,name')
                ->orderByDesc('approved_at')
                ->orderByDesc('id')
                ->limit(50)
                ->get()
            : collect();

        $empty = $items->isEmpty();
        $siteNoindex = (bool) config('site.noindex') || app()->environment('staging');
        $noindex = $siteNoindex || $empty;

        $enriched = $items->map(function (Ervaring $e) use ($plantensoorten) {
            $soort = null;
            if ($e->plantensoort_id) {
                $soort = $plantensoorten->find((int) $e->plantensoort_id);
            }

            return [
                'id' => $e->id,
                'body' => $e->body,
                'plaatsnaam' => $e->plaatsnaam,
                'naam' => $e->user?->publiekeNaam() ?? 'Lid',
                'soort' => $soort['naam'] ?? null,
                'has_photo' => $e->hasPhoto(),
                'photo_url' => $e->hasPhoto() ? route('ervaringen.foto', $e) : null,
                'datum' => $e->approved_at?->timezone('Europe/Amsterdam')->format('d-m-Y'),
            ];
        });

        $html = view('pages.ervaringen', [
            'title' => 'Ervaringen',
            'metaDescription' => 'Korte verhalen van leden die planten en stekjes delen via '.config('site.name').'. Met of zonder foto, en alleen een plaatsnaam, nooit een adres.',
            'canonical' => route('ervaringen.index'),
            'items' => $enriched,
            'empty' => $empty,
            'forceNoindex' => $noindex,
        ])->render();

        $response = response($html, 200)
            ->header('Content-Type', 'text/html; charset=UTF-8');

        if ($noindex) {
            $response->header('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }

    public function photo(Ervaring $ervaring): StreamedResponse|Response
    {
        // Alleen goedgekeurde foto’s publiek; eigenaar/admin via andere routes.
        abort_unless($ervaring->isApproved() && $ervaring->hasPhoto(), 404);

        return $this->streamPhoto($ervaring);
    }

    public function streamPhoto(Ervaring $ervaring): StreamedResponse|Response
    {
        $path = (string) $ervaring->photo_path;
        abort_unless($path !== '' && Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
