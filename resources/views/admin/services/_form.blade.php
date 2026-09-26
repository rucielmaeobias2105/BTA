{{--
    Shared service create/edit form. Variants are edited inline via Alpine and
    serialised into the `variants[]` array that ServiceRequest validates.
--}}
@extends('layouts.admin')

@section('title', $service->exists ? 'Edit Service' : 'Add Service')
@section('heading', $service->exists ? 'Edit Service' : 'Add Service')

@section('content')
    <a href="{{ route('admin.services.index') }}" class="mb-5 inline-flex items-center gap-1.5 text-sm text-ink-muted transition hover:text-primary">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
        Back to services
    </a>

    <x-ui.page-header
        eyebrow="Catalogue"
        :title="$service->exists ? 'Edit Service' : 'Add Service'"
        :description="$service->exists
            ? 'Update the details, photo and variants for “'.$service->name.'”.'
            : 'Create a new service for the customer-facing menu.'"
    />

    <x-ui.errors />

    @php $action = $service->exists ? route('admin.services.update', $service) : route('admin.services.store'); @endphp

    <form method="POST" action="{{ $action }}" enctype="multipart/form-data" x-data="variantEditor()" novalidate>
        @csrf
        @if ($service->exists) @method('PUT') @endif

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <x-ui.card title="Service Details">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-ui.form.input name="name" label="Service Name" required :value="old('name', $service->name)" placeholder="e.g. Glow Manicure" />
                        <x-ui.form.input name="slug" label="Slug" :value="old('slug', $service->slug)" hint="Leave blank to generate from the name." placeholder="glow-manicure" />
                    </div>

                    <div class="mt-5">
                        {{-- Datalist-backed free text so new categories are allowed --}}
                        <x-ui.form.input
                            name="category"
                            label="Category"
                            required
                            :value="old('category', $service->category)"
                            list="bta-service-categories"
                            placeholder="e.g. Nail Care"
                        />
                        <datalist id="bta-service-categories">
                            @foreach ($categories as $category)
                                <option value="{{ $category }}"></option>
                            @endforeach
                        </datalist>
                    </div>

                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <x-ui.form.input name="price" type="number" step="0.01" min="0" label="Price (₱)" required :value="old('price', $service->price)" prefix="₱" />
                        <x-ui.form.input name="duration_minutes" type="number" min="5" max="1440" label="Duration (minutes)" required :value="old('duration_minutes', $service->duration_minutes ?: 60)" />
                    </div>

                    <div class="mt-5">
                        <x-ui.form.textarea name="description" label="Description" rows="4" :value="old('description', $service->description)" placeholder="What does this service include?" />
                    </div>
                </x-ui.card>

                {{-- Variants (short/long hair style pricing) --}}
                <x-ui.card title="Variants" subtitle="Optional — e.g. Short Hair, Long Hair with different pricing.">
                    <div class="space-y-3" id="variant-rows">
                        <template x-for="(variant, index) in variants" :key="variant.key">
                            <div class="flex flex-wrap items-end gap-3 rounded-xl border border-primary/12 bg-linen/50 p-3.5">
                                <input type="hidden" :name="`variants[${index}][id]`" :value="variant.id || ''">

                                <div class="min-w-40 flex-1">
                                    <label class="label" :for="`variant-name-${index}`">Variant Name</label>
                                    <input :id="`variant-name-${index}`" :name="`variants[${index}][name]`" type="text"
                                           x-model="variant.name" placeholder="Short Hair" maxlength="100"
                                           class="input" @input="validate">
                                </div>

                                <div class="w-32">
                                    <label class="label" :for="`variant-price-${index}`">Price (₱)</label>
                                    <input :id="`variant-price-${index}`" :name="`variants[${index}][price]`" type="number"
                                           step="0.01" min="0" x-model="variant.price" class="input" @input="validate">
                                </div>

                                <div class="w-32">
                                    <label class="label" :for="`variant-duration-${index}`">Duration</label>
                                    <input :id="`variant-duration-${index}`" :name="`variants[${index}][duration_minutes]`" type="number"
                                           min="5" max="1440" x-model="variant.duration" class="input" placeholder="inherit">
                                </div>

                                <label class="flex cursor-pointer items-center gap-2 pb-2.5 text-xs font-medium text-ink">
                                    <input type="checkbox" class="checkbox" :name="`variants[${index}][is_default]`"
                                           value="1" x-model="variant.isDefault" @change="makeDefault(index)">
                                    Default
                                </label>

                                <button type="button" class="btn-danger btn-sm mb-1" @click="remove(index)">Remove</button>
                            </div>
                        </template>
                    </div>

                    <p x-show="error" x-cloak x-text="error" class="input-error-text mt-3"></p>

                    <button type="button" class="btn-secondary btn-sm mt-4" @click="add()">+ Add Variant</button>
                </x-ui.card>
            </div>

            {{-- Sidebar --}}
            <aside class="space-y-6">
                <x-ui.card title="Photo">
                    @if ($service->photo_path)
                        <img src="{{ Storage::url($service->photo_path) }}" alt="" class="mb-4 aspect-[4/3] w-full rounded-xl object-cover">
                    @endif

                    <x-ui.form.input name="photo" type="file" label="Upload Photo" accept="image/jpeg,image/png,image/webp" hint="JPG, PNG or WEBP. Max 3 MB." />
                </x-ui.card>

                <x-ui.card title="Visibility">
                    <div class="space-y-4">
                        <x-ui.form.checkbox name="is_active" value="1" :checked="old('is_active', $service->exists ? $service->is_active : true)" label="Active (visible to customers)" />
                        <x-ui.form.checkbox name="is_featured" value="1" :checked="old('is_featured', $service->is_featured)" label="Featured on the home page" />
                    </div>
                </x-ui.card>

                <div class="flex flex-col gap-3">
                    <button type="submit" class="btn-primary w-full">
                        {{ $service->exists ? 'Save Changes' : 'Create Service' }}
                    </button>
                    <a href="{{ route('admin.services.index') }}" class="btn-ghost w-full">Cancel</a>
                </div>
            </aside>
        </div>
    </form>
@endsection

@push('scripts')
    @php
        // Built in PHP: Blade's @json() parser cannot walk a multi-line ternary.
        $variantSeed = $service->exists
            ? $service->variants->map(fn ($v) => [
                'key' => 'existing-'.$v->id,
                'id' => $v->id,
                'name' => $v->name,
                'price' => (float) $v->price,
                'duration' => $v->duration_minutes,
                'isDefault' => (bool) $v->is_default,
            ])->values()
            : collect(old('variants', []));
    @endphp

    <script>
        /**
         * Inline variant rows for the service form. Alpine owns the DOM but the
         * inputs carry real `name` attributes, so the browser posts a normal
         * `variants[]` array that ServiceRequest validates.
         */
        document.addEventListener('alpine:init', () => {
            Alpine.data('variantEditor', () => ({
                variants: {!! json_encode($variantSeed, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!},
                error: '',

                init() {
                    this.validate();
                },

                add() {
                    this.variants.push({
                        key: 'new-' + Date.now() + '-' + Math.random().toString(36).slice(2, 7),
                        id: null,
                        name: '',
                        price: 0,
                        duration: null,
                        isDefault: this.variants.length === 0,
                    });

                    this.validate();
                },

                remove(index) {
                    this.variants.splice(index, 1);
                    this.validate();
                },

                makeDefault(index) {
                    this.variants.forEach((variant, i) => { variant.isDefault = i === index; });
                },

                validate() {
                    const seen = new Set();

                    for (const variant of this.variants) {
                        const name = (variant.name || '').trim().toLowerCase();

                        if (name) {
                            if (seen.has(name)) {
                                this.error = 'Variant names must be unique.';
                                return;
                            }

                            seen.add(name);
                        }
                    }

                    this.error = '';
                },
            }));
        });
    </script>
@endpush
