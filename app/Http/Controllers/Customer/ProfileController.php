<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Customer Flow 11 — Profile Management.
     */
    public function edit(Request $request): View
    {
        return view('customer.profile.edit', [
            'user' => $request->user(),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->safe()->except(['profile_photo', 'remove_photo', 'password']);

        if ($request->hasFile('profile_photo')) {
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }

            $path = $request->file('profile_photo')->store('profile-photos', 'public');

            $data['profile_photo_path'] = $path;
        } elseif ($request->boolean('remove_photo') && $user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);

            $data['profile_photo_path'] = null;
        }

        if ($request->filled('password')) {
            $data['password'] = $request->string('password')->toString();
        }

        $user->fill($data)->save();

        if ($request->filled('password')) {
            // Force other sessions to re-authenticate after a password change.
            Auth::logoutOtherDevices($request->string('password')->toString());
        }

        return back()->with('status', 'Your profile has been updated.');
    }

    public function destroyPhoto(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
            $user->forceFill(['profile_photo_path' => null])->save();
        }

        return back()->with('status', 'Profile photo removed.');
    }
}
