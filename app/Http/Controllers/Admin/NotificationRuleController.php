<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NotificationRuleController extends Controller
{
    public function index(Request $request): View
    {
        $prefix = $request->user()->isSuperAdmin() ? 'admin' : 'nstp_admin';

        return view('admin.notification-rules.index', [
            'layout' => $prefix === 'admin' ? 'layouts.admin' : 'layouts.nstp-admin',
            'routePrefix' => $prefix,
            'rules' => NotificationRule::with('updater')->orderBy('id')->get(),
            'channels' => NotificationRule::CHANNELS,
        ]);
    }

    public function update(Request $request, NotificationRule $notificationRule): RedirectResponse
    {
        $definition = config('notification_rules.'.$notificationRule->event_key);
        abort_unless($definition, 404);

        $request->merge(['is_enabled' => $request->boolean('is_enabled')]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_enabled' => ['required', 'boolean'],
            'channels' => ['required_if:is_enabled,1', 'array'],
            'channels.*' => ['required', 'distinct', Rule::in(array_keys(NotificationRule::CHANNELS))],
            'title_template' => ['required', 'string', 'max:180'],
            'body_template' => ['required', 'string', 'max:2000'],
            'schedule_mode' => ['required', Rule::in(['immediate', 'delayed'])],
            'delay_minutes' => ['required_if:schedule_mode,delayed', 'nullable', 'integer', 'min:1', 'max:43200'],
        ]);

        $notificationRule->update([
            ...$validated,
            'channels' => array_values($validated['channels'] ?? []),
            'delay_minutes' => $validated['schedule_mode'] === 'delayed' ? $validated['delay_minutes'] : 0,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('status', $notificationRule->name.' notification rule was updated.');
    }
}
