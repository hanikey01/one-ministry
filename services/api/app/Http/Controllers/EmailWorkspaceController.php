<?php

namespace App\Http\Controllers;

use App\Models\MinistrySetting;
use App\Services\EmailDeliverySettingsService;
use App\Services\EmailTemplateService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class EmailWorkspaceController extends Controller
{
    public function index(Request $request, EmailDeliverySettingsService $delivery, EmailTemplateService $templates): View
    {
        return view('staff.email', ['settings' => MinistrySetting::findOrFail(MinistrySetting::SINGLETON_ID), 'user' => $request->user(), 'emailDelivery' => $delivery->setting(), 'emailDeliveryActive' => $delivery->active(), 'emailProviders' => EmailDeliverySettingsService::providers(), 'templates' => $templates->templates()]);
    }

    public function updateDelivery(Request $request, EmailDeliverySettingsService $delivery): RedirectResponse
    {
        $data = $request->validate(['provider' => ['required', Rule::in(array_keys(EmailDeliverySettingsService::providers()))], 'enabled' => ['nullable', 'boolean'], 'from_address' => ['required', 'email', 'max:254'], 'from_name' => ['required', 'string', 'max:160'], 'host' => ['nullable', 'string', 'max:253'], 'port' => ['nullable', 'integer', 'between:1,65535'], 'encryption' => ['nullable', Rule::in(['tls', 'ssl', ''])], 'username' => ['nullable', 'string', 'max:254'], 'password' => ['nullable', 'string', 'max:1024']]);
        $data['enabled'] = $request->boolean('enabled');
        $delivery->update($data, $request->user(), $request->ip());
        return to_route('staff.email')->with('status', 'Email delivery settings saved. Send a test before relying on this connection.');
    }

    public function testDelivery(Request $request, EmailDeliverySettingsService $delivery): RedirectResponse
    {
        $data = $request->validate(['test_email' => ['required', 'email', 'max:254']]);
        $delivery->test($data['test_email'], $request->user(), $request->ip());
        return to_route('staff.email')->with('status', 'Test message accepted by the configured mail transport for '.$data['test_email'].'. Check the inbox and spam folder; transport acceptance does not confirm delivery.');
    }

    public function updateTemplate(Request $request, string $template, EmailTemplateService $templates): RedirectResponse
    {
        $data = $request->validate(['subject' => ['required', 'string', 'max:255'], 'body_template' => ['required', 'string', 'max:12000'], 'enabled' => ['nullable', 'boolean']]);
        $data['enabled'] = $request->boolean('enabled');
        $templates->update($template, $data, $request->user(), $request->ip());
        return to_route('staff.email', ['template' => $template])->with('status', 'Email template saved.');
    }

    public function resetTemplate(Request $request, string $template, EmailTemplateService $templates): RedirectResponse
    {
        $templates->reset($template, $request->user(), $request->ip());
        return to_route('staff.email', ['template' => $template])->with('status', 'Template restored to the platform default.');
    }

    public function testTemplate(Request $request, string $template, EmailTemplateService $templates): RedirectResponse
    {
        $data = $request->validate(['test_email' => ['required', 'email', 'max:254']]);
        $templates->sendTest($template, $data['test_email']);
        return to_route('staff.email', ['template' => $template])->with('status', 'Template test sent to '.$data['test_email'].'.');
    }

    public function preview(string $template, EmailTemplateService $templates): \Illuminate\Http\Response
    {
        return response($templates->preview($template))->header('Content-Type', 'text/html')->header('Content-Security-Policy', "default-src 'none'; img-src https: data:; style-src 'unsafe-inline'; frame-ancestors 'self';");
    }
}
