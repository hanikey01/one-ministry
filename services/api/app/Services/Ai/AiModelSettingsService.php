<?php

namespace App\Services\Ai;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class AiModelSettingsService
{
    public function __construct(private readonly AiProviderRegistry $providers) {}

    /** @return array<string, mixed> */
    public function overview(): array
    {
        $providers = [];
        foreach (AiProviderRegistry::labels() as $key => $label) {
            $row = DB::table('ai_provider_connections')->where('provider_key', $key)->first();
            $providers[$key] = ['label' => $label, 'enabled' => (bool) ($row->enabled ?? false), 'configured' => filled($row->api_key_encrypted ?? null), 'status' => $row->connection_status ?? 'unconfigured', 'last_checked_at' => $row->last_checked_at ?? null, 'model_count' => DB::table('ai_model_catalog')->where('provider_key', $key)->where('availability', 'available')->count(), 'error_code' => $row->last_error_code ?? null];
        }
        $route = DB::table('ai_task_routes')->where('task_key', 'page_editor')->first();
        $selectedModels = [];
        if ($route?->primary_provider && $route?->primary_model) {
            $selectedModels[] = ['provider_key' => $route->primary_provider, 'model_id' => $route->primary_model];
        }
        foreach (json_decode($route->fallback_models ?? '[]', true) ?: [] as $fallback) {
            if (is_array($fallback) && is_string($fallback['provider'] ?? null) && filled($fallback['provider']) && is_string($fallback['model_id'] ?? null) && filled($fallback['model_id'])) {
                $selectedModels[] = ['provider_key' => $fallback['provider'], 'model_id' => $fallback['model_id']];
            }
        }
        $models = collect();
        if ($selectedModels !== []) {
            $models = DB::table('ai_model_catalog')->where('availability', 'available')->where(function ($query) use ($selectedModels): void {
                foreach ($selectedModels as $selected) {
                    $query->orWhere(fn ($choice) => $choice->where('provider_key', $selected['provider_key'])->where('model_id', $selected['model_id']));
                }
            })->get(['provider_key', 'model_id', 'display_name', 'supports_structured_output', 'input_modalities', 'output_modalities']);
        }

        return ['providers' => $providers, 'models' => $models, 'route' => $route];
    }

    /** @return array{models: array<int, array{provider_key:string,model_id:string,display_name:string,label:string,search_text:string}>, has_more: bool} */
    public function searchModelChoices(string $search, int $limit = 50): array
    {
        $enabledProviders = DB::table('ai_provider_connections')->where('enabled', true)->pluck('provider_key')->all();
        if ($enabledProviders === []) return ['models' => [], 'has_more' => false];

        $labels = AiProviderRegistry::labels();
        $query = DB::table('ai_model_catalog')->whereIn('provider_key', $enabledProviders)
            ->where('availability', 'available')->where('supports_structured_output', true);
        $term = mb_strtolower(trim($search));
        if ($term !== '') {
            $pattern = '%'.$term.'%';
            $providerMatches = collect($labels)->filter(fn (string $label): bool => str_contains(mb_strtolower($label), $term))->keys()->all();
            $query->where(function ($match) use ($pattern, $providerMatches): void {
                $match->whereRaw('LOWER(display_name) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(model_id) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(provider_key) LIKE ?', [$pattern]);
                if ($providerMatches !== []) $match->orWhereIn('provider_key', $providerMatches);
            });
        }

        $rows = $query->orderBy('provider_key')->orderBy('display_name')->limit(250)->get([
            'provider_key', 'model_id', 'display_name', 'input_modalities', 'output_modalities',
        ]);
        $eligible = $rows->filter(function ($model): bool {
            $inputs = json_decode($model->input_modalities ?: '[]', true);
            $outputs = json_decode($model->output_modalities ?: '[]', true);
            return is_array($inputs) && in_array('text', $inputs, true)
                && is_array($outputs) && in_array('text', $outputs, true);
        })->values();
        $models = $eligible->take($limit)->map(function ($model) use ($labels): array {
            $provider = $labels[$model->provider_key] ?? $model->provider_key;
            return [
                'provider_key' => $model->provider_key,
                'model_id' => $model->model_id,
                'display_name' => $model->display_name,
                'label' => $provider.' · '.$model->display_name.' ('.$model->model_id.')',
                'search_text' => mb_strtolower($provider.' '.$model->display_name.' '.$model->model_id),
            ];
        })->all();

        return ['models' => $models, 'has_more' => $eligible->count() > $limit || $rows->count() === 250];
    }

    public function updateProvider(string $provider, array $data, User $actor, ?string $ip): void
    {
        $this->assertProvider($provider);
        $current = DB::table('ai_provider_connections')->where('provider_key', $provider)->first();
        $clearCredential = (bool) ($data['clear_api_key'] ?? false);
        if ($clearCredential && (bool) ($data['enabled'] ?? false)) throw ValidationException::withMessages(['api_key' => 'Disable this provider before removing its saved API key.']);
        $encrypted = $clearCredential ? null : (filled($data['api_key'] ?? null) ? Crypt::encryptString($data['api_key']) : ($current->api_key_encrypted ?? null));
        $enabled = (bool) ($data['enabled'] ?? false);
        if ($enabled && blank($encrypted)) throw ValidationException::withMessages(['api_key' => 'Add an API key before enabling this provider.']);

        $credentialChanged = filled($data['api_key'] ?? null) || $clearCredential;
        DB::transaction(function () use ($provider, $encrypted, $enabled, $actor, $ip, $current, $credentialChanged): void {
            DB::table('ai_provider_connections')->updateOrInsert(['provider_key' => $provider], [
                'api_key_encrypted' => $encrypted, 'enabled' => $enabled,
                'connection_status' => filled($encrypted) ? 'unverified' : 'unconfigured', 'last_checked_at' => null,
                'last_error_code' => null, 'updated_by' => $actor->id, 'created_at' => $current->created_at ?? now(), 'updated_at' => now(),
            ]);
            $this->audit($actor, 'ai.provider.updated', $provider, ['before' => ['enabled' => (bool) ($current->enabled ?? false), 'configured' => filled($current->api_key_encrypted ?? null)], 'after' => ['enabled' => $enabled, 'configured' => filled($encrypted), 'credential_changed' => $credentialChanged], 'ip' => $ip]);
            if ($current && ($credentialChanged || ((bool) $current->enabled && ! $enabled))) {
                $this->invalidateRoutesUsingProvider($provider);
            }
        });
    }

    /** @return array{seen:int, unavailable:int} */
    public function refreshCatalog(string $provider, ?User $actor, ?string $ip): array
    {
        $this->assertProvider($provider);
        $connection = DB::table('ai_provider_connections')->where('provider_key', $provider)->first();
        if (! $connection || ! $connection->enabled || blank($connection->api_key_encrypted)) throw ValidationException::withMessages(['provider' => 'Save and enable this provider before refreshing its model catalog.']);
        try {
            $models = $this->providers->get($provider)->discoverModels(Crypt::decryptString($connection->api_key_encrypted));
            $models = array_values(array_filter($models, fn ($model): bool => is_array($model)
                && is_string($model['model_id'] ?? null) && $model['model_id'] !== '' && strlen($model['model_id']) <= 190));
            if ($models === []) throw new RuntimeException('empty_catalog');
        } catch (Throwable $exception) {
            DB::table('ai_provider_connections')->where('provider_key', $provider)->update(['connection_status' => 'error', 'last_checked_at' => now(), 'last_error_code' => 'catalog_refresh_failed', 'updated_at' => now()]);
            throw new RuntimeException('The model catalog could not be refreshed. The previous catalog was kept.', previous: $exception);
        }

        $now = now();
        DB::transaction(function () use ($provider, $models, $now, $actor, $ip): void {
            DB::table('ai_model_catalog')->where('provider_key', $provider)->update(['availability' => 'unavailable', 'updated_at' => $now]);
            foreach ($models as $model) {
                if (! is_string($model['model_id'] ?? null) || $model['model_id'] === '' || strlen($model['model_id']) > 190) continue;
                $key = ['provider_key' => $provider, 'model_id' => $model['model_id']];
                $values = [
                    'display_name' => Str::limit((string) $model['display_name'], 255, ''), 'context_window' => $model['context_window'],
                    'supports_structured_output' => $model['supports_structured_output'], 'input_modalities' => json_encode($model['input_modalities'], JSON_THROW_ON_ERROR),
                    'output_modalities' => json_encode($model['output_modalities'], JSON_THROW_ON_ERROR), 'metadata' => json_encode($model['metadata'], JSON_THROW_ON_ERROR),
                    'availability' => 'available', 'last_seen_at' => $now, 'updated_at' => $now,
                ];
                if (DB::table('ai_model_catalog')->where($key)->exists()) {
                    DB::table('ai_model_catalog')->where($key)->update($values);
                } else {
                    DB::table('ai_model_catalog')->insert([...$key, 'id' => (string) Str::uuid(), ...$values, 'created_at' => $now]);
                }
            }
            DB::table('ai_provider_connections')->where('provider_key', $provider)->update(['connection_status' => 'connected', 'last_checked_at' => $now, 'last_error_code' => null, 'updated_at' => $now]);
            $this->invalidateRoutesUsingUnavailableModels($actor, $ip);
            $this->audit($actor, 'ai.catalog.refreshed', $provider, ['seen' => count($models), 'source' => $actor ? 'staff' : 'scheduler', 'ip' => $ip]);
        });

        return ['seen' => count($models), 'unavailable' => DB::table('ai_model_catalog')->where('provider_key', $provider)->where('availability', 'unavailable')->count()];
    }

    /** @param array<int, string> $fallbackSelections */
    public function savePageEditorRoute(string $primarySelection, array $fallbackSelections, User $actor, ?string $ip): void
    {
        $primary = $this->parseSelection($primarySelection);
        if (count($fallbackSelections) < 1 || count($fallbackSelections) > 2) throw ValidationException::withMessages(['fallbacks' => 'Choose one or two fallback models.']);
        $fallbacks = array_map(fn (string $choice): array => $this->parseSelection($choice), $fallbackSelections);
        $all = [$primary, ...$fallbacks];
        $keys = array_map(fn (array $item): string => $item['provider'].'|'.$item['model_id'], $all);
        if (count(array_unique($keys)) !== count($keys)) throw ValidationException::withMessages(['fallbacks' => 'Each fallback must be unique and different from the primary model.']);
        foreach ($all as $model) {
            $eligible = $this->isModelRouteCompatible($model['provider'], $model['model_id']);
            $enabled = DB::table('ai_provider_connections')->where('provider_key', $model['provider'])->where('enabled', true)->exists();
            if (! $eligible || ! $enabled) throw ValidationException::withMessages(['primary_model' => 'Every route model must be available, support structured output, and have its provider enabled. Refresh the catalog and choose another model.']);
        }
        $before = DB::table('ai_task_routes')->where('task_key', 'page_editor')->first();
        DB::transaction(function () use ($primary, $fallbacks, $actor, $ip, $before): void {
            DB::table('ai_task_routes')->updateOrInsert(['task_key' => 'page_editor'], [
                'primary_provider' => $primary['provider'], 'primary_model' => $primary['model_id'], 'fallback_models' => json_encode($fallbacks, JSON_THROW_ON_ERROR),
                'status' => 'unverified', 'last_verified_at' => null, 'updated_by' => $actor->id, 'created_at' => $before->created_at ?? now(), 'updated_at' => now(),
            ]);
            $this->audit($actor, 'ai.page_editor_route.updated', 'page_editor', ['before' => $before ? ['provider' => $before->primary_provider, 'model' => $before->primary_model, 'fallbacks' => json_decode($before->fallback_models ?: '[]', true)] : null, 'after' => ['provider' => $primary['provider'], 'model' => $primary['model_id'], 'fallbacks' => $fallbacks], 'ip' => $ip]);
        });
    }

    /** @return array{model:string, latency_ms:int} */
    public function testPageEditorRoute(User $actor, ?string $ip): array
    {
        $route = DB::table('ai_task_routes')->where('task_key', 'page_editor')->first();
        if (! $route || ! $route->primary_model) throw ValidationException::withMessages(['route' => 'Save a primary model and at least one fallback before testing.']);
        $fallbacks = json_decode($route->fallback_models ?: '[]', true);
        if (! is_array($fallbacks) || count($fallbacks) < 1 || count($fallbacks) > 2) throw ValidationException::withMessages(['route' => 'Choose one or two fallback models and save the route again.']);
        $choices = [['provider' => $route->primary_provider, 'model_id' => $route->primary_model], ...(is_array($fallbacks) ? $fallbacks : [])];
        $results = [];
        try {
            foreach ($choices as $choice) {
                $connection = DB::table('ai_provider_connections')->where('provider_key', $choice['provider'])->where('enabled', true)->first();
                if (! $connection || blank($connection->api_key_encrypted)) throw new RuntimeException('provider_not_ready');
                $results[] = ['provider' => $choice['provider'], ...$this->providers->get($choice['provider'])->validateStructuredOutput(Crypt::decryptString($connection->api_key_encrypted), $choice['model_id'])];
            }
        } catch (Throwable $exception) {
            DB::transaction(function () use ($actor, $ip): void {
                DB::table('ai_task_routes')->where('task_key', 'page_editor')->update(['status' => 'unverified', 'last_verified_at' => null, 'updated_at' => now()]);
                $this->audit($actor, 'ai.page_editor_route.test_failed', 'page_editor', ['failed' => true, 'ip' => $ip]);
            });
            throw new RuntimeException('At least one selected model failed the structured-output check. The route remains inactive; review the selection and try again.', previous: $exception);
        }
        DB::transaction(function () use ($actor, $ip, $results): void {
            DB::table('ai_task_routes')->where('task_key', 'page_editor')->update(['status' => 'verified', 'last_verified_at' => now(), 'updated_at' => now()]);
            $this->audit($actor, 'ai.page_editor_route.test_passed', 'page_editor', ['models' => array_map(fn (array $result): array => ['provider' => $result['provider'], 'model' => $result['model'], 'latency_ms' => $result['latency_ms']], $results), 'ip' => $ip]);
        });

        return ['models' => count($results), 'latency_ms' => array_sum(array_column($results, 'latency_ms'))];
    }

    /** @return array{provider:string,model_id:string} */
    private function parseSelection(string $value): array
    {
        [$provider, $model] = array_pad(explode('|', $value, 2), 2, '');
        $this->assertProvider($provider);
        if (blank($model) || strlen($model) > 190) throw ValidationException::withMessages(['primary_model' => 'Choose a valid model from the refreshed catalog.']);

        return ['provider' => $provider, 'model_id' => $model];
    }

    private function assertProvider(string $provider): void
    {
        if (! array_key_exists($provider, AiProviderRegistry::labels())) throw ValidationException::withMessages(['provider' => 'Choose Gemini or OpenRouter.']);
    }

    private function invalidateRoutesUsingUnavailableModels(?User $actor, ?string $ip): void
    {
        $route = DB::table('ai_task_routes')->where('task_key', 'page_editor')->first();
        if (! $route || ! $route->primary_model) return;
        $fallbacks = json_decode($route->fallback_models ?: '[]', true);
        $selected = [['provider' => $route->primary_provider, 'model_id' => $route->primary_model], ...(is_array($fallbacks) ? $fallbacks : [])];
        foreach ($selected as $choice) {
            if (! is_array($choice) || ! $this->isModelRouteCompatible((string) ($choice['provider'] ?? ''), (string) ($choice['model_id'] ?? ''))) {
                if ($route->status !== 'unavailable') {
                    DB::table('ai_task_routes')->where('task_key', 'page_editor')->update(['status' => 'unavailable', 'last_verified_at' => null, 'updated_at' => now()]);
                    $this->audit($actor, 'ai.page_editor_route.unavailable', 'page_editor', ['unavailable_model' => ['provider' => $choice['provider'] ?? null, 'model' => $choice['model_id'] ?? null], 'ip' => $ip]);
                }
                break;
            }
        }
    }

    private function isModelRouteCompatible(string $provider, string $modelId): bool
    {
        $model = DB::table('ai_model_catalog')->where('provider_key', $provider)->where('model_id', $modelId)->where('availability', 'available')->first();
        if (! $model || ! $model->supports_structured_output) return false;
        $inputs = json_decode($model->input_modalities ?: '[]', true);
        $outputs = json_decode($model->output_modalities ?: '[]', true);

        return is_array($inputs) && in_array('text', $inputs, true) && is_array($outputs) && in_array('text', $outputs, true);
    }

    private function invalidateRoutesUsingProvider(string $provider): void
    {
        foreach (DB::table('ai_task_routes')->where('status', 'verified')->get() as $route) {
            $fallbacks = json_decode($route->fallback_models ?: '[]', true) ?: [];
            $usesProvider = $route->primary_provider === $provider || collect($fallbacks)->contains(fn ($model): bool => is_array($model) && ($model['provider'] ?? null) === $provider);
            if ($usesProvider) {
                DB::table('ai_task_routes')->where('task_key', $route->task_key)->update(['status' => 'unverified', 'last_verified_at' => null, 'updated_at' => now()]);
            }
        }
    }

    /** @param array<string, mixed> $changes */
    private function audit(?User $actor, string $action, string $subject, array $changes): void
    {
        $ip = $changes['ip'] ?? null;
        unset($changes['ip']);
        DB::table('ai_configuration_audits')->insert(['id' => (string) Str::uuid(), 'actor_user_id' => $actor?->id, 'action' => $action, 'subject_type' => 'ai_settings', 'subject_id' => $subject, 'changes_json' => json_encode($changes, JSON_THROW_ON_ERROR), 'ip_address' => $ip, 'created_at' => now()]);
    }
}
