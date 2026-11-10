<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Beheer\PostLogController;
use App\Http\Controllers\Controller;
use App\Models\PostLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Interne mint-API voor plantjes + Grok Bot: post_logs-rij + tracking_url.
 * Auth: header X-Post-Log-Token (config stekjes.post_log_mint_token).
 */
class PostLogMintController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $expected = (string) config('stekjes.post_log_mint_token', '');
        $given = (string) $request->header('X-Post-Log-Token', '');

        if ($expected === '' || $given === '' || ! hash_equals($expected, $given)) {
            return response()->json(['ok' => false, 'error' => 'forbidden'], 403);
        }

        if (! Schema::hasTable('post_logs')) {
            return response()->json(['ok' => false, 'error' => 'unavailable'], 503);
        }

        $input = $request->all();
        $input['titel'] = trim((string) ($input['titel'] ?? ''));
        $input['groep'] = trim((string) ($input['groep'] ?? ''));
        if (array_key_exists('target_path', $input) && is_string($input['target_path'])) {
            $input['target_path'] = trim($input['target_path']);
        }

        $validator = Validator::make($input, [
            'titel' => ['required', 'string', 'max:200'],
            'groep' => ['required', 'string', 'max:120'],
            'soort' => ['required', 'string', Rule::in(PostLogController::SOORTEN)],
            'utm_source' => ['sometimes', 'nullable', 'string', 'max:40', 'regex:/^[a-z0-9_-]+$/i'],
            'notitie' => ['sometimes', 'nullable', 'string', 'max:500'],
            'posted_on' => ['sometimes', 'nullable', 'date'],
            'link' => ['sometimes', 'nullable', 'string', 'max:500', 'url'],
            'target_path' => ['sometimes', 'nullable', 'string', 'max:'.PostLog::TARGET_PATH_MAX],
        ], [
            'target_path.max' => 'target_path mag maximaal '.PostLog::TARGET_PATH_MAX.' tekens zijn.',
        ]);

        $validator->after(function ($validator) use ($input): void {
            if (! array_key_exists('target_path', $input) || $input['target_path'] === null) {
                return;
            }
            if (! is_string($input['target_path'])) {
                $validator->errors()->add(
                    'target_path',
                    'target_path moet een intern pad zijn dat met / begint (bijv. /tuinverenigingen).'
                );

                return;
            }
            $raw = trim($input['target_path']);
            if ($raw === '') {
                return;
            }
            if (PostLog::normalizeTargetPath($raw) === null) {
                $validator->errors()->add(
                    'target_path',
                    'target_path moet een intern pad zijn dat met / begint (geen //, schema of host); bijv. /tuinverenigingen of /stektips/kiwibes.'
                );
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $titel = (string) $data['titel'];
        $groep = (string) $data['groep'];
        $utmSource = isset($data['utm_source']) ? trim((string) $data['utm_source']) : '';
        if ($utmSource === '') {
            $utmSource = 'facebook';
        }

        $targetPath = null;
        if (isset($data['target_path'])) {
            $targetPath = PostLog::normalizeTargetPath((string) $data['target_path']);
        }

        $post = PostLog::query()->create([
            'posted_on' => $data['posted_on'] ?? now()->toDateString(),
            'titel' => $titel,
            'groep' => $groep,
            'groep_slug' => PostLog::slugForGroep($groep),
            'soort' => $data['soort'],
            'link' => isset($data['link']) ? (trim((string) $data['link']) ?: null) : null,
            'utm_source' => $utmSource,
            'target_path' => $targetPath,
            'notitie' => isset($data['notitie']) ? (trim((string) $data['notitie']) ?: null) : null,
        ]);

        $trackingUrl = $post->trackingUrl();
        $shortUrl = $post->shortUrl();

        return response()->json([
            'ok' => true,
            'id' => $post->id,
            'titel' => $post->titel,
            'tracking_url' => $trackingUrl,
            'short_url' => $shortUrl,
            'short_code' => $post->short_code,
            'target_path' => $post->resolvedTargetPath(),
            'utm' => [
                'source' => $post->utm_source ?: 'facebook',
                'medium' => 'social',
                'campaign' => $post->groep_slug,
                'content' => (string) $post->id,
            ],
            'groep' => $post->groep,
            'soort' => $post->soort,
            'posted_on' => $post->posted_on?->toDateString(),
        ], 201);
    }
}
