<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('admin.profile.edit', [
            'admin' => $request->user('admin'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $admin = $request->user('admin');

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => [
                'required', 'email:rfc', 'max:255',
                Rule::unique('admins', 'email')->ignore($admin->id)->whereNull('deleted_at'),
            ],
            'username' => [
                'required', 'string', 'max:64', 'alpha_dash',
                Rule::unique('admins', 'username')->ignore($admin->id)->whereNull('deleted_at'),
            ],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        // `role` is not part of the payload: there is one admin role, so the
        // column is left exactly as it is.
        //
        // `password` is dropped rather than written straight from the validated
        // data, because what comes back there is the plain confirmation match, not
        // a hash. Only the branch below that hashes it may set it.
        $payload = collect($data)->except('password')->all();

        if ($request->filled('password')) {
            $payload['password'] = Hash::make($request->string('password')->toString());
        }

        $admin->update($payload);

        return back()->with('status', 'Your admin profile has been updated.');
    }
}
