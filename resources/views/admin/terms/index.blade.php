@extends('layouts.admin')

@section('title', 'Terms & Conditions')
@section('heading', 'Terms & Conditions')

@section('content')
    <x-ui.page-header
        eyebrow="Policies"
        title="Terms & Conditions"
        description="Versioned content per category. Publishing a version retires the previous one and updates the customer-facing pages."
    >
        <x-slot:actions>
            <a href="{{ route('admin.terms.create') }}" class="btn-primary btn-sm">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 4.5v15m7.5-7.5h-15"/></svg>
                New Version
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.alert type="error" class="mb-6" :dismissible="false">
        @foreach ($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </x-ui.alert>

    <div class="space-y-6">
        @foreach (\App\Enums\TermsCategory::cases() as $category)
            @php
                $versions = $grouped[$category->value] ?? collect();
                $live = $published[$category->value] ?? null;
            @endphp

            <x-ui.card :title="$category->label().' Terms'">
                <x-slot:actions>
                    <a href="{{ route('admin.terms.create', ['category' => $category->value]) }}" class="text-xs font-medium text-primary underline underline-offset-2">Add version</a>
                </x-slot:actions>

                <div class="mb-4 flex flex-wrap items-center gap-2.5 rounded-xl bg-linen/60 px-4 py-3">
                    <span class="text-xs font-semibold uppercase tracking-wider text-ink-muted">Live for customers</span>
                    @if ($live)
                        <x-ui.badge status="completed" label="v{{ $live->version }} published" />
                        <span class="text-xs text-ink-muted">{{ $live->published_at?->format('M j, Y') }}</span>
                        <a href="{{ route('terms.show', $category->value) }}" target="_blank" rel="noopener" class="ml-auto text-xs font-medium text-primary underline underline-offset-2">Preview</a>
                    @else
                        <x-ui.badge status="cancelled" label="Not published" />
                    @endif
                </div>

                @if ($versions->isEmpty())
                    <p class="text-sm text-ink-muted">No versions yet.</p>
                @else
                    <ul class="space-y-2.5">
                        @foreach ($versions as $version)
                            <li @class([
                                'flex flex-wrap items-center justify-between gap-3 rounded-xl border px-4 py-3',
                                'border-status-completed/30 bg-status-completed-bg/25' => $version->is_published,
                                'border-primary/12 bg-linen/50' => ! $version->is_published,
                            ])>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-medium text-primary">Version {{ $version->version }}</p>
                                        @if ($version->is_published)
                                            <x-ui.badge status="completed" label="Live" />
                                        @else
                                            <x-ui.badge status="pending" label="Draft" />
                                        @endif
                                    </div>
                                    <p class="mt-0.5 text-xs text-ink-muted">
                                        {{ \Illuminate\Support\Str::limit(strip_tags($version->content), 110) }}
                                    </p>
                                    <p class="mt-1 text-[11px] text-ink-muted">
                                        {{ $version->admin?->full_name ?? 'System' }}
                                        &middot; {{ $version->created_at->format('M j, Y') }}
                                    </p>
                                </div>

                                <div class="flex shrink-0 flex-wrap items-center gap-1.5">
                                    <a href="{{ route('admin.terms.edit', $version) }}" class="btn-secondary btn-sm">Edit</a>

                                    @unless ($version->is_published)
                                        <form method="POST" action="{{ route('admin.terms.publish', $version) }}"
                                              onsubmit="return confirm('Publish version {{ $version->version }}? The current live version will be retired.')">
                                            @csrf
                                            <button type="submit" class="btn-primary btn-sm">Publish</button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.terms.destroy', $version) }}"
                                              onsubmit="return confirm('Delete draft version {{ $version->version }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-danger btn-sm">Delete</button>
                                        </form>
                                    @endunless
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        @endforeach
    </div>
@endsection
