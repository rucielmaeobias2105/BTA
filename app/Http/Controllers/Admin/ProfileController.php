<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminRole;
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
            'roleOptions' => AdminRole::options(),
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
            'role' => ['required', Rule::in(AdminRole::values())],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $payload = $request->safe()->only(['first_name', 'last_name', 'email', 'username', 'role']);

        if ($request->filled('password')) {
            $payload['password'] = Hash::make($request->string('password')->toString());
        }

        $admin->update($payload);

        return back()->with('status', 'Your admin profile has been updated.');
    }
}
