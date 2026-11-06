<?php

namespace App\Http\Controllers;

use App\Models\MinistrySetting;
use App\Services\Ai\AiModelSettingsService;
use App\Services\Ai\AiProviderRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class StaffAiSettingsController extends Controller
{
    public function index(Request $request, AiModelSettingsService $ai): View
    {
        $overview = $ai->overview();
        return view('staff.ai-settings', $overview + [
            'settings' => MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID),
            'user' => $request->user(),
            'providerLabels' => AiProviderRegistry::labels(),
            'fallbackSelections' => $this->fallbackSelections($overview['route'] ?? null),
        ]);
    }

    public function searchModels(Request $request, AiModelSettingsService $ai): JsonResponse
    {
        $validated = Validator::make($request->query(), ['q' => ['nullable', 'string', 'max:120']])->validate();
        try {
            return response()->json(['data' => $ai->searchModelChoices((string) ($validated['q'] ?? ''))])
                ->header('Cache-Control', 'private, no-store');
        } catch (Throwable $exception) {
            Log::error('Saved AI model catalog could not be searched.', [
                'actor_user_id' => $request->user()?->id,
                'exception' => $exception,
            ]);

            return response()->json([
                'message' => 'The saved model catalog could not be loaded. Refresh the provider catalog and try again.',
                'data' => ['models' => [], 'has_more' => false],
            ], 503);
        }
    }

    public function updateProvider(Request $request, string $provider, AiModelSettingsService $ai): RedirectResponse|JsonResponse
    {
        $validator = Validator::make($request->all(), ['api_key' => ['nullable', 'string', 'max:5000'], 'clear_api_key' => ['nullable', 'boolean'], 'enabled' => ['nullable', 'boolean']]);
        if ($validator->fails()) return $this->failure($request, 'Check the provider settings and try again.', $validator->errors()->toArray());
        try {
            $ai->updateProvider($provider, $validator->validated(), $request->user(), $request->ip());
        } catch (ValidationException $exception) {
            return $this->failure($request, 'Check the provider settings and try again.', $exception->errors());
        } catch (Throwable $exception) {
            Log::error('AI provider settings could not be saved.', [
                'provider' => $provider,
                'actor_user_id' => $request->user()?->id,
                'exception' => $exception,
            ]);

            return $this->failure(
                $request,
                'Provider settings could not be saved because of a server configuration problem. Try again or check the application log.',
                ['_form' => 'The provider connection was not changed.'],
                500,
                false,
            );
        }

        $overview = $ai->overview();
        $savedProvider = $overview['providers'][$provider] ?? null;
        $message = ($savedProvider['enabled'] ?? false)
            ? 'AI provider settings saved. Refresh its model catalog to verify the connection.'
            : 'AI provider key saved, but the provider is disabled. Enable it and save again before refreshing its model catalog.';

        return $this->success($request, $message, [
            'provider_key' => $provider,
            'provider' => $savedProvider,
        ]);
    }

    public function refreshCatalog(Request $request, string $provider, AiModelSettingsService $ai): RedirectResponse|JsonResponse
    {
        try {
            $result = $ai->refreshCatalog($provider, $request->user(), $request->ip());
            $overview = $ai->overview();
            return $this->success($request, "Catalog refreshed: {$result['seen']} models found; {$result['unavailable']} catalog entries unavailable.", [
                'provider_key' => $provider,
                'provider' => $overview['providers'][$provider] ?? null,
                'route' => $this->clientRoute($overview['route'] ?? null),
            ]);
        } catch (Throwable $exception) {
            $message = $exception instanceof RuntimeException ? $exception->getMessage() : 'The model catalog could not be refreshed.';
            if ($request->expectsJson()) return $this->failure($request, $message, ['provider' => $message], 422, false);

            return back()->withErrors(['provider' => $message])->with('ai_catalog_failed', $provider);
        }
    }

    public function savePageEditorRoute(Request $request, AiModelSettingsService $ai): RedirectResponse|JsonResponse
    {
        $validator = Validator::make($request->all(), ['primary_model' => ['required', 'string', 'max:225'], 'fallbacks' => ['required', 'array', 'min:1', 'max:2'], 'fallbacks.*' => ['nullable', 'string', 'max:225']]);
        if ($validator->fails()) return $this->failure($request, 'Choose a primary model and at least one fallback.', $validator->errors()->toArray());
        $fallbacks = array_values(array_filter($validator->validated()['fallbacks'], fn ($value): bool => is_string($value) && $value !== ''));
        try {
            $ai->savePageEditorRoute($validator->validated()['primary_model'], $fallbacks, $request->user(), $request->ip());
        } catch (ValidationException $exception) {
            return $this->failure($request, 'The page editor route could not be saved.', $exception->errors());
        } catch (Throwable $exception) {
            return $this->failure($request, 'The page editor route could not be saved.', ['primary_model' => $exception->getMessage()]);
        }

        $overview = $ai->overview();
        return $this->success($request, 'Page editor model route saved. Run the compatibility check before using it.', [
            'route' => $this->clientRoute($overview['route'] ?? null),
        ]);
    }

    public function testPageEditorRoute(Request $request, AiModelSettingsService $ai): RedirectResponse|JsonResponse
    {
        try {
            $result = $ai->testPageEditorRoute($request->user(), $request->ip());
            $overview = $ai->overview();
            return $this->success($request, "Page editor route verified across {$result['models']} models ({$result['latency_ms']} ms total test time).", [
                'route' => $this->clientRoute($overview['route'] ?? null),
            ]);
        } catch (Throwable $exception) {
            $message = $exception instanceof RuntimeException ? $exception->getMessage() : 'The page editor route could not be tested.';
            $overview = $ai->overview();
            return $this->failure($request, $message, ['route' => $message], 422, false, [
                'route' => $this->clientRoute($overview['route'] ?? null),
            ]);
        }
    }

    /** @param array<string, mixed> $data */
    private function success(Request $request, string $message, array $data = []): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'data' => $data]);
        }

        return back()->with('status', $message);
    }

    /** @param array<string, mixed> $errors
     *  @param array<string, mixed> $data
     */
    private function failure(Request $request, string $message, array $errors, int $status = 422, bool $preserveInput = true, array $data = []): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'errors' => $errors, 'data' => $data], $status);
        }

        $response = back()->withErrors($errors ?: ['error' => $message]);

        return $preserveInput ? $response->withInput($request->except('api_key')) : $response;
    }

    /** @return array{status:string,primary:string,fallbacks:array<int,string>} */
    private function clientRoute(?object $route): array
    {
        return [
            'status' => (string) ($route->status ?? 'unconfigured'),
            'primary' => $route && $route->primary_provider && $route->primary_model ? $route->primary_provider.'|'.$route->primary_model : '',
            'fallbacks' => $this->fallbackSelections($route),
        ];
    }

    /** @return array<int, string> */
    private function fallbackSelections(?object $route): array
    {
        if (! $route) return [];
        $fallbacks = json_decode($route->fallback_models ?: '[]', true);
        if (! is_array($fallbacks)) return [];

        return collect($fallbacks)->filter(fn ($model): bool => is_array($model) && is_string($model['provider'] ?? null) && is_string($model['model_id'] ?? null))
            ->map(fn (array $model): string => $model['provider'].'|'.$model['model_id'])->values()->all();
    }
}
