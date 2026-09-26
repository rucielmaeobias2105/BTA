@php
    /** Icon + tone mapping shared with the payload written by the notifications. */
    $icons = [
        'calendar' => 'heroicon-o-calendar-days',
        'check' => 'heroicon-o-check-circle',
        'x' => 'heroicon-o-x-circle',
        'bell' => 'heroicon-o-bell',
        'star' => 'heroicon-o-star',
        'sparkles' => 'heroicon-o-sparkles',
        'gift' => 'heroicon-o-gift',
        'key' => 'heroicon-o-key',
    ];
@endphp

<div class="bta-card p-5">
    {{-- Header: filters + mark all read --}}
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-primary/10 pb-4">
        <div class="flex gap-2">
            <a href="{{ route('notifications.index') }}"
               class="rounded-pill px-3.5 py-1.5 text-sm font-medium transition {{ $filter === 'all' ? 'bg-primary text-cream' : 'bg-linen text-ink hover:bg-linen/70' }}">
                All ({{ $totalCount }})
            </a>
            <a href="{{ route('notifications.index', ['filter' => 'unread']) }}"
               class="rounded-pill px-3.5 py-1.5 text-sm font-medium transition {{ $filter === 'unread' ? 'bg-primary text-cream' : 'bg-linen text-ink hover:bg-linen/70' }}">
                Unread ({{ $unreadCount }})
            </a>
        </div>

        @if ($unreadCount > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                @method('PATCH')
                <button type="submit" class="toggle-link">Mark all as read</button>
            </form>
        @endif
    </div>

    {{-- Read-only list; customers cannot compose or edit --}}
    @if ($notifications->isEmpty())
        <div class="pt-6">
            <x-ui.empty
                title="No notifications"
                description="{{ $filter === 'unread' ? 'You are all caught up.' : 'Booking updates and announcements will appear here.' }}"
            />
        </div>
    @else
        <ul class="divide-y divide-primary/8">
            @foreach ($notifications as $notification)
                @php
                    $data = $notification->data;
                    $icon = $icons[$data['icon'] ?? 'bell'] ?? 'heroicon-o-bell';
                    $tone = $data['tone'] ?? 'gold';
                    $isUnread = $notification->read_at === null;
                @endphp

                <li class="flex items-start gap-4 py-4">
                    <span @class([
                        'flex h-10 w-10 shrink-0 items-center justify-center rounded-full',
                        'bg-status-pending-bg text-status-pending' => $tone === 'pending',
                        'bg-status-confirmed-bg text-status-confirmed' => $tone === 'confirmed',
                        'bg-status-progress-bg text-status-progress' => $tone === 'progress',
                        'bg-status-completed text-cream' => $tone === 'completed',
                        'bg-status-cancelled-bg text-status-cancelled' => $tone === 'cancelled',
                        'bg-gold/20 text-gold-dark' => ! in_array($tone, ['pending', 'confirmed', 'progress', 'completed', 'cancelled'], true),
                    ])>
                        <x-dynamic-component :component="$icon" class="h-5 w-5" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p @class([
                                'text-sm font-semibold',
                                'text-primary' => $isUnread,
                                'text-ink' => ! $isUnread,
                            ])>{{ $data['title'] ?? 'Notification' }}</p>

                            @if ($isUnread)
                                <span class="h-2 w-2 rounded-full bg-primary" title="Unread"></span>
                            @endif
                        </div>

                        <p class="mt-0.5 text-sm text-ink-muted">{{ $data['message'] ?? '' }}</p>
                        <p class="mt-1 text-xs text-ink-muted/80">{{ $notification->created_at->diffForHumans() }}</p>
                    </div>

                    <div class="flex shrink-0 flex-col items-end gap-1.5">
                        @if (! empty($data['url']))
                            <a href="{{ $data['url'] }}" class="text-xs font-medium text-primary underline underline-offset-2 hover:text-primary-dark">View</a>
                        @endif

                        @if ($isUnread)
                            <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="toggle-link">Mark read</button>
                            </form>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="mt-6">{{ $notifications->links() }}</div>
    @endif
</div>
