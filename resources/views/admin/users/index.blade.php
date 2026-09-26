@extends('layouts.admin')

@section('title', 'Registered Users')
@section('heading', 'Registered Users')

@section('content')
    <x-ui.page-header
        eyebrow="Customers"
        title="Registered Users"
        description="Search, review, deactivate or delete customer accounts. This screen is view/delete only — there are no new-user fields."
    />

    <div class="mb-5 grid gap-4 sm:grid-cols-3">
        <div class="bta-card p-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Total Users</p>
            <p class="mt-1 font-display text-2xl font-bold text-primary">{{ $users->total() }}</p>
        </div>
        <div class="bta-card p-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Active</p>
            <p class="mt-1 font-display text-2xl font-bold text-status-confirmed">{{ $activeCount }}</p>
        </div>
        <div class="bta-card p-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Deactivated</p>
            <p class="mt-1 font-display text-2xl font-bold text-status-cancelled">{{ $inactiveCount }}</p>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.users.index') }}" class="bta-card mb-6 p-5">
        <div class="grid gap-4 md:grid-cols-12">
            <div class="md:col-span-7">
                <x-ui.form.input name="search" label="Search" placeholder="Name or email address" :value="$filters['search'] ?? null" />
            </div>
            <div class="md:col-span-3">
                <x-ui.form.select
                    name="status"
                    label="Status"
                    :value="$filters['status'] ?? ''"
                    :options="['' => 'All Users', 'active' => 'Active only', 'inactive' => 'Deactivated only']"
                />
            </div>
            <div class="flex items-end gap-2 md:col-span-2">
                <button type="submit" class="btn-primary flex-1">Search</button>
                @if (array_filter($filters))
                    <a href="{{ route('admin.users.index') }}" class="btn-ghost">Clear</a>
                @endif
            </div>
        </div>
    </form>

    @if ($users->isEmpty())
        <x-ui.empty title="No users found" description="Try a different search term." />
    @else
        <div class="bta-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="bta-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>Joined</th>
                            <th class="text-center">Bookings</th>
                            <th class="text-center">Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td>
                                    <div class="flex items-center gap-3">
                                        @if ($user->profile_photo_path)
                                            <img src="{{ Storage::url($user->profile_photo_path) }}" alt="" class="h-9 w-9 rounded-full object-cover">
                                        @else
                                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-primary text-[11px] font-semibold text-cream">{{ $user->initials }}</span>
                                        @endif
                                        <div class="min-w-0">
                                            <p class="truncate font-medium text-primary">{{ $user->full_name }}</p>
                                            <p class="truncate text-xs text-ink-muted">{{ '@'.$user->username }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-ink">{{ $user->email }}</td>
                                <td class="whitespace-nowrap text-ink">{{ $user->contact_number }}</td>
                                <td class="whitespace-nowrap text-ink">{{ $user->created_at->format('M j, Y') }}</td>
                                <td class="text-center text-ink">{{ $user->appointments_count }}</td>
                                <td class="text-center">
                                    <x-ui.badge :status="$user->is_active ? 'confirmed' : 'cancelled'" :label="$user->is_active ? 'Active' : 'Inactive'" />
                                </td>
                                <td>
                                    <div class="flex justify-end gap-1.5">
                                        <a href="{{ route('admin.users.show', $user) }}" class="btn-secondary btn-sm">View</a>
                                        <form method="POST" action="{{ route('admin.users.status', $user) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn-gold btn-sm">{{ $user->is_active ? 'Deactivate' : 'Reactivate' }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                                              onsubmit="return confirm('Delete {{ $user->full_name }}? Their appointment history is kept for reporting.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-danger btn-sm">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6">{{ $users->links() }}</div>
    @endif
@endsection
