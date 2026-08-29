<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\PortalAccessService;
use App\Services\ProfilePhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(private PortalAccessService $access) {}

    public function edit(Request $request): View
    {
        return view('portal.profile', [
            'user' => $request->user(),
            'layout' => $this->access->layout($request->user()),
            'routePrefix' => $this->access->routePrefix($request->user()),
        ]);
    }

    public function update(Request $request, ProfilePhotoService $photos): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=4096,max_height=4096'],
        ]);
        $user->fill(['name' => $validated['name'], 'email' => $validated['email']]);
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
        $user->save();
        if ($request->hasFile('profile_photo')) {
            $photos->replace($user, $request->file('profile_photo'));
        }

        return back()->with('status', 'Profile information updated successfully.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);
        $request->user()->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ])->save();

        return back()->with('status', 'Your password has been changed successfully.');
    }
}
