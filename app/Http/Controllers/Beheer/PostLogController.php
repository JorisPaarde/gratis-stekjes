<?php

declare(strict_types=1);

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use App\Models\PostLog;
use App\Services\ConversionTracker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class PostLogController extends Controller
{
    /** @var list<string> */
    public const SOORTEN = [
        'aankondiging',
        'uitleg',
        'oproep',
        'resultaat',
        'anders',
    ];

    public function index(ConversionTracker $tracker): Response
    {
        abort_unless(Schema::hasTable('post_logs'), 404);

        $posts = PostLog::query()->orderByDesc('posted_on')->orderByDesc('id')->paginate(40);
        $conversions = $tracker->conversionsForPosts();

        $html = view('beheer.posts.index', [
            'title' => 'Postlogboek',
            'metaDescription' => 'Social posts en UTM-links (beheer).',
            'canonical' => route('beheer.posts.index'),
            'posts' => $posts,
            'byPost' => $conversions['by_post'],
            'byGroep' => $conversions['by_groep'],
            'soorten' => self::SOORTEN,
        ])->render();

        return response($html, 200)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function create(): Response
    {
        $html = view('beheer.posts.form', [
            'title' => 'Post toevoegen',
            'metaDescription' => 'Nieuwe post in het logboek.',
            'canonical' => route('beheer.posts.create'),
            'post' => new PostLog([
                'posted_on' => now()->toDateString(),
                'titel' => '',
                'utm_source' => 'facebook',
                'soort' => 'aankondiging',
            ]),
            'soorten' => self::SOORTEN,
            'action' => route('beheer.posts.store'),
            'method' => 'POST',
        ])->render();

        return response($html, 200)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $post = PostLog::query()->create($data);

        return redirect()
            ->route('beheer.posts.index')
            ->with('status', 'Post opgeslagen. UTM-link staat klaar om te kopiëren (#post-'.$post->id.').');
    }

    public function edit(PostLog $post): Response
    {
        $html = view('beheer.posts.form', [
            'title' => 'Post bewerken',
            'metaDescription' => 'Post bewerken.',
            'canonical' => route('beheer.posts.edit', $post),
            'post' => $post,
            'soorten' => self::SOORTEN,
            'action' => route('beheer.posts.update', $post),
            'method' => 'POST',
        ])->render();

        return response($html, 200)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function update(Request $request, PostLog $post): RedirectResponse
    {
        $post->update($this->validated($request));

        return redirect()
            ->route('beheer.posts.index')
            ->with('status', 'Post bijgewerkt.');
    }

    public function destroy(PostLog $post): RedirectResponse
    {
        $post->delete();

        return redirect()
            ->route('beheer.posts.index')
            ->with('status', 'Post verwijderd.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'posted_on' => ['required', 'date'],
            'titel' => ['required', 'string', 'max:200'],
            'groep' => ['required', 'string', 'max:120'],
            'soort' => ['required', 'string', Rule::in(self::SOORTEN)],
            'link' => ['nullable', 'string', 'max:500'],
            'utm_source' => ['required', 'string', 'max:80'],
            'notitie' => ['nullable', 'string', 'max:500'],
        ], [
            'titel.required' => 'Vul een titel in.',
            'groep.required' => 'Vul de groepsnaam in.',
            'posted_on.required' => 'Kies een datum.',
        ]);

        $data['titel'] = trim((string) $data['titel']);
        $data['groep'] = trim((string) $data['groep']);
        $data['groep_slug'] = PostLog::slugForGroep($data['groep']);
        $data['utm_source'] = trim((string) $data['utm_source']) ?: 'facebook';
        $data['link'] = isset($data['link']) ? trim((string) $data['link']) ?: null : null;
        $data['notitie'] = isset($data['notitie']) ? trim((string) $data['notitie']) ?: null : null;

        return $data;
    }
}
