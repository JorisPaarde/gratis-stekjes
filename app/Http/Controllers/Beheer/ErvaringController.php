<?php

declare(strict_types=1);

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use App\Models\Ervaring;
use App\Services\ErvaringService;
use App\Services\Plantensoorten;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ErvaringController extends Controller
{
    public function index(Plantensoorten $plantensoorten): Response
    {
        $items = Schema::hasTable('ervaringen')
            ? Ervaring::query()
                ->with('user:id,name')
                ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END")
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(200)
                ->get()
            : collect();

        $rows = $items->map(function (Ervaring $e) use ($plantensoorten) {
            $soort = $e->plantensoort_id ? $plantensoorten->find((int) $e->plantensoort_id) : null;

            return [
                'model' => $e,
                'soort' => $soort['naam'] ?? null,
            ];
        });

        $html = view('beheer.ervaringen', [
            'title' => 'Ervaringen',
            'metaDescription' => 'Ervaringen modereren (beheer).',
            'canonical' => route('beheer.ervaringen'),
            'rows' => $rows,
        ])->render();

        return response($html, 200)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function approve(Request $request, ErvaringService $ervaringen): RedirectResponse
    {
        $data = $request->validate(['id' => ['required', 'integer', 'min:1']]);
        $item = Ervaring::query()->find((int) $data['id']);
        if ($item) {
            $ervaringen->approve($item);
        }

        return redirect()
            ->route('beheer.ervaringen')
            ->with('status', 'Ervaring goedgekeurd.');
    }

    public function reject(Request $request, ErvaringService $ervaringen): RedirectResponse
    {
        $data = $request->validate(['id' => ['required', 'integer', 'min:1']]);
        $item = Ervaring::query()->find((int) $data['id']);
        if ($item) {
            $ervaringen->reject($item);
        }

        return redirect()
            ->route('beheer.ervaringen')
            ->with('status', 'Ervaring afgewezen.');
    }

    public function destroy(Request $request, ErvaringService $ervaringen): RedirectResponse
    {
        $data = $request->validate(['id' => ['required', 'integer', 'min:1']]);
        $item = Ervaring::query()->find((int) $data['id']);
        if ($item) {
            $ervaringen->deleteErvaring($item);
        }

        return redirect()
            ->route('beheer.ervaringen')
            ->with('status', 'Ervaring verwijderd.');
    }

    public function photo(Ervaring $ervaring): StreamedResponse|Response
    {
        abort_unless($ervaring->hasPhoto(), 404);
        $path = (string) $ervaring->photo_path;
        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
