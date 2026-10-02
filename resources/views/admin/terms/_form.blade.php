{{--
    Rich text editor for Terms & Conditions (Admin Flow 10).

    Uses a dependency-free contenteditable editor with an execCommand toolbar
    rather than pulling in TipTap/Quill — the output is a plain HTML string that
    is published and rendered verbatim on the customer side. If you later swap
    in TipTap, only this partial and its Alpine data need changing; the
    `content` textarea stays the contract with the controller.
--}}
@extends('layouts.admin')

@section('title', $term->exists ? 'Edit Terms' : 'New Terms')
@section('heading', 'Terms & Conditions Editor')

@section('content')
    <a href="{{ route('admin.terms.index') }}" class="mb-5 inline-flex items-center gap-1.5 text-sm text-ink-muted transition hover:text-primary">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
        Back to terms
    </a>

    <x-ui.page-header
        eyebrow="Policies"
        :title="$term->exists ? 'Edit Terms · '.$term->category->label() : 'New Terms'"
        :description="$term->exists && $term->is_published
            ? 'This policy is live. Saving replaces the text customers see right now.'
            : 'Only published policies are shown to customers.'"
    />


    @php $action = $term->exists ? route('admin.terms.update', $term) : route('admin.terms.store'); @endphp

    <form method="POST" action="{{ $action }}" x-data="richTextEditor()" novalidate>
        @csrf
        @if ($term->exists) @method('PUT') @endif

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <x-ui.card title="Content" subtitle="Formatting is stored as HTML and rendered on the customer-facing T&C pages.">
                    <div class="mb-4">
                        <x-ui.form.select
                            name="category"
                            label="Category"
                            required
                            :value="old('category', $term->exists ? $term->category->value : $category->value)"
                            :options="$categories"
                        />
                    </div>

                    {{-- Toolbar --}}
                    <div class="mb-2.5 flex flex-wrap items-center gap-1 rounded-xl border border-primary/15 bg-linen/60 p-2">
                        @foreach ([
                            ['bold', 'Bold', 'M6 4h8a4 4 0 0 1 0 8H6zM6 12h9a4 4 0 0 1 0 8H6z'],
                            ['italic', 'Italic', 'M19 4h-9M14 20H5M15 4 9 20'],
                            ['underline', 'Underline', 'M6 4v6a6 6 0 0 0 12 0V4M4 21h16'],
                        ] as [$command, $label, $path])
                            <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg text-primary transition hover:bg-cream" title="{{ $label }}" @click="exec('{{ $command }}')">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $path }}"/></svg>
                            </button>
                        @endforeach

                        <span class="mx-1 h-6 w-px bg-primary/15"></span>

                        <select class="input w-32 py-1.5 text-xs" @change="formatBlock($event.target.value); $event.target.value = ''" aria-label="Text style">
                            <option value="">Paragraph</option>
                            <option value="h2">Heading 2</option>
                            <option value="h3">Heading 3</option>
                            <option value="blockquote">Quote</option>
                        </select>

                        <span class="mx-1 h-6 w-px bg-primary/15"></span>

                        <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg text-primary transition hover:bg-cream" title="Bulleted list" @click="exec('insertUnorderedList')">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01"/></svg>
                        </button>
                        <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg text-primary transition hover:bg-cream" title="Numbered list" @click="exec('insertOrderedList')">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01"/></svg>
                        </button>

                        <span class="mx-1 h-6 w-px bg-primary/15"></span>

                        <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg text-primary transition hover:bg-cream" title="Insert link" @click="insertLink()">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/></svg>
                        </button>
                        <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg text-primary transition hover:bg-cream" title="Divider" @click="exec('insertHorizontalRule')">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M3 12h18"/></svg>
                        </button>

                        <span class="ml-auto"></span>

                        <button type="button" class="toggle-link" @click="toggleSource()">Show HTML</button>
                    </div>

                    {{-- WYSIWYG surface --}}
                    <div
                        x-ref="editor"
                        contenteditable="true"
                        @input="sync"
                        @blur="sync"
                        class="prose-bta min-h-72 rounded-xl border border-primary/20 bg-white/70 px-4 py-3.5 text-sm leading-relaxed text-ink focus:border-gold focus:outline-none focus:ring-2 focus:ring-gold/40
                               [&_h2]:font-display [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:text-primary [&_h2]:mb-3
                               [&_h3]:mt-5 [&_h3]:font-semibold [&_h3]:text-primary
                               [&_blockquote]:border-l-2 [&_blockquote]:border-gold [&_blockquote]:pl-4 [&_blockquote]:italic
                               [&_li]:ml-5 [&_li]:list-disc [&_ol_li]:list-decimal
                               [&_p]:mb-3 [&_strong]:font-semibold [&_strong]:text-primary [&_ul]:mb-3"
                    ></div>

                    {{-- Raw HTML source (same value, toggled) --}}
                    <textarea
                        x-ref="source"
                        x-show="showSource"
                        @input="syncFromSource"
                        rows="14"
                        class="input mt-3 font-mono text-xs"
                        spellcheck="false"
                        aria-label="Terms HTML source"
                    ></textarea>

                    {{-- Single source of truth posted to the server --}}
                    <input type="hidden" name="content" :value="html">
                </x-ui.card>
            </div>

            <aside class="space-y-6">
                <x-ui.card title="Publishing">
                    @if ($term->exists && $term->is_published)
                        <x-ui.alert type="warning" class="mb-4" :dismissible="false">
                            This policy is live. Saving replaces the text customers see right now — keep
                            &ldquo;Publish&rdquo; ticked, since un-ticking it is refused.
                        </x-ui.alert>
                    @endif

                    <x-ui.form.checkbox
                        name="is_published"
                        value="1"
                        :checked="old('is_published', $term->exists ? $term->is_published : true)"
                        label="Publish this policy (show it to customers)"
                    />
                </x-ui.card>

                <div class="flex flex-col gap-3">
                    <button type="submit" class="btn-primary w-full">
                        {{ $term->exists ? 'Save Changes' : 'Save Terms' }}
                    </button>
                    <a href="{{ route('admin.terms.index') }}" class="btn-ghost w-full">Cancel</a>
                </div>
            </aside>
        </div>
    </form>
@endsection

@push('scripts')
    @php
        $contentSeed = old('content', $term->exists ? $term->content : '');
    @endphp

    <script>
        /**
         * Minimal rich text editor. `html` is the single source of truth that
         * gets posted; the contenteditable surface and the raw textarea are two
         * views of the same string.
         */
        document.addEventListener('alpine:init', () => {
            Alpine.data('richTextEditor', () => ({
                html: {!! json_encode($contentSeed, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!},
                showSource: false,
                ready: false,

                init() {
                    const editor = this.$refs.editor;
                    const source = this.$refs.source;

                    editor.innerHTML = this.html;
                    source.value = this.html;
                    this.ready = true;

                    // The surface must never end up empty silently.
                    this.$el.addEventListener('submit', () => {
                        if (!editor.innerHTML.trim() && !source.value.trim()) {
                            alert('Please enter the terms content before saving.');
                            return;
                        }
                    });
                },

                exec(command) {
                    this.$refs.editor.focus();
                    document.execCommand(command, false, null);
                    this.sync();
                },

                formatBlock(tag) {
                    if (!tag) return;
                    this.$refs.editor.focus();
                    document.execCommand('formatBlock', false, tag);
                    this.sync();
                },

                insertLink() {
                    const url = window.prompt('Link URL (https://…)');

                    if (!url) return;

                    this.$refs.editor.focus();
                    document.execCommand('createLink', false, url);
                    this.sync();
                },

                sync() {
                    if (!this.ready) return;
                    this.html = this.$refs.editor.innerHTML;
                },

                syncFromSource() {
                    this.html = this.$refs.source.value;
                    this.$refs.editor.innerHTML = this.html;
                },

                toggleSource() {
                    this.sync();
                    this.$refs.source.value = this.html;
                    this.showSource = !this.showSource;
                },
            }));
        });
    </script>
@endpush
