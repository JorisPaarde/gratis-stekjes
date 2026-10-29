<?php

declare(strict_types=1);

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use App\Models\Deelplek;
use App\Models\PartnerMarker;
use App\Services\DeelplekAanmelding;
use App\Services\Deelplekken;
use App\Services\PartnerMarkers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, PartnerMarkers $partners, Deelplekken $deelplekken): View
    {
        $editId = (int) $request->query('edit', 0);
        $editing = $editId > 0 ? PartnerMarker::query()->find($editId) : null;

        $reports = collect();
        if (Schema::hasTable('reports')) {
            $reports = DB::table('reports')
                ->orderByDesc('id')
                ->limit(50)
                ->get();
        }

        $pendingDeelplekken = collect();
        if (Schema::hasTable('deelplekken')) {
            $pendingDeelplekken = Deelplek::query()
                ->pending()
                ->orderByDesc('created_at')
                ->limit(50)
                ->get();
        }

        return view('beheer.index', [
            'title' => 'Beheer',
            'metaDescription' => 'Beheer',
            'canonical' => route('beheer.index'),
            'markers' => PartnerMarker::query()->orderBy('naam')->orderBy('id')->get(),
            'typeLabels' => $partners->typeLabels(),
            'deelplekTypeLabels' => $deelplekken->typeLabels(),
            'editing' => $editing,
            'reports' => $reports,
            'hasReportsTable' => Schema::hasTable('reports'),
            'pendingDeelplekken' => $pendingDeelplekken,
        ]);
    }

    public function approveDeelplek(Request $request, DeelplekAanmelding $aanmelding): RedirectResponse
    {
        $plek = Deelplek::query()->pending()->find((int) $request->input('id', 0));
        if ($plek === null) {
            return back()->withErrors(['deelplek' => 'Aanmelding niet gevonden.']);
        }
        $aanmelding->approve($plek);

        return redirect()->route('beheer.index')->with('status', 'Deelplek goedgekeurd en zichtbaar.');
    }

    public function rejectDeelplek(Request $request, DeelplekAanmelding $aanmelding): RedirectResponse
    {
        $plek = Deelplek::query()->pending()->find((int) $request->input('id', 0));
        if ($plek === null) {
            return back()->withErrors(['deelplek' => 'Aanmelding niet gevonden.']);
        }
        $aanmelding->reject($plek);

        return redirect()->route('beheer.index')->with('status', 'Deelplek afgewezen.');
    }

    public function saveMarker(Request $request, PartnerMarkers $partners): RedirectResponse
    {
        $id = (int) $request->input('id', 0);
        $result = $partners->save($id > 0 ? $id : null, [
            'naam' => $request->input('naam'),
            'type' => $request->input('type'),
            'plaats' => $request->input('plaats'),
            'beschrijving' => $request->input('beschrijving'),
            'url' => $request->input('url'),
            'bron' => $request->input('bron'),
            'visible' => $request->boolean('visible'),
        ]);

        if (empty($result['ok'])) {
            return back()->withErrors([
                'marker' => $result['error'] ?? 'Opslaan mislukt.',
            ])->withInput();
        }

        return redirect()
            ->route('beheer.index')
            ->with('status', 'Marker opgeslagen.');
    }

    public function deleteMarker(Request $request, PartnerMarkers $partners): RedirectResponse
    {
        $result = $partners->delete((int) $request->input('id', 0));
        if (empty($result['ok'])) {
            return back()->withErrors([
                'marker' => $result['error'] ?? 'Verwijderen mislukt.',
            ]);
        }

        return redirect()
            ->route('beheer.index')
            ->with('status', 'Marker verwijderd.');
    }

    public function markReportHandled(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('reports')) {
            return back();
        }
        $id = (int) $request->input('id', 0);
        DB::table('reports')->where('id', $id)->update(['handled' => true]);

        return redirect()->route('beheer.index')->with('status', 'Melding afgehandeld.');
    }
}
