<?php

namespace App\Http\Controllers;

use App\Actions\UpdateMinistrySettings;
use App\Models\MinistrySetting;
use App\Support\MinistryTerminology;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class MinistryTerminologyController extends Controller
{
    public function index(Request $request, MinistryTerminology $terminology): View
    {
        $settings = MinistrySetting::query()->findOrFail(MinistrySetting::SINGLETON_ID);

        return view('staff.terminology', [
            'settings' => $settings,
            'user' => $request->user(),
            'definitions' => $terminology->definitions(),
            'values' => $terminology->values($settings),
        ]);
    }

    public function update(Request $request, UpdateMinistrySettings $update): RedirectResponse|JsonResponse
    {
        $rules = ['terminology' => ['required', 'array:'.implode(',', array_map(fn (string $key): string => $key, array_keys(MinistryTerminology::TERMS)))]];
        foreach (MinistryTerminology::TERMS as $term => $_definition) {
            $rules["terminology.{$term}"] = ['required', 'array:singular,plural'];
            $rules["terminology.{$term}.singular"] = ['nullable', 'string', 'max:48'];
            $rules["terminology.{$term}.plural"] = ['nullable', 'string', 'max:48'];
        }

        $validated = $request->validate($rules);
        $normalized = [];

        foreach (MinistryTerminology::TERMS as $term => $definition) {
            foreach (['singular', 'plural'] as $form) {
                $value = trim((string) ($validated['terminology'][$term][$form] ?? ''));
                if ($value !== '' && $value !== $definition[$form]) {
                    $normalized[$term][$form] = $value;
                }
            }
        }

        $update->execute(['terminology' => $normalized ?: null], $request->user(), $request->ip());

        $message = 'Ministry terminology saved.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'data' => ['terminology' => $normalized]]);
        }

        return to_route('staff.terminology')->with('status', $message);
    }
}
