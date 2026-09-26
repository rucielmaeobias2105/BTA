@extends('layouts.customer')

@section('title', 'Terms & Conditions')

@section('content')
    <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
        <nav class="mb-6 flex items-center gap-2 text-sm text-ink-muted" aria-label="Breadcrumb">
            <a href="{{ route('home') }}" class="transition hover:text-primary">Home</a>
            <span aria-hidden="true">/</span>
            <span class="font-medium text-primary">{{ $category->label() }} Terms</span>
        </nav>

        <x-ui.page-header
            eyebrow="Policies"
            :title="$category->label().' Terms & Conditions'"
            :description="$terms
                ? 'Currently published version '.$terms->version.' — last updated '.$terms->published_at?->format('M j, Y').'.'
                : 'No published version for this category yet. Please check back soon.'"
        />

        <div class="grid gap-5 sm:grid-cols-3">
            @foreach (\App\Enums\TermsCategory::cases() as $case)
                <a href="{{ route('terms.show', $case->value) }}"
                   class="rounded-card border px-4 py-3 text-center text-sm font-medium transition
                          {{ $case === $category
                                ? 'border-primary bg-primary text-cream'
                                : 'border-primary/15 bg-cream text-ink hover:border-gold hover:text-primary' }}">
                    {{ $case->label() }}
                </a>
            @endforeach
        </div>

        <article class="bta-card mt-8 p-6 sm:p-10">
            @if ($terms)
                {{-- Admin-authored rich text (Admin Flow 10). Stored as HTML. --}}
                <div class="prose-bta max-w-none text-sm leading-relaxed text-ink [&_h2]:font-display [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:text-primary [&_h2]:mb-4 [&_h3]:mt-6 [&_h3]:font-semibold [&_h3]:text-primary [&_hr]:my-6 [&_li]:ml-5 [&_li]:list-disc [&_ol]:list-decimal [&_p]:mb-4 [&_strong]:font-semibold [&_strong]:text-primary [&_ul]:mb-4">
                    {!! $terms->content !!}
                </div>
            @else
                <x-ui.empty
                    title="Not published yet"
                    description="Our team is still finalising this policy. Please contact us if you have questions in the meantime."
                >
                    <x-slot:action>
                        <a href="{{ route('contact.create') }}" class="btn-secondary">Contact Us</a>
                    </x-slot:action>
                </x-ui.empty>
            @endif
        </article>

        <div class="mt-8 text-center">
            <a href="{{ route('appointments.create') }}" class="btn-primary">Book an Appointment</a>
        </div>
    </div>
@endsection
