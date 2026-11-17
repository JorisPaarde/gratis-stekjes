<?php

declare(strict_types=1);

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use App\Models\Vereniging;
use App\Services\VerenigingVerwijdering;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VerenigingenController extends Controller
{
    public function confirmDestroy(Vereniging $vereniging, VerenigingVerwijdering $verwijdering): View|RedirectResponse
    {
        $reden = VerenigingVerwijdering::weigerReden($vereniging);
        if ($reden !== null) {
            return redirect()->route('beheer.verenigingen')->withErrors(['vereniging' => $reden]);
        }

        $plekken = $verwijdering->findDeletableByIds([$vereniging->id]);

        return view('beheer.verenigingen.delete', [
            'title' => 'Vereniging verwijderen',
            'metaDescription' => 'Bevestig verwijderen van vereniging.',
            'canonical' => route('beheer.verenigingen.destroy.confirm', $vereniging),
            'verenigingen' => $plekken,
            'bulk' => false,
            'ids' => $plekken->pluck('id')->map(fn ($id) => (int) $id)->all(),
        ]);
    }

    public function destroy(Request $request, Vereniging $vereniging, VerenigingVerwijdering $verwijdering): RedirectResponse
    {
        $reden = VerenigingVerwijdering::weigerReden($vereniging);
        if ($reden !== null) {
            return redirect()->route('beheer.verenigingen')->withErrors(['vereniging' => $reden]);
        }

        $request->validate([
            'confirm' => ['required', 'accepted'],
        ], [
            'confirm.required' => 'Bevestig dat je deze vereniging wilt verwijderen.',
            'confirm.accepted' => 'Bevestig dat je deze vereniging wilt verwijderen.',
        ]);

        $result = $verwijdering->deleteMany($verwijdering->findDeletableByIds([$vereniging->id]));
        if (empty($result['ok'])) {
            return redirect()->route('beheer.verenigingen')
                ->withErrors(['vereniging' => $result['error'] ?? 'Verwijderen mislukt.']);
        }

        return redirect()
            ->route('beheer.verenigingen')
            ->with('status', ($result['deleted'][0]['naam'] ?? 'Vereniging').' is definitief verwijderd.');
    }

    public function confirmBulkDestroy(Request $request, VerenigingVerwijdering $verwijdering): View|RedirectResponse
    {
        $ids = $this->idsFrom($request);
        $verenigingen = $verwijdering->findDeletableByIds($ids);

        if ($verenigingen->isEmpty()) {
            return redirect()->route('beheer.verenigingen')
                ->withErrors(['vereniging' => 'Selecteer minstens één verborgen vereniging.']);
        }
        if ($verenigingen->count() !== count($this->normalize($ids))) {
            return redirect()->route('beheer.verenigingen')
                ->withErrors(['vereniging' => 'Alleen verborgen verenigingen die niet bij de vaste lijst horen mogen in de selectie staan.']);
        }

        return view('beheer.verenigingen.delete', [
            'title' => 'Selectie verwijderen',
            'metaDescription' => 'Bevestig verwijderen van verenigingen.',
            'canonical' => route('beheer.verenigingen.bulk-destroy.confirm'),
            'verenigingen' => $verenigingen,
            'bulk' => true,
            'ids' => $verenigingen->pluck('id')->map(fn ($id) => (int) $id)->all(),
        ]);
    }

    public function bulkDestroy(Request $request, VerenigingVerwijdering $verwijdering): RedirectResponse
    {
        $request->validate([
            'confirm' => ['required', 'accepted'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ], [
            'confirm.required' => 'Bevestig dat je deze verenigingen wilt verwijderen.',
            'confirm.accepted' => 'Bevestig dat je deze verenigingen wilt verwijderen.',
            'ids.required' => 'Selecteer minstens één verborgen vereniging.',
        ]);

        $ids = $this->idsFrom($request);
        $verenigingen = $verwijdering->findDeletableByIds($ids);

        if ($verenigingen->isEmpty() || $verenigingen->count() !== count($this->normalize($ids))) {
            return redirect()->route('beheer.verenigingen')
                ->withErrors(['vereniging' => 'Alleen verborgen verenigingen die niet bij de vaste lijst horen mogen worden verwijderd.']);
        }

        $result = $verwijdering->deleteMany($verenigingen);
        if (empty($result['ok'])) {
            return redirect()->route('beheer.verenigingen')
                ->withErrors(['vereniging' => $result['error'] ?? 'Verwijderen mislukt.']);
        }

        $aantal = count($result['deleted'] ?? []);

        return redirect()
            ->route('beheer.verenigingen')
            ->with('status', $aantal === 1
                ? '1 vereniging is definitief verwijderd.'
                : $aantal.' verenigingen zijn definitief verwijderd.');
    }

    /** @return list<mixed> */
    private function idsFrom(Request $request): array
    {
        $ids = $request->input('ids', []);

        return is_array($ids) ? array_values($ids) : [];
    }

    /** @return list<int> */
    private function normalize(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn ($id): int => (int) $id, $ids),
            static fn (int $id): bool => $id > 0
        )));
    }
}
