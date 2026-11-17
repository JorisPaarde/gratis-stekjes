<?php

declare(strict_types=1);

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use App\Models\Deelplek;
use App\Services\Deelplekken;
use App\Services\DeelplekVerwijdering;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DeelplekkenController extends Controller
{
    public function index(Request $request, Deelplekken $deelplekken): View
    {
        $filter = $this->normalizeFilter((string) $request->query('zichtbaar', 'verborgen'));

        $query = Schema::hasTable('deelplekken')
            ? Deelplek::query()->orderBy('plaats')->orderBy('naam')
            : Deelplek::query()->whereRaw('0 = 1');

        if ($filter === 'verborgen') {
            $query->where('visible', false);
        } elseif ($filter === 'zichtbaar') {
            $query->where('visible', true);
        }

        $plekken = $query->limit(500)->get();

        return view('beheer.deelplekken.index', [
            'title' => 'Deelplekken · Beheer',
            'metaDescription' => 'Beheer deelplekken',
            'canonical' => route('beheer.deelplekken.index', ['zichtbaar' => $filter]),
            'plekken' => $plekken,
            'filter' => $filter,
            'typeLabels' => $deelplekken->typeLabels(),
        ]);
    }

    public function show(Deelplek $deelplek, Deelplekken $deelplekken): View
    {
        return view('beheer.deelplekken.show', [
            'title' => $deelplek->naam.' · Beheer',
            'metaDescription' => 'Deelplek details',
            'canonical' => route('beheer.deelplekken.show', $deelplek),
            'plek' => $deelplek,
            'typeLabels' => $deelplekken->typeLabels(),
        ]);
    }

    public function confirmDestroy(Deelplek $deelplek): View|RedirectResponse
    {
        if ($deelplek->visible) {
            return redirect()
                ->route('beheer.deelplekken.show', $deelplek)
                ->withErrors(['deelplek' => 'Zichtbare deelplekken kun je niet verwijderen.']);
        }

        return view('beheer.deelplekken.delete', [
            'title' => 'Deelplek verwijderen',
            'metaDescription' => 'Bevestig verwijderen van deelplek.',
            'canonical' => route('beheer.deelplekken.destroy.confirm', $deelplek),
            'plekken' => collect([$deelplek]),
            'bulk' => false,
            'ids' => [(int) $deelplek->id],
        ]);
    }

    public function destroy(Request $request, Deelplek $deelplek, DeelplekVerwijdering $verwijdering): RedirectResponse
    {
        if ($deelplek->visible) {
            return redirect()
                ->route('beheer.deelplekken.show', $deelplek)
                ->withErrors(['deelplek' => 'Zichtbare deelplekken kun je niet verwijderen.']);
        }

        $request->validate([
            'confirm' => ['required', 'accepted'],
        ], [
            'confirm.required' => 'Bevestig dat je deze deelplek wilt verwijderen.',
            'confirm.accepted' => 'Bevestig dat je deze deelplek wilt verwijderen.',
        ]);

        $result = $verwijdering->deleteOne($deelplek);
        if (empty($result['ok'])) {
            return back()->withErrors(['deelplek' => $result['error'] ?? 'Verwijderen mislukt.']);
        }

        $naam = $result['deleted'][0]['naam'] ?? 'Deelplek';

        return redirect()
            ->route('beheer.deelplekken.index', ['zichtbaar' => 'verborgen'])
            ->with('status', $naam.' is definitief verwijderd.');
    }

    public function confirmBulkDestroy(Request $request, DeelplekVerwijdering $verwijdering): View|RedirectResponse
    {
        $ids = $request->input('ids', []);
        if (! is_array($ids)) {
            $ids = [];
        }

        $plekken = $verwijdering->findHiddenByIds($ids);
        if ($plekken->isEmpty()) {
            return redirect()
                ->route('beheer.deelplekken.index', ['zichtbaar' => 'verborgen'])
                ->withErrors(['deelplek' => 'Selecteer minstens één verborgen deelplek.']);
        }

        // Zichtbare of onbekende id’s in de selectie → weigeren (geen gedeeltelijke delete).
        $requested = array_values(array_unique(array_filter(
            array_map(static fn ($id): int => (int) $id, $ids),
            static fn (int $id): bool => $id > 0
        )));
        if (count($requested) !== $plekken->count()) {
            return redirect()
                ->route('beheer.deelplekken.index', ['zichtbaar' => 'verborgen'])
                ->withErrors(['deelplek' => 'Alleen verborgen deelplekken mogen in de selectie staan.']);
        }

        return view('beheer.deelplekken.delete', [
            'title' => 'Selectie verwijderen',
            'metaDescription' => 'Bevestig verwijderen van deelplekken.',
            'canonical' => route('beheer.deelplekken.bulk-destroy.confirm'),
            'plekken' => $plekken,
            'bulk' => true,
            'ids' => $plekken->pluck('id')->map(fn ($id) => (int) $id)->all(),
        ]);
    }

    public function bulkDestroy(Request $request, DeelplekVerwijdering $verwijdering): RedirectResponse
    {
        $request->validate([
            'confirm' => ['required', 'accepted'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ], [
            'confirm.required' => 'Bevestig dat je deze deelplekken wilt verwijderen.',
            'confirm.accepted' => 'Bevestig dat je deze deelplekken wilt verwijderen.',
            'ids.required' => 'Selecteer minstens één verborgen deelplek.',
        ]);

        $ids = $request->input('ids', []);
        if (! is_array($ids)) {
            $ids = [];
        }

        $plekken = $verwijdering->findHiddenByIds($ids);
        $requested = array_values(array_unique(array_filter(
            array_map(static fn ($id): int => (int) $id, $ids),
            static fn (int $id): bool => $id > 0
        )));

        if ($plekken->isEmpty() || count($requested) !== $plekken->count()) {
            return redirect()
                ->route('beheer.deelplekken.index', ['zichtbaar' => 'verborgen'])
                ->withErrors(['deelplek' => 'Alleen verborgen deelplekken mogen worden verwijderd.']);
        }

        $result = $verwijdering->deleteMany($plekken);
        if (empty($result['ok'])) {
            return back()->withErrors(['deelplek' => $result['error'] ?? 'Verwijderen mislukt.']);
        }

        $aantal = count($result['deleted'] ?? []);

        return redirect()
            ->route('beheer.deelplekken.index', ['zichtbaar' => 'verborgen'])
            ->with('status', $aantal === 1
                ? '1 deelplek is definitief verwijderd.'
                : $aantal.' deelplekken zijn definitief verwijderd.');
    }

    private function normalizeFilter(string $raw): string
    {
        return in_array($raw, ['alle', 'zichtbaar', 'verborgen'], true) ? $raw : 'verborgen';
    }
}
