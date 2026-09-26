@extends('layouts.admin')

@section('title', 'My Profile')
@section('heading', 'My Profile')

@section('content')
    <x-ui.page-header
        eyebrow="Account"
        title="My Profile"
        description="Update the credentials you use to sign in to the admin panel."
    />

    <x-ui.errors />

    <form method="POST" action="{{ route('admin.profile.update') }}" class="max-w-3xl space-y-6" novalidate>
        @csrf
        @method('PATCH')

        <x-ui.card title="Personal Details">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.form.input name="first_name" label="First Name" required :value="old('first_name', $admin->first_name)" />
                <x-ui.form.input name="last_name" label="Last Name" required :value="old('last_name', $admin->last_name)" />
                <x-ui.form.input name="email" type="email" label="Email" required :value="old('email', $admin->email)" />
                <x-ui.form.input name="username" label="Username" required :value="old('username', $admin->username)" />
                <x-ui.form.select name="role" label="Role" required :value="old('role', $admin->role->value)" :options="$roleOptions" />
            </div>

            <div class="mt-5 rounded-xl bg-linen/70 px-4 py-3 text-xs text-ink-muted">
                Last signed in: <span class="font-medium text-primary">{{ $admin->last_login_at?->format('M j, Y g:i A') ?? 'this is your first session' }}</span>
            </div>
        </x-ui.card>

        <x-ui.card title="Change Password" subtitle="Leave both fields blank to keep your current password.">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.form.password name="password" label="New Password" autocomplete="new-password" hint="Minimum of 8 characters." />
                <x-ui.form.password name="password_confirmation" label="Confirm New Password" autocomplete="new-password" />
            </div>
        </x-ui.card>

        <div class="flex justify-end">
            <button type="submit" class="btn-primary sm:min-w-44">Save Changes</button>
        </div>
    </form>
@endsection
