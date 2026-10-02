@extends('layouts.admin')

@section('title', $user->full_name)
@section('heading', 'Customer Profile')

@section('content')
    <a href="{{ route('admin.users.index') }}" class="mb-5 inline-flex items-center gap-1.5 text-sm text-ink-muted transition hover:text-primary">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
        Back to users
    </a>

    {{--
        No page header and no stat cards, and no Deactivate/Delete here either:
        the top bar already says which customer this is, the Account Details
        card beside the history carries the name, the dates and the account's
        state, and both actions stay on the row in the Registered Users list
        where they are one click from every other account rather than buried on
        a page reached by opening each one.
    --}}
    <div class="grid gap-6 xl:grid-cols-3">
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
        </div>

        <aside>
            <x-ui.card title="Account Details">
                <div class="mb-5 flex items-center gap-4">
                    @if ($user->profile_photo_path)
                        <img src="{{ $user->profile_photo_url }}" alt="" class="h-16 w-16 rounded-full object-cover ring-2 ring-gold ring-offset-2 ring-offset-cream">
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
