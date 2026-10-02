@extends('layouts.admin')

@section('title', 'My Profile')
@section('heading', 'My Profile')

@section('content')
    {{-- Title only. The eyebrow, the sentence under it and the password
         card's subtitle and hint are all gone; the fields they described stay,
         because an admin still needs to be able to change their password and a
         password field with no guidance is worse than one with it. --}}
    <x-ui.page-header title="My Profile" />


    <form method="POST" action="{{ route('admin.profile.update') }}" class="max-w-3xl space-y-6" novalidate>
        @csrf
        @method('PATCH')

        <x-ui.card title="Personal Details">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.form.input name="first_name" label="First Name" required :value="old('first_name', $admin->first_name)" />
                <x-ui.form.input name="last_name" label="Last Name" required :value="old('last_name', $admin->last_name)" />
                <x-ui.form.input name="email" type="email" label="Email" required :value="old('email', $admin->email)" />
                <x-ui.form.input name="username" label="Username" required :value="old('username', $admin->username)" />
            </div>

            {{-- There is one admin role, so it is shown rather than chosen. --}}
            <div class="mt-5 flex items-center gap-3 rounded-xl bg-linen/70 px-4 py-3">
                <span class="text-xs text-ink-muted">Role</span>
                <span class="badge badge-gold">{{ $admin->role->label() }}</span>
            </div>

            <div class="mt-5 rounded-xl bg-linen/70 px-4 py-3 text-xs text-ink-muted">
                Last signed in: <span class="font-medium text-primary">{{ $admin->last_login_at?->format('M j, Y g:i A') ?? 'this is your first session' }}</span>
            </div>
        </x-ui.card>

        <x-ui.card title="Change Password">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.form.password
                    name="password"
                    label="New Password"
                    icon="heroicon-o-lock-closed"
                    :toggle-icon="'heroicon-o-eye'"
                    autocomplete="new-password"
                />
                <x-ui.form.password
                    name="password_confirmation"
                    label="Confirm New Password"
                    icon="heroicon-o-lock-closed"
                    :toggle-icon="'heroicon-o-eye'"
                    autocomplete="new-password"
                />
            </div>
        </x-ui.card>

        <div class="flex justify-end">
            <button type="submit" class="btn-primary sm:min-w-44">Save Changes</button>
        </div>
    </form>
@endsection
