@extends('layouts.admin')

@section('title', $user->full_name)
@section('heading', 'Customer Profile')

@section('content')
    <a href="{{ route('admin.users.index') }}" class="mb-5 inline-flex items-center gap-1.5 text-sm text-ink-muted transition hover:text-primary">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
        Back to users
    </a>

    <x-ui.page-header
        eyebrow="Customer"
        :title="$user->full_name"
        :description="'Joined '.$user->created_at->format('F j, Y').' · Last login '.($user->last_login_at?->format('M j, Y') ?? 'never')"
    >
        <x-slot:actions>
            <form method="POST" action="{{ route('admin.users.status', $user) }}">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn-gold btn-sm">{{ $user->is_active ? 'Deactivate Account' : 'Reactivate Account' }}</button>
            </form>
            <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                  onsubmit="return confirm('Delete {{ $user->full_name }}? Their appointment history is kept for reporting.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-danger btn-sm">Delete</button>
            </form>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Total Bookings', $stats['total']],
            ['Completed', $stats['completed']],
            ['Cancelled', $stats['cancelled']],
            ['Lifetime Value', '₱'.number_format($stats['total_spent'], 2)],
        ] as [$label, $value])
            <div class="bta-card p-5">
                <p class="text-xs font-semibold uppercase tracking-wider text-ink-muted">{{ $label }}</p>
                <p class="mt-1.5 font-display text-2xl font-bold text-primary">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-ui.card title="Appointment History">
                @if ($user->appointments->isEmpty())
                    <p class="text-sm text-ink-muted">This customer has no appointments yet.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="bta-table">
                            <thead>
                                <tr>
                                    <th>Reference</th>
                                    <th>Service(s)</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th class="text-right">Total</th>
                                    <th class="text-right"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($user->appointments as $appointment)
                                    <tr>
                                        <td class="whitespace-nowrap font-mono text-xs font-semibold text-primary">{{ $appointment->reference_number }}</td>
                                        <td class="max-w-xs truncate text-ink">{{ $appointment->service_names }}</td>
                                        <td class="whitespace-nowrap text-ink">{{ $appointment->preferred_date->format('M j, Y') }}</td>
                                        <td><x-ui.badge :status="$appointment->status->badge()" :label="$appointment->status->label()" /></td>
                                        <td class="whitespace-nowrap text-right font-medium text-primary">₱{{ number_format((float) $appointment->total_amount, 2) }}</td>
                                        <td class="text-right">
                                            <a href="{{ route('admin.appointments.show', $appointment) }}" class="btn-ghost btn-sm">Open</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ui.card>

            <x-ui.card title="Reviews" :subtitle="$user->reviews->count().' review(s) written'">
                @if ($user->reviews->isEmpty())
                    <p class="text-sm text-ink-muted">No reviews written.</p>
                @else
                    <ul class="space-y-3.5">
                        @foreach ($user->reviews as $review)
                            <li class="border-b border-primary/8 pb-3.5 last:border-0 last:pb-0">
                                <div class="flex items-center justify-between gap-3">
                                    <x-ui.star-rating :value="$review->rating" :interactive="false" size="sm" />
                                    <span class="text-xs text-ink-muted">{{ $review->created_at->format('M j, Y') }}</span>
                                </div>
                                <p class="mt-1.5 text-sm text-ink">{{ $review->message }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>

        <aside>
            <x-ui.card title="Account Details">
                <div class="mb-5 flex items-center gap-4">
                    @if ($user->profile_photo_path)
                        <img src="{{ Storage::url($user->profile_photo_path) }}" alt="" class="h-16 w-16 rounded-full object-cover ring-2 ring-gold ring-offset-2 ring-offset-cream">
                    @else
                        <span class="flex h-16 w-16 items-center justify-center rounded-full bg-primary font-display text-lg font-semibold text-cream ring-2 ring-gold ring-offset-2 ring-offset-cream">{{ $user->initials }}</span>
                    @endif
                    <div>
                        <x-ui.badge :status="$user->is_active ? 'confirmed' : 'cancelled'" :label="$user->is_active ? 'Active' : 'Deactivated'" />
                    </div>
                </div>

                <dl class="space-y-3.5 text-sm">
                    @foreach ([
                        'Full Name' => $user->full_name,
                        'Email' => $user->email,
                        'Username' => '@'.$user->username,
                        'Contact Number' => $user->contact_number,
                        'Registered' => $user->created_at->format('M j, Y g:i A'),
                        'Last Login' => $user->last_login_at?->format('M j, Y g:i A') ?? 'Never',
                    ] as $label => $value)
                        <div class="border-b border-primary/5 pb-3.5 last:border-0 last:pb-0">
                            <dt class="text-xs font-semibold uppercase tracking-wider text-ink-muted">{{ $label }}</dt>
                            <dd class="mt-0.5 break-words text-ink">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-ui.card>
        </aside>
    </div>
@endsection
