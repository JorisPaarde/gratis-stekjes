<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Aanbod;
use App\Models\Conversation;
use App\Models\Ervaring;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class ErvaringService
{
    public const MAX_BODY = 500;

    public const MAX_PLAATS = 80;

    public function __construct(
        private readonly SafeImageUpload $images,
        private readonly Plantensoorten $plantensoorten,
    ) {}

    /**
     * @param  array{body: string, plaatsnaam?: string|null, plantensoort_id?: int|null, aanbod_id?: int|null, conversation_id?: int|null}  $data
     * @return array{ok: true, ervaring: Ervaring}|array{ok: false, error: string, field?: string}
     */
    public function create(User $user, array $data, ?UploadedFile $photo = null): array
    {
        $body = trim($data['body']);
        if ($body === '' || mb_strlen($body) > self::MAX_BODY) {
            return ['ok' => false, 'error' => 'Schrijf een korte ervaring (max. '.self::MAX_BODY.' tekens).', 'field' => 'body'];
        }

        $plaats = $this->resolvePlaatsnaam($user, $data['plaatsnaam'] ?? null);
        if ($plaats === null) {
            return [
                'ok' => false,
                'error' => 'Gebruik alleen een plaats- of gemeentennaam (geen straatadres). Vul anders eerst je locatie in je profiel in.',
                'field' => 'plaatsnaam',
            ];
        }

        $plantensoortId = null;
        $rawSoort = $data['plantensoort_id'] ?? null;
        if ($rawSoort !== null && $rawSoort !== '') {
            $plantensoortId = (int) $rawSoort;
            if ($plantensoortId < 1 || ! $this->plantensoorten->exists($plantensoortId)) {
                return ['ok' => false, 'error' => 'Deze plantensoort ken ik niet.', 'field' => 'plantensoort_id'];
            }
        }

        $aanbodId = null;
        $rawAanbod = $data['aanbod_id'] ?? null;
        if ($rawAanbod !== null && $rawAanbod !== '') {
            $aanbodId = (int) $rawAanbod;
            $owns = Aanbod::query()->whereKey($aanbodId)->where('user_id', $user->id)->exists();
            if (! $owns) {
                return ['ok' => false, 'error' => 'Dit aanbod hoort niet bij jou.', 'field' => 'aanbod_id'];
            }
        }

        $conversationId = null;
        $rawConv = $data['conversation_id'] ?? null;
        if ($rawConv !== null && $rawConv !== '') {
            $conversationId = (int) $rawConv;
            $conv = Conversation::query()->find($conversationId);
            if ($conv === null || ! $conv->involves((int) $user->id)) {
                return ['ok' => false, 'error' => 'Dit gesprek hoort niet bij jou.', 'field' => 'conversation_id'];
            }
        }

        $photoPath = null;
        if ($photo !== null) {
            $stored = $this->images->store($photo, 'ervaringen/'.$user->id);
            if (! $stored['ok']) {
                return ['ok' => false, 'error' => $stored['error'], 'field' => 'foto'];
            }
            $photoPath = $stored['path'];
        }

        $ervaring = Ervaring::query()->create([
            'user_id' => $user->id,
            'body' => $body,
            'photo_path' => $photoPath,
            'plantensoort_id' => $plantensoortId,
            'aanbod_id' => $aanbodId,
            'conversation_id' => $conversationId,
            'plaatsnaam' => $plaats,
            'status' => Ervaring::STATUS_PENDING,
        ]);

        return ['ok' => true, 'ervaring' => $ervaring];
    }

    public function deleteOwned(User $user, Ervaring $ervaring): bool
    {
        if ((int) $ervaring->user_id !== (int) $user->id) {
            return false;
        }

        return $this->deleteErvaring($ervaring);
    }

    public function deleteErvaring(Ervaring $ervaring): bool
    {
        return (bool) DB::transaction(function () use ($ervaring) {
            $path = $ervaring->photo_path;
            $ervaring->delete();
            $this->images->delete($path);

            return true;
        });
    }

    public function approve(Ervaring $ervaring): void
    {
        if ($ervaring->isApproved()) {
            return;
        }
        $ervaring->update([
            'status' => Ervaring::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
    }

    public function reject(Ervaring $ervaring): void
    {
        $ervaring->update([
            'status' => Ervaring::STATUS_REJECTED,
            'approved_at' => null,
        ]);
    }

    /**
     * Plaatsnaam op gemeenteniveau: profiel-label of vrije tekst zonder straat/huisnummer-patronen.
     */
    public function resolvePlaatsnaam(User $user, mixed $raw): ?string
    {
        $candidate = trim((string) ($raw ?? ''));
        if ($candidate === '') {
            $candidate = trim((string) ($user->locatie_label ?? ''));
        }
        if ($candidate === '') {
            return null;
        }
        if (mb_strlen($candidate) > self::MAX_PLAATS) {
            $candidate = mb_substr($candidate, 0, self::MAX_PLAATS);
        }

        if (! $this->isGemeenteLevel($candidate)) {
            return null;
        }

        return $candidate;
    }

    public function isGemeenteLevel(string $value): bool
    {
        $v = trim($value);
        if ($v === '' || mb_strlen($v) > self::MAX_PLAATS) {
            return false;
        }

        // Straatnaam + huisnummer → afwijzen.
        if (preg_match('/\b(straat|laan|weg|pad|plein|singel|gracht|kade|dreef|hof|steeg)\b/iu', $v)
            && preg_match('/\d/u', $v)) {
            return false;
        }

        // Plaats + los huisnummer (bijv. "Haarlem 12") → afwijzen.
        // Postcode-4 (+ letters) + plaats mag: "2011 Haarlem", "2011AB Haarlem".
        if (preg_match('/\d/u', $v) && ! preg_match('/^\d{4}\s*[A-Za-z]{0,2}\b/u', $v)) {
            return false;
        }

        return true;
    }
}
