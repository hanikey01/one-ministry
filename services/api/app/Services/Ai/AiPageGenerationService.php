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
    private const ACTIVE = ['queued', 'planning', 'composing', 'reviewing', 'repairing', 'compiling'];

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
        if (in_array($row->state, self::ACTIVE, true) || $row->state === 'failed') {
            $payload = json_decode(Crypt::decryptString($row->payload_encrypted), true, 512, JSON_THROW_ON_ERROR);
            $planned = count($payload['plan']['output']['sections'] ?? []);
            $completed = count($payload['sections'] ?? []);
            $data['progress'] = ['completed_sections' => $completed, 'total_sections' => $planned];
            if ($row->state === 'failed') $data['failure'] = array_slice($payload['diagnostics'] ?? [], -3);
        }
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
                $payload['result'] = $drafts->compileComposition($composition['output'], $actor, $payload['plan']['output'] ?? []) + [
                    'design_plan' => $payload['plan']['output'], 'design_review' => $payload['design_review']['issues'] ?? [],
                    'review_status' => $payload['design_review']['status'] ?? (isset($payload['design_review']) ? (($payload['design_review']['approved'] ?? false) ? 'approved' : 'repaired') : 'not_required'),
                    'provider' => $composition['provider'], 'model' => $composition['model'],
                    'fallback_used' => $composition['fallback_used'] || $payload['plan']['fallback_used'], 'trace_id' => $row->id,
                ];
                $payload = ['result' => $payload['result']];
                $update = ['state' => 'completed', 'completed_at' => now()];
            } elseif ($stage === 'reviewing') {
                DB::table('ai_page_generations')->where('id', $row->id)->where('lease_token', $row->lease_token)->update(['state' => $stage]);
                $review = $drafts->executeDesignReview($payload['input'], $actor, $payload['plan']['output'], $payload['sections'], intdiv($row->stage_attempt, 2), $row->id);
                $payload['design_review'] = $review['output'];
                $payload['design_review']['status'] = ($review['output']['approved'] ?? false) ? 'approved' : 'repair_requested';
                $payload['composition_meta']['fallback_used'] = ($payload['composition_meta']['fallback_used'] ?? false) || $review['fallback_used'];
                $repair = $review['output']['repair_section'] ?? null;
                $update = is_int($repair) && $repair >= 1 && $repair <= count($payload['sections']) && ! ($review['output']['approved'] ?? false)
                    ? ['state' => 'repairing', 'stage_attempt' => 0, 'available_at' => now()]
                    : ['state' => 'compiling', 'stage_attempt' => 0, 'available_at' => now()];
            } elseif ($stage === 'repairing') {
                DB::table('ai_page_generations')->where('id', $row->id)->where('lease_token', $row->lease_token)->update(['state' => $stage]);
                $sectionIndex = max(0, (int) ($payload['design_review']['repair_section'] ?? 1) - 1);
                $result = $drafts->executeSection($payload['input'], $actor, $payload['plan']['output'], $sectionIndex, $payload['sections'], intdiv($row->stage_attempt, 2), $row->id, (string) ($payload['design_review']['repair_instructions'] ?? 'Strengthen visual distinction and coherence.'));
                $payload['sections'][$sectionIndex] = $result['output'];
                $payload['design_review']['status'] = 'repaired';
                $payload['composition_meta'] = [
                    'provider' => $result['provider'], 'model' => $result['model'],
                    'fallback_used' => ($payload['composition_meta']['fallback_used'] ?? false) || $result['fallback_used'],
                ];
                $payload['composition'] = ['output' => $drafts->compositionFromSections($payload['input'], $payload['plan']['output'], $payload['sections']), ...$payload['composition_meta']];
                $update = ['state' => 'compiling', 'stage_attempt' => 0, 'available_at' => now()];
            } elseif ($stage === 'composing') {
                DB::table('ai_page_generations')->where('id', $row->id)->where('lease_token', $row->lease_token)->update(['state' => $stage]);
                $sections = is_array($payload['sections'] ?? null) ? $payload['sections'] : [];
                $sectionIndex = count($sections);
                // Generate and validate one section per durable request. Combining
                // multiple HTML/CSS sections into one strict JSON response caused
                // otherwise compatible models to return malformed JSON or a batch
                // that failed canonical canvas validation.
                $result = $drafts->executeSection($payload['input'], $actor, $payload['plan']['output'], $sectionIndex, $sections, intdiv($row->stage_attempt, 2), $row->id);
                $sections[] = $result['output'];
                $payload['sections'] = $sections;
                $payload['composition_meta'] = [
                    'provider' => $result['provider'], 'model' => $result['model'],
                    'fallback_used' => ($payload['composition_meta']['fallback_used'] ?? false) || $result['fallback_used'],
                ];
                if (count($sections) >= count($payload['plan']['output']['sections'])) {
                    $payload['composition'] = ['output' => $drafts->compositionFromSections($payload['input'], $payload['plan']['output'], $sections), ...$payload['composition_meta']];
                    $update = ['state' => 'reviewing', 'stage_attempt' => 0, 'available_at' => now()];
                } else {
                    $update = ['state' => 'composing', 'stage_attempt' => 0, 'available_at' => now()];
                }
            } else {
                DB::table('ai_page_generations')->where('id', $row->id)->where('lease_token', $row->lease_token)->update(['state' => $stage]);
                $result = $drafts->executeStage($stage, $payload['input'], $actor, null, [], intdiv($row->stage_attempt, 2), $row->id);
                $payload['plan'] = $result;
                $payload['sections'] = [];
                $update = ['state' => 'composing', 'stage_attempt' => 0, 'available_at' => now()];
            }
            $update['payload_encrypted'] = Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
            $update['failure_code'] = null;
        } catch (Throwable $exception) {
            if ($row->state === 'reviewing') {
                $payload['design_review'] = ['status' => 'unavailable', 'approved' => true, 'issues' => []];
                $update = [
                    'state' => 'compiling', 'stage_attempt' => 0, 'available_at' => now(), 'failure_code' => null,
                    'payload_encrypted' => Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR)),
                ];
            } else {
            $code = $exception instanceof AiProviderException ? $exception->diagnosticCode : ($exception instanceof ValidationException ? 'invalid_composition' : 'generation_failed');
            $attempt = $row->stage_attempt + 1;
            // A 120-second provider timeout is not a useful retry signal for the same
            // model. Advance to the configured fallback on the next durable attempt.
            if ($code === 'provider_timeout') {
                $attempt = max($attempt, 2);
            }
            if (! in_array($code, ['provider_timeout', 'provider_connection_failed', 'provider_rate_limited', 'provider_unavailable'], true) && $attempt % 2 === 1) {
                $attempt++;
            }
            $configuredAttempts = $this->configuredModelCount();
            $terminal = intdiv($attempt, 2) >= $configuredAttempts || $row->state === 'compiling' || ! ($exception instanceof AiProviderException);
            $retryAfter = $exception instanceof AiProviderException && $exception->retryAfterSeconds !== null
                ? $exception->retryAfterSeconds
                : min(120, 15 * (2 ** min(3, max(0, $attempt - 1))));
            $update = ['stage_attempt' => $attempt, 'failure_code' => $code, 'available_at' => now()->addSeconds($retryAfter)];
            $payload['diagnostics'][] = ['stage' => $row->state, 'code' => $code, 'attempt' => $attempt, 'recorded_at' => now()->toIso8601String()];
            if ($terminal) $update += ['state' => 'failed', 'completed_at' => now(), 'payload_encrypted' => Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR))];
            else $update['payload_encrypted'] = Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
            }
        }
        DB::table('ai_page_generations')->where('id', $row->id)->where('lease_token', $row->lease_token)->whereIn('state', self::ACTIVE)
            ->update($update + ['lease_token' => null, 'lease_expires_at' => null, 'updated_at' => now()]);

        return true;
    }

    private function configuredModelCount(): int
    {
        $route = DB::table('ai_task_routes')->where('task_key', 'page_editor')->first();
        if (! $route) {
            return 1;
        }
        $fallbacks = json_decode($route->fallback_models ?: '[]', true);

        return 1 + count(is_array($fallbacks) ? $fallbacks : []);
    }

    public function processOwned(string $id, User $actor): object
    {
        $this->owned($id, $actor);
        $this->processNext($id);

        return $this->owned($id, $actor);
    }
}
