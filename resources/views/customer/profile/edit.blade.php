@extends('layouts.customer')

@section('title', 'Profile')

@section('content')
    <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
        <x-ui.page-header
            eyebrow="Account"
            title="Profile Management"
            description="Keep your contact details and profile picture up to date."
        />

        <x-ui.errors />

        <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="space-y-6" novalidate>
            @csrf
            @method('PATCH')

            {{-- Profile picture --}}
            <x-ui.card title="Profile Picture">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
                    @if ($user->profile_photo_path)
                        <img src="{{ Storage::url($user->profile_photo_path) }}" alt="Profile picture"
                             class="h-24 w-24 rounded-full border-2 border-gold object-cover shadow-card">
                    @else
                        <span class="flex h-24 w-24 items-center justify-center rounded-full bg-primary font-display text-2xl font-semibold text-cream ring-2 ring-gold ring-offset-2 ring-offset-cream">
                            {{ $user->initials }}
                        </span>
                    @endif

                    <div class="flex-1">
                        <x-ui.form.input
                            name="profile_photo"
                            type="file"
                            label="Upload a new picture"
                            accept="image/jpeg,image/png,image/webp"
                            hint="JPG, PNG or WEBP. Maximum 2 MB."
                        />

                        @if ($user->profile_photo_path)
                            <button type="button" class="toggle-link mt-2" x-data x-on:click="$el.closest('form').querySelector('[name=remove_photo]').checked = true; $el.closest('form').submit()">
                                Remove current picture
                            </button>
                            <input type="hidden" name="remove_photo" value="1">
                        @endif
                    </div>
                </div>
            </x-ui.card>

            {{-- Personal details --}}
            <x-ui.card title="Personal Details">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.form.input name="first_name" label="First Name" required :value="$user->first_name" autocomplete="given-name" />
                    <x-ui.form.input name="last_name" label="Last Name" required :value="$user->last_name" autocomplete="family-name" />
                    <x-ui.form.input name="email" type="email" label="Email" required :value="$user->email" autocomplete="email" />
                    <x-ui.form.input name="contact_number" label="Contact Number" required :value="$user->contact_number" autocomplete="tel" />
                </div>

                <div class="mt-5 rounded-xl bg-linen/70 px-4 py-3 text-xs text-ink-muted">
                    Username: <span class="font-medium text-primary">{{ $user->username }}</span>
                    — you can use either your username or email to log in.
                </div>
            </x-ui.card>

            {{-- Optional password change --}}
            <x-ui.card title="Change Password" subtitle="Leave both fields blank to keep your current password.">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.form.password
                        name="password"
                        label="New Password"
                        autocomplete="new-password"
                        hint="Minimum of 8 characters."
                    />
                    <x-ui.form.password
                        name="password_confirmation"
                        label="Confirm New Password"
                        autocomplete="new-password"
                    />
                </div>
            </x-ui.card>

            <div class="flex justify-end">
                <button type="submit" class="btn-primary sm:min-w-44">Save Changes</button>
            </div>
        </form>
    </div>
@endsection
