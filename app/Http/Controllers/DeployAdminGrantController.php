<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Eenmalige, idempotente beheerder-toekenning vanuit de prod-deploy.
 *
 * De workflow stuurt alleen sha256(lowercase(trim(e-mail))) mee (GitHub-secret),
 * nooit het adres zelf. Alleen bij precies één match wordt users.is_admin gezet.
 * De response bevat alleen aantallen/vlaggen: geen e-mail, naam of id.
 */
class DeployAdminGrantController extends Controller
{
    private const RATE_MAX = 5;

    private const RATE_DECAY = 3600;

    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->endpointEnabled()) {
            return response()->json(['ok' => false, 'error' => 'disabled'], 403);
        }

        $expected = (string) config('stekjes.deploy_token', '');
        $given = (string) ($request->bearerToken() ?? '');

        if ($expected === '' || $given === '' || ! hash_equals($expected, $given)) {
            return response()->json(['ok' => false, 'error' => 'forbidden'], 403);
        }

        $throttleKey = 'deploy-admin-grant:'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, self::RATE_MAX)) {
            return response()->json(['ok' => false, 'error' => 'rate_limited'], 429);
        }
        RateLimiter::hit($throttleKey, self::RATE_DECAY);

        $hash = strtolower(trim((string) $request->input('email_sha256', '')));
        if (preg_match('/^[0-9a-f]{64}$/', $hash) !== 1) {
            return response()->json(['ok' => false, 'error' => 'invalid_hash'], 422);
        }

        /** @var list<int> $matches */
        $matches = [];
        User::query()->select(['id', 'email'])->chunkById(500, function ($users) use ($hash, &$matches): void {
            foreach ($users as $user) {
                $normalized = strtolower(trim((string) $user->email));
                if ($normalized !== '' && hash_equals($hash, hash('sha256', $normalized))) {
                    $matches[] = (int) $user->id;
                }
            }
        });

        $count = count($matches);
        $granted = false;
        $alreadyAdmin = false;
        $verified = null;

        if ($count === 1) {
            $user = User::query()->findOrFail($matches[0]);
            $verified = $user->hasVerifiedEmail();
            $alreadyAdmin = (bool) $user->is_admin;
            if (! $alreadyAdmin) {
                User::query()->whereKey($user->getKey())->update(['is_admin' => true]);
                $granted = true;
            }
        }

        return response()->json([
            'ok' => true,
            'matches' => $count,
            'granted' => $granted,
            'already_admin' => $alreadyAdmin,
            'email_verified' => $verified,
        ]);
    }

    private function endpointEnabled(): bool
    {
        if (app()->environment('staging')) {
            return true;
        }

        return (bool) config('stekjes.deploy_endpoint_enabled', false);
    }
}
