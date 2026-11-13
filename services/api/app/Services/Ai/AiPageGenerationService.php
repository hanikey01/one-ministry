<?php

namespace App\Services\Ai;

use App\Models\FeatureSetting;
use App\Models\User;
use App\Services\ModuleAvailability;
use App\Services\PermissionChecker;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class AiPageGenerationService
{
    private const ACTIVE = ['queued', 'planning', 'composing', 'compiling'];

    public function enqueue(array $input, User $actor, string $key): object
    {
        abort_unless(app(PermissionChecker::class)->allows($actor, 'content.manage'), 403);
        abort_unless(FeatureSetting::where('key', 'ai_page_editor')->value('state') === 'enabled' || app(ModuleAvailability::class)->visibleToStaff('ai_page_editor', $actor), 404);
        $hash = hash('sha256', json_encode($input, JSON_THROW_ON_ERROR));
        DB::table('ai_page_generations')->insertOrIgnore([
            'id' => (string) Str::uuid(), 'actor_user_id' => $actor->id, 'idempotency_key' => $key, 'request_hash' => $hash,
            'state' => 'queued', 'payload_encrypted' => Crypt::encryptString(json_encode(['input' => $input], JSON_THROW_ON_ERROR)),
            'available_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $row = DB::table('ai_page_generations')->where('actor_user_id', $actor->id)->where('idempotency_key', $key)->firstOrFail();
        if ($row->request_hash !== $hash) {
            throw ValidationException::withMessages(['idempotency_key' => 'This request key was already used for another brief.']);
        }

        return $row;
    }

    public function owned(string $id, User $actor): object
    {
        abort_unless(app(PermissionChecker::class)->allows($actor, 'content.manage'), 403);

        return DB::table('ai_page_generations')->where('id', $id)->where('actor_user_id', $actor->id)->firstOrFail();
    }

    public function view(object $row): array
    {
        $data = ['id' => $row->id, 'state' => $row->state, 'trace_id' => $row->id, 'failure_code' => $row->failure_code,
            'poll_url' => route('staff.pages.ai-generation.show', $row->id), 'cancel_url' => route('staff.pages.ai-generation.cancel', $row->id)];
        $data['process_url'] = route('staff.pages.ai-generation.process', $row->id);
        $availableAt = isset($row->available_at) ? strtotime((string) $row->available_at) : false;
        $data['retry_after_ms'] = $availableAt === false ? 0 : max(0, ($availableAt - time()) * 1000);
        if ($row->state === 'completed') {
            $payload = json_decode(Crypt::decryptString($row->payload_encrypted), true, 512, JSON_THROW_ON_ERROR);
            $data['proposal'] = $payload['result'];
            $data['proposal']['slug'] = Str::slug($payload['result']['title']) ?: 'page';
        }

        return $data;
    }

    public function cancel(string $id, User $actor): void
    {
        $this->owned($id, $actor);
        DB::table('ai_page_generations')->where('id', $id)->whereIn('state', self::ACTIVE)->update(['state' => 'cancelled', 'lease_token' => null, 'lease_expires_at' => null, 'payload_encrypted' => Crypt::encryptString('{}'), 'completed_at' => now(), 'updated_at' => now()]);
    }

    /** Host cron processes one bounded stage/attempt; no worker daemon is required. */
    public function processNext(?string $generationId = null): bool
    {
        $row = DB::transaction(function () use ($generationId): ?object {
            $query = DB::table('ai_page_generations')->whereIn('state', self::ACTIVE)->where('available_at', '<=', now())
                ->where(fn ($q) => $q->whereNull('lease_token')->orWhere('lease_expires_at', '<=', now()));
            if ($generationId !== null) {
                $query->where('id', $generationId);
            }
            $row = $query->orderBy('available_at')->lockForUpdate()->first();
            if (! $row) {
                return null;
            }
            if ($row->lease_token !== null) {
                $row->stage_attempt++;
                if ($row->stage_attempt >= 6) {
                    DB::table('ai_page_generations')->where('id', $row->id)->update(['state' => 'failed', 'failure_code' => 'stale_attempts_exhausted', 'completed_at' => now(), 'payload_encrypted' => Crypt::encryptString('{}')]);

                    return null;
                }
            }
            $token = (string) Str::uuid();
            $changed = DB::table('ai_page_generations')->where('id', $row->id)
                ->where(fn ($q) => $q->whereNull('lease_token')->orWhere('lease_expires_at', '<=', now()))
                ->update(['lease_token' => $token, 'lease_expires_at' => now()->addSeconds(180), 'stage_attempt' => $row->stage_attempt, 'updated_at' => now()]);
            if (! $changed) {
                return null;
            }
            $row->lease_token = $token;

            return $row;
        });
        if (! $row) {
            return false;
        }
        $update = [];
        try {
            $actor = User::findOrFail($row->actor_user_id);
            abort_unless(app(PermissionChecker::class)->allows($actor, 'content.manage'), 403);
            $payload = json_decode(Crypt::decryptString($row->payload_encrypted), true, 512, JSON_THROW_ON_ERROR);
            $drafts = app(AiPageDraftService::class);
            $stage = $row->state === 'queued' ? 'planning' : $row->state;
            if ($stage === 'compiling') {
                $composition = $payload['composition'];
                $review = ! empty($payload['input']['review'])
                    ? $drafts->reviewComposition($composition['output'], $payload['plan']['output'], $actor, $composition, $row->id)
                    : ['composition' => $composition['output'], 'findings' => [], 'status' => 'skipped'];
                $payload['result'] = $drafts->compileComposition($review['composition'], $actor) + [
                    'design_plan' => $payload['plan']['output'], 'design_review' => $review['findings'], 'review_status' => $review['status'], 'provider' => $composition['provider'], 'model' => $composition['model'],
                    'fallback_used' => $composition['fallback_used'] || $payload['plan']['fallback_used'], 'trace_id' => $row->id,
                ];
                $payload = ['result' => $payload['result']];
                $update = ['state' => 'completed', 'completed_at' => now()];
            } else {
                DB::table('ai_page_generations')->where('id', $row->id)->where('lease_token', $row->lease_token)->update(['state' => $stage]);
                $result = $drafts->executeStage($stage, $payload['input'], $actor, null, $payload['plan']['output'] ?? [], intdiv($row->stage_attempt, 2), $row->id);
                $payload[$stage === 'planning' ? 'plan' : 'composition'] = $result;
                $update = ['state' => $stage === 'planning' ? 'composing' : 'compiling', 'stage_attempt' => 0];
            }
            $update['payload_encrypted'] = Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
            $update['failure_code'] = null;
        } catch (Throwable $exception) {
            $code = $exception instanceof AiProviderException ? $exception->diagnosticCode : ($exception instanceof ValidationException ? 'invalid_composition' : 'generation_failed');
            $attempt = $row->stage_attempt + 1;
            if (! in_array($code, ['provider_timeout', 'provider_connection_failed', 'provider_rate_limited', 'provider_unavailable'], true) && $attempt % 2 === 1) {
                $attempt++;
            }
            $terminal = $attempt >= 6 || $row->state === 'compiling' || ! ($exception instanceof AiProviderException);
            $retryAfter = $exception instanceof AiProviderException && $exception->retryAfterSeconds !== null
                ? $exception->retryAfterSeconds
                : min(120, 15 * (2 ** min(3, max(0, $attempt - 1))));
            $update = ['stage_attempt' => $attempt, 'failure_code' => $code, 'available_at' => now()->addSeconds($retryAfter)];
            if ($terminal) {
                $update += ['state' => 'failed', 'completed_at' => now(), 'payload_encrypted' => Crypt::encryptString('{}')];
            }
        }
        DB::table('ai_page_generations')->where('id', $row->id)->where('lease_token', $row->lease_token)->whereIn('state', self::ACTIVE)
            ->update($update + ['lease_token' => null, 'lease_expires_at' => null, 'updated_at' => now()]);

        return true;
    }

    public function processOwned(string $id, User $actor): object
    {
        $this->owned($id, $actor);
        $this->processNext($id);

        return $this->owned($id, $actor);
    }
}
