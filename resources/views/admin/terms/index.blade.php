@extends('layouts.admin')

@section('title', 'Terms & Conditions')
@section('heading', 'Terms & Conditions')

@section('content')

    {{--
        The reference admin terms screen is one card per policy, each with a
        textarea and a Save button, all on the index. BTA reproduces that
        structure: one editor per category, saved straight from this page.

        A category owns exactly one row. Saving overwrites it in place — there is
        no version history — and the "Publish this policy" checkbox decides
        whether customers see it. The checkbox defaults to ticked, live or not,
        so re-saving a live policy cannot silently unpublish it; un-ticking it
        and saving is how an admin deliberately takes a policy down.
    --}}
    <div class="grid gap-6 xl:grid-cols-2">
        @foreach (\App\Enums\TermsCategory::cases() as $category)
            @php
                $row = ($grouped[$category->value] ?? collect())->first();
                $live = $published[$category->value] ?? null;
            @endphp

            <x-ui.card :title="$category->label().' Terms'">
                <x-slot:actions>
                    {{-- Preview, for a policy that has a live row at all.

                         This used to live in the version history list, once per
                         version row. With that list gone there would be no way to
                         see the customer-facing rendering at all, and the raw
                         textarea above is not it — what a customer reads is the
                         published row put through the terms dialog, with its
                         numbered list and styling.

                         So it sits on the card, once, and only where there is
                         something published to preview. It dispatches the shared
                         dialog rather than linking out, so an admin checks the
                         text without leaving the screen they are editing on. --}}
                    @if ($live)
                        <x-terms.link :category="$category" class="btn-ghost btn-sm no-underline">
                            Preview
                        </x-terms.link>
                    @endif

                    @if ($live)
                        <x-ui.badge status="completed" label="Live" />
                    @else
                        <x-ui.badge status="cancelled" label="Not published" />
                    @endif
                </x-slot:actions>

                @can('admin.terms.manage')
                    <form method="POST" action="{{ route('admin.terms.store') }}">
                        @csrf
                        <input type="hidden" name="category" value="{{ $category->value }}">

                        <x-ui.form.textarea
                            name="content"
                            label="Content"
                            rows="10"
                            required
                            :value="old('content', $row?->content ?? '')"
                        />

                        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                            {{-- Always ticked by default. It used to default to
                                 unticked for a policy that was already live, on
                                 the reasoning that this "cannot silently
                                 unpublish" it — which had the opposite effect:
                                 the common case, editing a live policy's text,
                                 demoted it on every save. Un-ticking and saving
                                 is now the deliberate way to take one down. --}}
                            <x-ui.form.checkbox
                                name="is_published"
                                value="1"
                                label="Publish this policy (show it to customers)"
                            />

                            <button type="submit" class="btn-primary btn-sm">Save</button>
                        </div>
                    </form>
                @else
                    <div class="prose-bta max-w-none text-sm leading-relaxed text-ink">
                        @if ($row)
                            {!! $row->content !!}
                        @else
                            <p>No content yet.</p>
                        @endif
                    </div>
                @endcan
            </x-ui.card>
        @endforeach
    </div>
@endsection
