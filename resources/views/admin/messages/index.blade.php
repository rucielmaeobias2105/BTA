@extends('layouts.admin')

@section('title', 'Contact Messages')
@section('heading', 'Contact Messages')

@section('content')
    <x-ui.page-header
        eyebrow="Inbox"
        title="Contact Messages"
        description="Enquiries submitted through the customer Contact Us form."
    />

    <div class="mb-5 flex flex-wrap items-center gap-2">
        <a href="{{ route('admin.messages.index') }}"
           class="rounded-pill px-3.5 py-1.5 text-sm font-medium transition {{ empty($filters['filter']) ? 'bg-primary text-cream' : 'bg-cream text-ink hover:bg-linen' }}">
            All
        </a>
        <a href="{{ route('admin.messages.index', ['filter' => 'unread']) }}"
           class="rounded-pill px-3.5 py-1.5 text-sm font-medium transition {{ ($filters['filter'] ?? '') === 'unread' ? 'bg-primary text-cream' : 'bg-cream text-ink hover:bg-linen' }}">
            Unread ({{ $unreadCount }})
        </a>
    </div>

    <form method="GET" action="{{ route('admin.messages.index') }}" class="bta-card mb-6 p-5">
        <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-64 flex-1">
                <x-ui.form.input name="search" label="Search" placeholder="Name, email or message" :value="$filters['search'] ?? null" />
            </div>
            <button type="submit" class="btn-primary">Search</button>
            @if (array_filter($filters))
                <a href="{{ route('admin.messages.index') }}" class="btn-ghost">Clear</a>
            @endif
        </div>
    </form>

    @if ($messages->isEmpty())
        <x-ui.empty title="No messages" description="Customer enquiries will appear here." />
    @else
        <div class="space-y-4">
            @foreach ($messages as $message)
                <article @class([
                    'bta-card p-5',
                    'border-l-4 !border-l-primary' => ! $message->is_read,
                ])>
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold text-primary">{{ $message->name }}</p>
                                <x-ui.badge status="gold" :label="$message->topic->label()" />
                                @unless ($message->is_read)
                                    <x-ui.badge status="pending" label="New" />
                                @endunless
                            </div>

                            <p class="mt-0.5 text-xs text-ink-muted">
                                <a href="mailto:{{ $message->email }}" class="hover:text-primary">{{ $message->email }}</a>
                                &middot; {{ $message->created_at->format('M j, Y g:i A') }}
                            </p>

                            <p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-ink">{{ $message->message }}</p>
                        </div>

                        <form method="POST" action="{{ route('admin.messages.destroy', $message) }}" class="shrink-0"
                              onsubmit="return confirm('Delete this message?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-danger btn-sm">Delete</button>
                        </form>
                    </div>

                    <form method="POST" action="{{ route('admin.messages.update', $message) }}" class="mt-4 border-t border-primary/10 pt-4">
                        @csrf
                        @method('PATCH')

                        <x-ui.form.textarea
                            name="admin_reply"
                            label="Internal Reply / Note"
                            rows="3"
                            :value="old('admin_reply', $message->admin_reply)"
                            placeholder="Record your response here and mark the message as read."
                        />

                        <div class="mt-3 flex items-center justify-end">
                            <button type="submit" class="btn-primary btn-sm">Save &amp; Mark Read</button>
                        </div>
                    </form>
                </article>
            @endforeach
        </div>

        <div class="mt-6">{{ $messages->links() }}</div>
    @endif
@endsection
